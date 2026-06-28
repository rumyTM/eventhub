<?php

namespace App\Services\Refunds;

use App\Enums\OrderStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Exceptions\Refunds\RefundNotAllowedException;
use App\Exceptions\Refunds\RefundNotEligibleException;
use App\Jobs\ExecuteRefundJob;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Support\Refunds\RefundPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates a refund REQUEST against a paid order (CLAUDE.md §F Refunds). This chunk decides eligibility +
 * the auto-derived amount and persists a `requested` refund row idempotently. It does **not** move money
 * or write any reversal ledger entry — execution is dispatched to {@see ExecuteRefundJob} and
 * lands in Chunk C.
 *
 * Idempotency mirrors checkout/charge (ADR-09): **one open refund per order**. The authoritative guard is
 * a `SELECT … FOR UPDATE` on the order row inside the transaction — a duplicate request (concurrent or
 * sequential) finds the existing open refund and returns it, never a second row, never a second job.
 *
 * Money is integer minor units throughout (ADR-08); single-currency per order is assumed (ADR-12,
 * enforced at checkout) so the order's one currency governs every amount here.
 */
final class RefundService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly PaymentRepositoryInterface $payments,
        private readonly RefundRepositoryInterface $refunds,
        private readonly RefundPolicy $policy,
    ) {}

    /**
     * Request a refund for an order. `$items` selects a subset of tickets (partial refund); null/empty
     * refunds the whole order. The attendee NEVER supplies an amount — it is derived by the policy.
     *
     * @param  list<array{order_item_id: string, quantity: int}>|null  $items
     */
    public function request(Order $order, RefundReason $reason, ?array $items = null): Refund
    {
        $order = $this->orders->findForRefund($order->id)
            ?? throw new RefundNotAllowedException;

        // Only a paid (or partially-refunded) order can be refunded — never a pending/expired/failed/
        // cancelled order, and never one already fully refunded.
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded], true)) {
            throw new RefundNotAllowedException(__('api.refunds.not_allowed'));
        }

        $payment = $this->payments->succeededForOrder($order->id)
            ?? throw new RefundNotAllowedException(__('api.refunds.no_payment'));

        // Cheap idempotent short-circuit: an order may have only one open refund at a time. This avoids a
        // transaction for the common duplicate; it is NOT the authoritative gate — that lives under the
        // row lock below, where the open-refund check AND the cumulative-cap policy are (re-)evaluated.
        $open = $this->refunds->findOpenForOrder($order->id);
        if ($open !== null) {
            return $open;
        }

        // Authoritative create: lock the order row so concurrent requests serialize. The open-refund
        // check, the already-refunded total, and the policy decision are ALL evaluated under the lock so
        // the cumulative-refund cap can never be computed from stale data (a second request that loses the
        // race sees the now-existing open refund and returns it — no second row, no second job).
        return DB::transaction(function () use ($order, $payment, $reason, $items): Refund {
            $this->orders->lockForUpdate($order->id);

            $existing = $this->refunds->findOpenForOrder($order->id);
            if ($existing !== null) {
                return $existing;
            }

            [$selectedBaseMinor, $eventStartsAt] = $this->resolveSelection($order, $items);

            $decision = $this->policy->decide(
                reason: $reason,
                eventStartsAt: $eventStartsAt,
                now: now(),
                selectedBaseMinor: $selectedBaseMinor,
                chargeMinor: $payment->amount,
                alreadyRefundedMinor: $this->refunds->refundedTotalForOrder($order->id),
            );

            if (! $decision->eligible) {
                throw new RefundNotEligibleException($this->denialMessage($decision->denialReason));
            }

            return $this->refunds->create([
                'payment_id' => $payment->id,
                'amount' => $decision->amountMinor,        // auto-derived minor units (never user-set)
                'policy_applied' => $decision->policyApplied,
                'status' => RefundStatus::Requested->value, // no money moved yet — execution is Chunk C
                'reason' => $decision->reason->value,
            ]);
        });
    }

    /**
     * Resolve the refundable base (sum of selected line totals, minor units) and the reference event
     * start (the EARLIEST start among the tickets being refunded — the most conservative window).
     *
     * @param  list<array{order_item_id: string, quantity: int}>|null  $items
     * @return array{0: int, 1: CarbonInterface}
     */
    private function resolveSelection(Order $order, ?array $items): array
    {
        $itemsById = $order->items->keyBy('id');

        // Build the (order_item, quantity) lines being refunded: explicit subset, or the whole order.
        $lines = [];
        if ($items === null || $items === []) {
            foreach ($order->items as $item) {
                $lines[] = [$item, (int) $item->quantity];
            }
        } else {
            foreach ($items as $selection) {
                $item = $itemsById->get($selection['order_item_id'])
                    ?? throw new RefundNotAllowedException(__('api.refunds.invalid_item'));

                $quantity = (int) $selection['quantity'];
                if ($quantity > (int) $item->quantity) {
                    throw new RefundNotAllowedException(__('api.refunds.invalid_quantity'));
                }

                $lines[] = [$item, $quantity];
            }
        }

        $base = 0;
        $earliestStart = null;

        foreach ($lines as [$item, $quantity]) {
            $base += (int) $item->unit_price * $quantity;

            $start = $item->ticketType?->event?->starts_at;
            if ($start !== null && ($earliestStart === null || $start->lessThan($earliestStart))) {
                $earliestStart = $start;
            }
        }

        if ($earliestStart === null) {
            // Can't resolve a refund window without the event start (e.g. integrity gap).
            throw new RefundNotAllowedException(__('api.refunds.not_allowed'));
        }

        return [$base, $earliestStart];
    }

    private function denialMessage(?string $denialReason): string
    {
        return match ($denialReason) {
            'already_refunded' => __('api.refunds.already_refunded'),
            default => __('api.refunds.not_eligible_window'),
        };
    }
}
