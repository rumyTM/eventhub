<?php

namespace App\Services\Payments;

use App\Actions\Payouts\CalculateCommission;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Exceptions\Payments\WebhookAmountMismatchException;
use App\Helpers\LogHelper;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Order;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\TicketHoldRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies a payment-service webhook result and closes the purchase loop (CLAUDE.md §F.3–4). Runs in
 * ONE transaction with the order row locked FOR UPDATE; every step is idempotent on replay.
 *
 * Order of guards is deliberate:
 *   1. unknown order        → no-op (a stale/foreign callback never errors);
 *   2. order not pending     → no-op (already paid/expired/failed — a replayed or late webhook must
 *                              never double-issue tickets, double-write the ledger, or re-increment
 *                              quantity_sold);
 *   3. amount/currency       → must equal the order's total/currency, else reject (422) and mutate
 *                              nothing (a tampered or misrouted result can't mark an order paid).
 *
 * On SUCCESS: the matching payment row → succeeded (a success with NO payment of record never settles
 * — audit integrity). Then the reservation must still be live: only NON-EXPIRED holds convert, and if
 * none do (a charge that confirmed after the 15-min window) we do NOT issue — those seats were already
 * freed for other buyers, so issuing would oversell; the order is left pending for the expiry net and
 * the recorded success becomes a refund concern. When the reservation holds: order → paid; one valid
 * QR ticket per held unit; quantity_sold moves HERE (never at checkout); a signed `sale` (+) and
 * `commission` (−) ledger row PER owning vendor (a cart may span vendors — split via
 * order_item → ticket_type → event → vendor), using the order's snapshotted commission_rate (ADR-14,
 * integer math). The confirmation notification is queued after commit.
 *
 * On FAILURE: the payment row → failed; NO tickets, NO quantity_sold change, NO sale ledger. The order
 * is left `pending` for the 15-minute hold-expiry safety net to reclaim inventory and expire it
 * (CLAUDE.md §F.5) — we do not eagerly mark it failed, so a buyer retry against the same order is still
 * possible until the hold lapses.
 */
final class ProcessPaymentWebhookService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly PaymentRepositoryInterface $payments,
        private readonly TicketHoldRepositoryInterface $holds,
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketTypeRepositoryInterface $ticketTypes,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly CalculateCommission $calculateCommission,
    ) {}

    /**
     * @param  array{event: string, payment_ref: string, order_id: string, status: array{value: string}, amount: int, currency: string}  $payload
     */
    public function handle(array $payload): void
    {
        $settled = DB::transaction(function () use ($payload): bool {
            $order = $this->orders->lockForUpdate($payload['order_id']);

            // (1) Unknown order, or (2) already terminal — idempotent no-op (no double anything).
            if ($order === null || $order->status !== OrderStatus::Pending) {
                return false;
            }

            // (3) The callback must settle the exact money the order owes.
            if ((int) $payload['amount'] !== (int) $order->total || $payload['currency'] !== $order->currency) {
                throw new WebhookAmountMismatchException;
            }

            $payment = $this->payments->findByExternalRefForOrder($order->id, $payload['payment_ref']);

            if ($payload['status']['value'] === PaymentStatus::Failed->value) {
                if ($payment !== null) {
                    $this->payments->markStatus($payment, PaymentStatus::Failed);
                }

                // Leave the order pending — the hold-expiry job reclaims inventory and expires it.
                return false;
            }

            // --- SUCCESS ---
            // Never settle an order with no payment of record: external_ref is written at
            // checkout-initiation, so a legitimate success always has a matching row. A miss means an
            // unreconcilable/foreign callback — log and no-op rather than mark an order paid blind.
            if ($payment === null) {
                LogHelper::logEntry(LogHelper::LOG_WARNING, 'Webhook success with no matching payment row', [
                    'order_id' => $order->id,
                ]);

                return false;
            }

            // Faithful to the gateway result: the charge succeeded, record it regardless of fulfilment.
            $this->payments->markStatus($payment, PaymentStatus::Succeeded);

            // The reservation must still be live. If every hold lapsed before this (slow gateway/late
            // webhook), its seats were already freed at the 15-min mark — issuing now would oversell.
            // Leave the order pending for ReleaseExpiredHolds to expire; the (already-recorded) success
            // becomes a refund concern in a later slice. Idempotent: a replay re-converts nothing.
            if ($this->holds->convertActiveForOrder($order->id) === 0) {
                LogHelper::logEntry(LogHelper::LOG_WARNING, 'Paid charge arrived after hold expiry — not issuing tickets', [
                    'order_id' => $order->id,
                ]);

                return false;
            }

            $this->orders->markPaid($order);
            $this->issueTicketsAndSettle($order);

            return true;
        });

        // Off the request path, and only once tickets are actually issued (never on replay/failure).
        if ($settled) {
            SendOrderConfirmationJob::dispatch($payload['order_id']);
        }
    }

    /**
     * Issue one ticket per held unit, move quantity_sold, and write the per-vendor sale/commission
     * ledger rows. order_items are the authoritative issued quantity. Holds are already converted by
     * the caller (which also verified the reservation was still live before reaching here).
     */
    private function issueTicketsAndSettle(Order $order): void
    {
        $order->loadMissing('items.ticketType.event');

        /** @var array<string, int> $grossByVendor */
        $grossByVendor = [];

        foreach ($order->items as $item) {
            for ($seat = 0; $seat < $item->quantity; $seat++) {
                $this->tickets->create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'qr_code' => $this->mintQrCode(),
                    'status' => TicketStatus::Valid->value,
                ]);
            }

            // Denormalized sold counter moves on payment success — atomic increment.
            $this->ticketTypes->incrementSold($item->ticket_type_id, $item->quantity);

            $vendorId = $item->ticketType?->event?->vendor_id;
            if ($vendorId !== null) {
                $grossByVendor[$vendorId] = ($grossByVendor[$vendorId] ?? 0) + ($item->quantity * $item->unit_price);
            }
        }

        $rate = (string) $order->commission_rate; // decimal:4 snapshot (ADR-14)

        foreach ($grossByVendor as $vendorId => $gross) {
            $commission = $this->calculateCommission->handle($gross, $rate);

            // Signed, append-only (ADR-13): +sale credits the vendor, −commission is the platform's cut.
            $this->ledger->create([
                'vendor_id' => $vendorId,
                'subject_type' => 'order',
                'subject_id' => $order->id,
                'entry_type' => LedgerEntryType::Sale->value,
                'amount' => $gross,
                'currency' => $order->currency,
            ]);

            $this->ledger->create([
                'vendor_id' => $vendorId,
                'subject_type' => 'order',
                'subject_id' => $order->id,
                'entry_type' => LedgerEntryType::Commission->value,
                'amount' => -$commission,
                'currency' => $order->currency,
            ]);
        }
    }

    /** A unique, non-sequential QR token (the unique index on tickets.qr_code is the hard guard). */
    private function mintQrCode(): string
    {
        return 'TKT-'.Str::lower((string) Str::ulid());
    }
}
