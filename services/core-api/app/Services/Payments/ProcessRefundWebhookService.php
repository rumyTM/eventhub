<?php

namespace App\Services\Payments;

use App\Actions\Payouts\CalculateCommission;
use App\Enums\LedgerEntryType;
use App\Enums\RefundStatus;
use App\Exceptions\Payments\OrderSettlementIntegrityException;
use App\Exceptions\Payments\RefundWebhookMismatchException;
use App\Jobs\SendRefundConfirmationJob;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applies a payment-service REFUND webhook result, mirroring {@see ProcessPaymentWebhookService} for the
 * charge path (CLAUDE.md §F/§H; ADR-09/10/13/14/20/23). Runs in ONE transaction with the order row
 * locked FOR UPDATE; every step is idempotent on replay.
 *
 * Order of guards is deliberate:
 *   1. unknown order              → no-op (a stale/foreign callback never errors);
 *   2. no OPEN refund for it      → no-op (replay after the refund already resolved, or none) — so a
 *                                    re-delivered success never writes a second set of reversal rows;
 *   3. amount/currency            → must equal the open refund's amount + the order's currency, else
 *                                    reject (422) and mutate nothing.
 *
 * On SUCCESS (`completed`): mark the refund completed; write the SIGNED reversal ledger PER owning vendor
 * (resolve the vendor exactly as settlement does — order_item → ticket_type → event → vendor; the refund
 * amount is split across vendors by gross share). Each vendor gets a negative `refund` reversal of its
 * share AND a positive `commission` reversal (the platform earns nothing on the refunded portion — ADR-23
 * symmetry, sale + full reversal net to zero). If the vendor's revenue for this order was ALREADY paid out
 * (a `paid` payout_item exists), the negative entry is a `clawback` instead, recovering disbursed funds
 * (ADR-20 — the rare fallback). Tickets are voided + the order set refunded/partially_refunded, and the
 * refund-confirmation notification is queued after commit.
 *
 * On FAILURE (`failed`): mark the refund failed; NO ledger row, NO ticket/order change — failure moves no money.
 */
