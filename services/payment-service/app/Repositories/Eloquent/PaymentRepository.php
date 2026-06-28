<?php

namespace App\Repositories\Eloquent;

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
}
