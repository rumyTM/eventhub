<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    /**
     * Get the charge-attempt row for this idempotency key, creating it (pending) if absent. The
     * unique `idempotency_key` makes a job retry reuse the same row — never a second charge (ADR-09/17).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function firstOrCreateForAttempt(string $idempotencyKey, array $attributes): Payment;

    /** Persist the gateway reference returned by the payment-service (never card data). */
    public function recordExternalRef(Payment $payment, string $externalRef): Payment;
}