final class ProcessRefundWebhookService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly RefundRepositoryInterface $refunds,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TicketRepositoryInterface $tickets,
        private readonly PayoutRepositoryInterface $payouts,
        private readonly CalculateCommission $calculateCommission,
    ) {}

    /**
     * @param  array{event: string, refund_ref: string, payment_ref: string, order_id: string, status: array{value: string}, amount: int, currency: string}  $payload
     */
    public function handle(array $payload): void
    {
        $completedRefundId = DB::transaction(function () use ($payload): ?string {
            $order = $this->orders->lockForUpdate($payload['order_id']);

            // (1) Unknown order — idempotent no-op.
            if ($order === null) {
                return null;
            }

            // (2) No open refund — a replay after the refund already resolved (or none). No-op.
            $refund = $this->refunds->lockOpenForOrder($order->id);
            if ($refund === null) {
                return null;
            }

            // (3) The callback must resolve the exact refund money (amount) in the order's currency.
            if ((int) $payload['amount'] !== (int) $refund->amount || $payload['currency'] !== $order->currency) {
                throw new RefundWebhookMismatchException;
            }

            if ($payload['status']['value'] === RefundStatus::Failed->value) {
                $this->refunds->markFailed($refund);  // no money moved → no ledger

                return null;
            }

            // --- SUCCESS (completed) ---
            $this->refunds->markCompleted($refund);
            $this->writeReversalLedger($order, $refund);
            $this->voidTicketsAndUpdateOrder($order, $refund);

            return $refund->id;
        });

        // Off the request path, and only once a refund actually completed (never on replay/failure).
        if ($completedRefundId !== null) {
            SendRefundConfirmationJob::dispatch($completedRefundId);
        }
    }

    /**
     * Write the signed reversal ledger rows per owning vendor. The refund amount is split across the
     * order's vendors by gross share (exact for a full-order refund; proportional otherwise — the
     * per-item selection is not persisted on the refund, so this is the faithful approximation).
     */
    private function writeReversalLedger(Order $order, Refund $refund): void
    {
        // Load ticket_type + event WITH trashed: a vendor/admin cancellation soft-deletes the event, and
        // an event-cancellation refund (ADR-23) is a primary refund driver — without withTrashed the
        // vendor would resolve null, abort settlement, and the webhook would 500-loop forever. Mirrors
        // OrderRepository::findForRefund (the request path already loads trashed).
        $order->loadMissing([
            'items.ticketType' => fn ($q) => $q->withTrashed(),
            'items.ticketType.event' => fn ($q) => $q->withTrashed(),
        ]);

        /** @var array<string, int> $grossByVendor */
        $grossByVendor = [];

        foreach ($order->items as $item) {
            // Resolve the owning vendor exactly as settlement does. A soft-deleted ticket_type/event
            // makes vendor_id null — writing a refund while dropping the vendor's ledger attribution
            // would move money with no audit record, so abort the whole transaction loudly.
            $vendorId = $item->ticketType?->event?->vendor_id;
            if ($vendorId === null) {
                throw new OrderSettlementIntegrityException($order->id, $item->id);
            }

            $grossByVendor[$vendorId] = ($grossByVendor[$vendorId] ?? 0) + ($item->quantity * $item->unit_price);
        }

        $orderGross = array_sum($grossByVendor);
        $rate = (string) $order->commission_rate; // decimal:4 snapshot (ADR-14)

        foreach ($this->allocate($refund->amount, $grossByVendor, $orderGross) as $vendorId => $refundShare) {
            if ($refundShare <= 0) {
                continue;
            }

            $commissionReversal = $this->calculateCommission->handle($refundShare, $rate);
            $alreadyPaidOut = $this->payouts->orderSettledPaidForVendor($order->id, $vendorId);

            // Vendor-side negative reversal: a `refund` of not-yet-settled revenue, or a `clawback` of
            // already-disbursed funds when the vendor was already paid out for this order (ADR-20).
            $this->ledger->create([
                'vendor_id' => $vendorId,
                'subject_type' => 'refund',
                'subject_id' => $refund->id,
                'entry_type' => ($alreadyPaidOut ? LedgerEntryType::Clawback : LedgerEntryType::Refund)->value,
                'amount' => -$refundShare,
                'currency' => $order->currency,
            ]);

            // Platform returns its commission on the refunded portion (+) — it earns nothing on a refund,
            // so the original sale and its reversal net to zero (ADR-23). Same snapshot rate (ADR-14).
            $this->ledger->create([
                'vendor_id' => $vendorId,
                'subject_type' => 'refund',
                'subject_id' => $refund->id,
                'entry_type' => LedgerEntryType::Commission->value,
                'amount' => $commissionReversal,
                'currency' => $order->currency,
            ]);
        }
    }

    /**
     * Void tickets + set the order's refunded state. When cumulative refunds reach the order total the
     * order is fully `refunded` and its still-valid tickets are voided; otherwise it is
     * `partially_refunded` (the exact tickets aren't identifiable without a persisted per-item selection,
     * so they are left valid — a documented follow-up).
     */
    private function voidTicketsAndUpdateOrder(Order $order, Refund $refund): void
    {
        // Completed-only total (M-2): unconditionally correct for the full-vs-partial threshold
        // regardless of open-refund state; includes the just-marked-completed refund.
        $refundedTotal = $this->refunds->completedRefundedTotalForOrder($order->id);

        if ($refundedTotal >= (int) $order->total) {
            $this->tickets->voidValidForOrder($order->id);
            $this->orders->markRefunded($order);

            return;
        }

        $this->orders->markPartiallyRefunded($order);
    }

    /**
     * Split a refund amount across vendors by gross share, remainder-safe (the last vendor absorbs any
     * rounding so the parts sum to exactly the refund amount). Single-vendor orders trivially get the
     * whole amount.
     *
     * @param  array<string, int>  $grossByVendor
     * @return array<string, int>
     */
    private function allocate(int $refundAmount, array $grossByVendor, int $orderGross): array
    {
        if ($orderGross <= 0) {
            return [];
        }

        $allocations = [];
        $assigned = 0;
        $vendorIds = array_keys($grossByVendor);
        $lastIndex = count($vendorIds) - 1;

        foreach ($vendorIds as $i => $vendorId) {
            if ($i === $lastIndex) {
                $allocations[$vendorId] = $refundAmount - $assigned; // remainder absorbs rounding
            } else {
                $share = intdiv($refundAmount * $grossByVendor[$vendorId], $orderGross); // floor — last vendor absorbs remainder, always ≥ 0
                $allocations[$vendorId] = $share;
                $assigned += $share;
            }
        }

        return $allocations;
    }
}
