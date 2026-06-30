<?php

namespace App\Repositories\Eloquent;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Refund;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;

final class LedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    public function create(array $attributes): LedgerEntry
    {
        return LedgerEntry::create($attributes);
    }

    public function dailySalesByVendor(string $reportDate): array
    {
        // Financial totals per vendor from ledger entries.
        $financial = LedgerEntry::query()
            ->select('vendor_id')
            ->selectRaw(
                'SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END) as gross',
                [LedgerEntryType::Sale->value],
            )
            ->selectRaw(
                'ABS(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END)) as commission',
                [LedgerEntryType::Commission->value],
            )
            ->where('subject_type', 'order')
            ->whereDate('created_at', $reportDate)
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        if ($financial->isEmpty()) {
            return [];
        }

        // Ticket quantities from order_items for those same orders.
        $ticketCounts = LedgerEntry::query()
            ->select('ledger_entries.vendor_id')
            ->selectRaw('SUM(oi.quantity) as tickets_sold')
            ->join('order_items as oi', 'oi.order_id', '=', 'ledger_entries.subject_id')
            ->where('ledger_entries.entry_type', LedgerEntryType::Sale->value)
            ->where('ledger_entries.subject_type', 'order')
            ->whereDate('ledger_entries.created_at', $reportDate)
            ->groupBy('ledger_entries.vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $result = [];
        foreach ($financial as $vendorId => $row) {
            $result[] = [
                'vendor_id' => $vendorId,
                'gross' => (int) $row->gross,
                'commission' => (int) $row->commission,
                'tickets_sold' => (int) ($ticketCounts[$vendorId]?->tickets_sold ?? 0),
            ];
        }

        return $result;
    }

    public function vendorPayoutAmounts(string $vendorId, array $eligibleOrderIds): array
    {
        if ($eligibleOrderIds === []) {
            return ['gross' => 0, 'commission' => 0, 'adjustments' => 0, 'per_order' => []];
        }

        // Sale entries per eligible order (positive).
        $saleRows = LedgerEntry::query()
            ->where('vendor_id', $vendorId)
            ->where('entry_type', LedgerEntryType::Sale->value)
            ->where('subject_type', 'order')
            ->whereIn('subject_id', $eligibleOrderIds)
            ->selectRaw('subject_id, SUM(amount) as total')
            ->groupBy('subject_id')
            ->get()
            ->pluck('total', 'subject_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Commission entries per eligible order (negative; we store absolute value on the Payout row).
        $commissionRows = LedgerEntry::query()
            ->where('vendor_id', $vendorId)
            ->where('entry_type', LedgerEntryType::Commission->value)
            ->where('subject_type', 'order')
            ->whereIn('subject_id', $eligibleOrderIds)
            ->selectRaw('subject_id, SUM(amount) as total')
            ->groupBy('subject_id')
            ->get()
            ->pluck('total', 'subject_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $gross = (int) array_sum($saleRows);
        $commission = abs((int) array_sum($commissionRows));

        // Per-order settled_amount = net of sale+commission for this vendor (used for PayoutItems).
        $perOrder = [];
        foreach ($eligibleOrderIds as $orderId) {
            $net = (int) ($saleRows[$orderId] ?? 0) + (int) ($commissionRows[$orderId] ?? 0);
            $perOrder[$orderId] = max(0, $net);
        }

        // Anchor the clawback window on paid_at (write-once, set only on paid transition) rather than
        // updated_at (mutable; a processing→processing retry updates it and can silently push the
        // boundary past legitimate clawback entries written in the same window).
        $lastPaidAt = Payout::query()
            ->where('vendor_id', $vendorId)
            ->where('status', PayoutStatus::Paid->value)
            ->max('paid_at');

        // Refund-entry adjustments: only completed refunds have ledger entries (ADR-30).
        // Include BOTH Refund entries (sale reversal, negative) AND Commission entries with
        // subject_type=refund (commission returned to vendor, positive). These cover refunds on
        // orders that are still in $eligibleOrderIds (not yet settled in a paid payout).
        $eligibleRefundIds = Refund::query()
            ->whereHas('payment', fn ($q) => $q->whereIn('order_id', $eligibleOrderIds))
            ->where('status', RefundStatus::Completed->value)
            ->pluck('id')
            ->all();

        $refundAdjustments = $eligibleRefundIds !== [] ? (int) LedgerEntry::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('entry_type', [LedgerEntryType::Refund->value, LedgerEntryType::Commission->value])
            ->where('subject_type', 'refund')
            ->whereIn('subject_id', $eligibleRefundIds)
            ->sum('amount') : 0;

        // Commission reversals for refunds on ALREADY-SETTLED orders: when a refund completes on an
        // order that was in a prior paid payout, ProcessRefundWebhookService writes a Clawback entry
        // (the debit, caught below) AND a Commission entry with subject_type=refund (the credit, NOT
        // in $eligibleRefundIds because that order is excluded from eligibleOrderIds). Without this
        // query the vendor is permanently short-changed by the returned commission on those refunds.
        // The $lastPaidAt filter (same window as clawbacks) prevents double-counting across cycles.
        $orphanedCommissionReversal = (int) LedgerEntry::query()
            ->where('vendor_id', $vendorId)
            ->where('entry_type', LedgerEntryType::Commission->value)
            ->where('subject_type', 'refund')
            ->when($eligibleRefundIds !== [], fn ($q) => $q->whereNotIn('subject_id', $eligibleRefundIds))
            ->when($lastPaidAt !== null, fn ($q) => $q->where('created_at', '>', $lastPaidAt))
            ->sum('amount');

        // Clawback entries: scoped to AFTER the vendor's last paid payout so clawbacks that were
        // already captured in a prior cycle are not double-counted.
        $clawbackQuery = LedgerEntry::query()
            ->where('vendor_id', $vendorId)
            ->where('entry_type', LedgerEntryType::Clawback->value);

        if ($lastPaidAt !== null) {
            $clawbackQuery->where('created_at', '>', $lastPaidAt);
        }

        $clawbackAdjustments = (int) $clawbackQuery->sum('amount');

        return [
            'gross' => $gross,
            'commission' => $commission,
            'adjustments' => $refundAdjustments + $orphanedCommissionReversal + $clawbackAdjustments,
            'per_order' => $perOrder,
        ];
    }

    public function dailyDiscountByVendor(string $reportDate): array
    {
        return LedgerEntry::query()
            ->select('ledger_entries.vendor_id')
            ->selectRaw('SUM((oi.original_price - oi.unit_price) * oi.quantity) as total_discount')
            ->join('order_items as oi', 'oi.order_id', '=', 'ledger_entries.subject_id')
            ->where('ledger_entries.entry_type', LedgerEntryType::Sale->value)
            ->where('ledger_entries.subject_type', 'order')
            ->whereDate('ledger_entries.created_at', $reportDate)
            ->whereColumn('oi.original_price', '>', 'oi.unit_price')
            ->groupBy('ledger_entries.vendor_id')
            ->get()
            ->map(fn ($row) => [
                'vendor_id' => $row->vendor_id,
                'total_discount' => (int) $row->total_discount,
            ])
            ->all();
    }

    public function pendingRefundAmountForOrders(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        return (int) Refund::query()
            ->whereHas('payment', fn ($q) => $q->whereIn('order_id', $orderIds))
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Pending->value])
            ->sum('amount');
    }
}
