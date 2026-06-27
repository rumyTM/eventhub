<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\OrderItem;

interface OrderRepositoryInterface
{
    public function create(array $attributes): Order;

    public function addItem(Order $order, array $attributes): OrderItem;

    /** Load an order with its items and holds (for the response / replay). */
    public function findWithLines(string $id): Order;

    /**
     * Mark the given orders `expired` only if still `pending`. Returns the number updated.
     * Never touches paid/refunded/cancelled orders.
     *
     * @param  list<string>  $orderIds
     */
    public function markPendingExpired(array $orderIds): int;
}
