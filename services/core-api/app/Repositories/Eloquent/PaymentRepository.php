<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PaymentRepository implements PaymentRepositoryInterface
{
    public function firstOrCreateForAttempt(string $idempotencyKey, array $attributes): Payment
    {
        try {
            return Payment::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                $attributes,
            );
        } catch (UniqueConstraintViolationException) {
            // A concurrent dispatch inserted the row between our read and write — the unique
            // idempotency_key guarantees no second charge; resolve the race as a replay, not a 500
            // (mirrors CheckoutService's idempotency handling).
            return Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    public function recordExternalRef(Payment $payment, string $externalRef): Payment
    {
        // Targeted update of ONLY external_ref — never a full save() of a possibly-stale model, so a
        // status the webhook may have already written (e.g. succeeded) is never clobbered back.
        Payment::query()->whereKey($payment->id)->update(['external_ref' => $externalRef]);

        return $payment->refresh();
    }
}
