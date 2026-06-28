<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function findOrFail(string $id): Payment
    {
        return Payment::query()->findOrFail($id);
    }

    public function findForUpdate(string $id): Payment
    {
        return Payment::query()->lockForUpdate()->findOrFail($id);
    }

    public function markResolved(Payment $payment, PaymentStatus $status, string $gatewayRef): Payment
    {
        $payment->forceFill([
            'status' => $status->value,
            'gateway_ref' => $gatewayRef,
        ])->save();

        return $payment;
    }
}
