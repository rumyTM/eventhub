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

    public function find(string $id): ?Refund
    {
        return Refund::query()->find($id);
    }

    public function findOpenForOrder(string $orderId): ?Refund
    {
        return Refund::query()
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Pending->value])
            ->whereHas('payment', fn ($q) => $q->where('order_id', $orderId))
            ->first();
    }

    public function refundedTotalForOrder(string $orderId): int
    {
        return (int) Refund::query()
            ->where('status', '!=', RefundStatus::Failed->value)
            ->whereHas('payment', fn ($q) => $q->where('order_id', $orderId))
            ->sum('amount');
    }
}
