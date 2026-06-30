<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TransactionType;
use App\Exceptions\Payments\ChargeNotRefundableException;
use App\Exceptions\Payments\IdempotencyKeyConflictException;
use App\Exceptions\Payments\RefundCurrencyMismatchException;
use App\Exceptions\Payments\RefundExceedsChargeException;
use App\Gateways\GatewayManager;
use App\Jobs\DeliverRefundResultJob;
use App\Models\Refund;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns a refund end-to-end, mirroring {@see ChargeService} exactly (CLAUDE.md §A.4/§D/§E/§G):
 *
 *   1. createRefund() — reserve the `pending` Refund idempotently (ADR-09). The Idempotency-Key maps to
 *      exactly one Refund; same key+body replays it, same key+different body is a 409. The gateway and
 *      order reference are copied from the original charge so the row is self-describing for the webhook.
 *   2. resolve()      — let the original charge's gateway decide success/failure, persist the terminal
 *      status, and append ONE row to the `transactions` ledger with a NEGATIVE signed amount (money out).
 *      Runs inside the queued DeliverRefundResultJob so the HTTP request returns `pending` immediately and
 *      the real result arrives via the signed webhook to core-api.
 *
 * resolve() is idempotent: a row-locked re-check short-circuits a refund that is already terminal, so a
 * job retry (or a duplicate dispatch) never rolls the gateway twice or writes a second ledger row — the
 * money guarantee that mirrors the charge path's webhook-replay handling.
 *
 * core-api owns the refund *policy* and the cumulative-refund validation; this service executes the exact
 * amount it is told, with one local sanity guard: a single refund may not exceed the original charge.
 */
final class RefundService
{
    public function __construct(
        private readonly RefundRepositoryInterface $refunds,
        private readonly PaymentRepositoryInterface $payments,
        private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly GatewayManager $gateways,
    ) {}

