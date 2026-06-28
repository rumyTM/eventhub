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

    /** Find an order by id, or null if it no longer exists (used by the async charge job). */
    public function find(string $id): ?Order;

    /** Row-lock an order (SELECT … FOR UPDATE) for the webhook settlement; null if it no longer exists. */
    public function lockForUpdate(string $id): ?Order;

    /** Mark an order paid. Caller holds the row lock + has asserted it was pending. */
    public function markPaid(Order $order): void;

    /**
     * Mark the given orders `expired` only if still `pending`. Returns the number updated.
     * Never touches paid/refunded/cancelled orders.
     *
     * @param  list<string>  $orderIds
     */
    public function markPendingExpired(array $orderIds): int;
}
