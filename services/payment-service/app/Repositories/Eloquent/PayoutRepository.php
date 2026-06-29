<?php

namespace App\Repositories\Eloquent;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Repositories\Contracts\PayoutRepositoryInterface;

final class PayoutRepository implements PayoutRepositoryInterface
{
    public function create(array $attributes): Payout
    {
        return Payout::create($attributes);
    }

    public function findOrFail(string $id): Payout
    {
        return Payout::query()->findOrFail($id);
    }

    public function findForUpdate(string $id): Payout
    {
        return Payout::query()->lockForUpdate()->findOrFail($id);
    }

    public function markResolved(Payout $payout, PayoutStatus $status, string $gatewayRef): Payout
    {
        $payout->forceFill([
            'status' => $status->value,
            'gateway_ref' => $gatewayRef,
        ])->save();

        return $payout;
    }
}