    /**
     * @param  array{payment_ref: string, amount: int, currency: string, reason?: string|null}  $payload
     */
    public function createRefund(string $idempotencyKey, array $payload): Refund
    {
        $requestHash = $this->requestHash($payload);

        // --- Idempotency replay / conflict (ADR-09) ---
        $seen = $this->idempotencyKeys->findByKey($idempotencyKey);
        if ($seen !== null) {
            if (! hash_equals($seen->request_hash, $requestHash)) {
                throw new IdempotencyKeyConflictException;
            }

            return $this->refunds->findOrFail($seen->response_payload['refund_id']);
        }

        // Resolve the original charge and apply local sanity guards (NOT policy — core-api owns the
        // 100/50/0% policy and cumulative validation against its ledger):
        //   - the charge must be `succeeded` — a pending charge may still fail, a failed one never
        //     captured money, so there is nothing to give back;
        //   - the refund currency must match the charge (refunding a different currency poisons the
        //     ledger's net position);
        //   - a single refund may not exceed the original charge.
        $payment = $this->payments->findOrFail($payload['payment_ref']);
        if ($payment->status !== PaymentStatus::Succeeded) {
            throw new ChargeNotRefundableException;
        }
        if ($payload['currency'] !== $payment->currency) {
            throw new RefundCurrencyMismatchException;
        }
        if ($payload['amount'] > $payment->amount) {
            throw new RefundExceedsChargeException;
        }

        try {
            return DB::transaction(function () use ($idempotencyKey, $requestHash, $payload, $payment): Refund {
                // Lock the payment row and re-sum all non-failed refunds inside the transaction so two
                // concurrent refund requests cannot both pass the single-refund guard and together exceed
                // the original charge (defence-in-depth; core-api is the primary policy owner).
                $locked = $this->payments->findForUpdate($payment->id);
                $alreadyRefunded = $this->refunds->sumNonFailedForPayment($locked->id);
                if ($alreadyRefunded + $payload['amount'] > $locked->amount) {
                    throw new RefundExceedsChargeException;
                }

                return $this->persist($idempotencyKey, $requestHash, $payload, $locked->gateway->value, $locked->order_id);
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request used the same key first — resolve as a replay rather than 500.
            $seen = $this->idempotencyKeys->findByKey($idempotencyKey);

            if ($seen !== null) {
                return $this->refunds->findOrFail($seen->response_payload['refund_id']);
            }

            throw $e;
        }
    }

    /**
     * Queue the asynchronous resolution + signed webhook, delayed by the charge gateway's configured
     * processing time. A refund that is already terminal (an idempotent replay) needs no further work.
     */
    public function scheduleResolution(Refund $refund): void
    {
        if ($refund->status->isTerminal()) {
            return;
        }

        $delaySeconds = $this->gateways->make($refund->gateway->value)->delaySeconds();

        DeliverRefundResultJob::dispatch($refund->id)
            ->delay(Carbon::now()->addSeconds($delaySeconds));
    }

    /**
     * Resolve a pending refund: ask the original charge's gateway for the outcome, persist the terminal
     * status, and append the ledger row. Returns the (possibly already-resolved) Refund.
     *
     * The gateway call runs OUTSIDE the transaction so the `refunds` row lock is held only for the write.
     * The lock plus the terminal-status re-check inside the transaction is the authoritative single-write
     * guard (mirrors ADR-07): a job retry or duplicate dispatch re-enters, finds the refund already
     * terminal, and short-circuits — never a second gateway roll, never a second ledger row.
     */
    public function resolve(string $refundId): Refund
    {
        // 1. Optimistic read (no lock): nothing to do if already resolved.
        $refund = $this->refunds->findOrFail($refundId);
        if ($refund->status->isTerminal()) {
            return $refund;
        }

        // 2. Gateway decision OUTSIDE the transaction (no row lock held while it runs). The refund runs
        //    through the SAME gateway that processed the original charge.
        $result = $this->gateways
            ->make($refund->gateway->value)
            ->refund($refund->amount, $refund->currency, $refund->payment_id);

        $status = $result->succeeded ? RefundStatus::Completed : RefundStatus::Failed;

        // 3. Lock, re-check (authoritative guard), persist + append the ledger row atomically.
        return DB::transaction(function () use ($refundId, $result, $status): Refund {
            $refund = $this->refunds->findForUpdate($refundId);

            // A concurrent resolve already committed — discard this roll, never double-write.
            if ($refund->status->isTerminal()) {
                return $refund;
            }

            $refund = $this->refunds->markResolved($refund, $status, $result->reference);

            // Append-only ledger: a successful refund is money OUT, so it is recorded as a NEGATIVE
            // signed amount; a failed refund moved nothing, so it is 0 — SUM(amount) stays an honest
            // net position. Keyed to the original `payment_id` so a charge and its refunds reconcile.
            $this->transactions->create([
                'payment_id' => $refund->payment_id,
                'type' => TransactionType::Refund->value,
                'amount' => $result->succeeded ? -$refund->amount : 0,
                'currency' => $refund->currency,
                'gateway_ref' => $result->reference,
            ]);

            return $refund;
        });
    }

    /**
     * @param  array{payment_ref: string, amount: int, currency: string, reason?: string|null}  $payload
     */
    private function persist(string $idempotencyKey, string $requestHash, array $payload, string $gateway, string $orderId): Refund
    {
        $refund = $this->refunds->create([
            'payment_id' => $payload['payment_ref'],
            'order_id' => $orderId,
            'gateway' => $gateway,
            'status' => RefundStatus::Pending->value,
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'reason' => $payload['reason'] ?? null,
        ]);

        $this->idempotencyKeys->create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'response_payload' => ['refund_id' => $refund->id],
            'status' => 'completed',
        ]);

        return $refund;
    }

    /**
     * Stable hash of the money-relevant request fields, so the same key with a different body is
     * detectable as a conflict.
     *
     * @param  array{payment_ref: string, amount: int, currency: string, reason?: string|null}  $payload
     */
    private function requestHash(array $payload): string
    {
        // Only money-relevant fields (mirrors the charge path). `reason` is a descriptive field — a
        // replay that differs only in `reason` must NOT 409, it must replay the original refund.
        return hash('sha256', (string) json_encode([
            'payment_ref' => $payload['payment_ref'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
        ]));
    }
}
