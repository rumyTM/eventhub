<?php

namespace App\Repositories\Eloquent;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Repositories\Contracts\RefundRepositoryInterface;

final class RefundRepository implements RefundRepositoryInterface
{
    public function create(array $attributes): Refund
    {
        return Refund::create($attributes);
    }

    public function findOrFail(string $id): Refund
    {
        return Refund::query()->findOrFail($id);
    }

    public function findForUpdate(string $id): Refund
    {
        return Refund::query()->lockForUpdate()->findOrFail($id);
    }

    public function markResolved(Refund $refund, RefundStatus $status, string $gatewayRef): Refund
    {
        $refund->forceFill([
            'status' => $status->value,
            'gateway_ref' => $gatewayRef,
        ])->save();

        return $refund;
    }

    public function sumNonFailedForPayment(string $paymentId): int
    {
        return (int) Refund::query()
            ->where('payment_id', $paymentId)
            ->where('status', '!=', RefundStatus::Failed->value)
            ->sum('amount');
    }
}
