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

    public function findForUpdate(string $id): ?Refund
    {
        return Refund::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function lockOpenForOrder(string $orderId): ?Refund
    {
        return Refund::query()
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Pending->value])
            ->whereHas('payment', fn ($q) => $q->where('order_id', $orderId))
            ->lockForUpdate()
            ->first();
    }

    public function markPending(Refund $refund): void
    {
        $refund->update(['status' => RefundStatus::Pending->value]);
    }

    public function markCompleted(Refund $refund): void
    {
        $refund->update(['status' => RefundStatus::Completed->value]);
    }

    public function markFailed(Refund $refund): void
    {
        $refund->update(['status' => RefundStatus::Failed->value]);
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

    public function completedRefundedTotalForOrder(string $orderId): int
    {
        return (int) Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereHas('payment', fn ($q) => $q->where('order_id', $orderId))
            ->sum('amount');
    }
}
