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

        // Refund-entry adjustments: only completed refunds have ledger entries (ADR-30).
        // H-1: include BOTH Refund entries (sale reversal, negative) AND Commission entries with
        // subject_type=refund (commission returned to vendor, positive). Scoping both by subject_id in
        // $eligibleRefundIds captures the full net of a refund for this vendor on this batch's orders.
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

        // Clawback entries scoped to AFTER the vendor's last paid payout (H-3: clawbacks that were
        // already present when a prior cycle was built have already reduced that cycle's payable; only
        // clawbacks that arrived after the last paid payout are new and belong to the current cycle).
        $lastPaidAt = Payout::query()
            ->where('vendor_id', $vendorId)
            ->where('status', PayoutStatus::Paid->value)
            ->max('updated_at');

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
            'adjustments' => $refundAdjustments + $clawbackAdjustments,
            'per_order' => $perOrder,
        ];
    }
}
