<?php

namespace App\Repositories\Eloquent;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;

final class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $attributes): Order
    {
        return Order::create($attributes);
    }

    public function addItem(Order $order, array $attributes): OrderItem
    {
        return $order->items()->create($attributes);
    }

    public function findWithLines(string $id): Order
    {
        return Order::query()->with(['items', 'holds'])->findOrFail($id);
    }

    public function find(string $id): ?Order
    {
        return Order::query()->find($id);
    }

    public function lockForUpdate(string $id): ?Order
    {
        return Order::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function markPaid(Order $order): void
    {
        $order->update(['status' => OrderStatus::Paid->value]);
    }

    public function markPendingExpired(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        return Order::query()
            ->whereKey($orderIds)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Expired]);
    }
}
