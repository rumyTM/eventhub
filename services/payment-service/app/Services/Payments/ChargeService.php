<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Exceptions\Payments\IdempotencyKeyConflictException;
use App\Gateways\GatewayManager;
use App\Jobs\DeliverChargeResultJob;
use App\Models\Payment;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns a charge end-to-end across two phases (CLAUDE.md §C/§E):
 *
 *   1. createCharge()  — reserve the `pending` Payment idempotently (ADR-09). The Idempotency-Key
 *      maps to exactly one Payment; same key+body replays it, same key+different body is a 409.
 *   2. resolve()       — let the configured gateway simulator decide success/failure, persist the
 *      terminal status, and append one row to the `transactions` ledger. Runs inside the queued
 *      DeliverChargeResultJob so the HTTP request returns `pending` immediately and the real result
 *      arrives via the signed webhook to core-api.
 *
 * resolve() is idempotent: a row-locked re-check short-circuits a payment that is already terminal,
 * so a job retry (or a duplicate dispatch) never rolls the gateway twice or writes a second ledger
 * row — the money guarantee that mirrors core-api's webhook-replay handling.
 */
final class ChargeService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly GatewayManager $gateways,
    ) {}

    /**
     * @param  array{order_id: string, gateway: string, amount: int, currency: string}  $payload
     */
    public function createCharge(string $idempotencyKey, array $payload): Payment
    {
        $requestHash = $this->requestHash($payload);

        // --- Idempotency replay / conflict (ADR-09) ---
        $seen = $this->idempotencyKeys->findByKey($idempotencyKey);
        if ($seen !== null) {
            if (! hash_equals($seen->request_hash, $requestHash)) {
                throw new IdempotencyKeyConflictException;
            }

            return $this->payments->findOrFail($seen->response_payload['payment_id']);
        }

        try {
            return DB::transaction(fn (): Payment => $this->persist($idempotencyKey, $requestHash, $payload));
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request used the same key first — resolve as a replay rather than 500.
            $seen = $this->idempotencyKeys->findByKey($idempotencyKey);

            if ($seen !== null) {
                return $this->payments->findOrFail($seen->response_payload['payment_id']);
            }

            throw $e;
        }
    }

    /**
     * Queue the asynchronous resolution + signed webhook, delayed by the gateway's configured
     * processing time so we model a gateway that does not answer instantly. A payment that is
     * already terminal (an idempotent replay) needs no further work. The callback URL is read from
     * config — a trusted, fixed core-api endpoint — never accepted from the request (SSRF guard).
     */
    public function scheduleResolution(Payment $payment): void
    {
        if ($payment->status->isTerminal()) {
            return;
        }

        $delaySeconds = $this->gateways->make($payment->gateway->value)->delaySeconds();

        DeliverChargeResultJob::dispatch($payment->id)
            ->delay(Carbon::now()->addSeconds($delaySeconds));
    }

    /**
     * Resolve a pending charge: ask the gateway for the outcome, persist the terminal status, and
     * append the ledger row. Returns the (possibly already-resolved) Payment.
     *
     * The gateway call runs OUTSIDE the transaction so the `payments` row lock is held only for the
     * write — never for the duration of a (potentially slow, real) gateway response. The lock plus
     * the terminal-status re-check inside the transaction is the authoritative single-write guard
     * (mirrors ADR-07's "DB row lock is the correctness guard"): a job retry or duplicate dispatch
     * re-enters, finds the payment already terminal, and short-circuits — never a second gateway
     * roll, never a second ledger row. A redundant gateway roll by a racing caller is harmless: its
     * result is discarded when its transaction sees the terminal status.
     */
    public function resolve(string $paymentId): Payment
    {
        // 1. Optimistic read (no lock): nothing to do if already resolved.
        $payment = $this->payments->findOrFail($paymentId);
        if ($payment->status->isTerminal()) {
            return $payment;
        }

        // 2. Gateway decision OUTSIDE the transaction (no row lock held while it runs).
        $result = $this->gateways
            ->make($payment->gateway->value)
            ->charge($payment->amount, $payment->currency, $payment->order_id);

        // 3. Lock, re-check (authoritative guard), persist + append the ledger row atomically.
        return DB::transaction(function () use ($paymentId, $result): Payment {
            $payment = $this->payments->findForUpdate($paymentId);

            // A concurrent resolve already committed — discard this roll, never double-write.
            if ($payment->status->isTerminal()) {
                return $payment;
            }

            $payment = $this->payments->markResolved($payment, $result->status(), $result->reference);

            // Append-only ledger: a successful charge is positive money-in; a failed charge moved
            // nothing, so it is recorded as 0 — SUM(amount) stays an honest net position.
            $this->transactions->create([
                'payment_id' => $payment->id,
                'type' => TransactionType::Charge->value,
                'amount' => $result->succeeded ? $payment->amount : 0,
                'currency' => $payment->currency,
                'gateway_ref' => $result->reference,
            ]);

            return $payment;
        });
    }

    /**
     * @param  array{order_id: string, gateway: string, amount: int, currency: string}  $payload
     */
    private function persist(string $idempotencyKey, string $requestHash, array $payload): Payment
    {
        $payment = $this->payments->create([
            'order_id' => $payload['order_id'],
            'gateway' => $payload['gateway'],
            'status' => PaymentStatus::Pending->value,
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
        ]);

        $this->idempotencyKeys->create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'response_payload' => ['payment_id' => $payment->id],
            'status' => 'completed',
        ]);

        return $payment;
    }

    /**
     * Stable hash of the money-relevant request fields, so the same key with a different body is
     * detectable as a conflict.
     *
     * @param  array{order_id: string, gateway: string, amount: int, currency: string}  $payload
     */
    private function requestHash(array $payload): string
    {
        return hash('sha256', (string) json_encode([
            'order_id' => $payload['order_id'],
            'gateway' => $payload['gateway'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
        ]));
    }
}
