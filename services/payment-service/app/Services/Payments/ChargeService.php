<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\IdempotencyKeyConflictException;
use App\Models\Payment;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a charge for an order. The charge is persisted `pending` and idempotency is DB-backed
 * (ADR-09): the Idempotency-Key maps to exactly one Payment. A replay with the same body returns
 * that same Payment with NO second charge; a replay with a different body is a 409 conflict. The
 * gateway's success/failure decision and the callback to core-api happen when the charge resolves
 * (a later slice) — here we only reserve the attempt idempotently.
 *
 * Mirrors core-api's CheckoutService idempotency mechanics so both ends of the money path behave
 * identically under retries.
 */
final class ChargeService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys,
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
