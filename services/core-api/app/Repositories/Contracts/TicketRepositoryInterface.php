<?php

namespace App\Repositories\Contracts;

use App\Models\Ticket;

interface TicketRepositoryInterface
{
    /**
     * Issue one ticket. Called once per held unit on payment success.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Ticket;

    /**
     * Void an order's still-valid tickets on a full refund: flip `valid` → `refunded`. A `checked_in`
     * ticket was already used and is left untouched. Returns the number voided (idempotent: a re-run
     * finds no `valid` tickets and voids none).
     */
    public function voidValidForOrder(string $orderId): int;

    /**
     * True if any ticket on the given order items has already been checked in. A checked-in ticket
     * represents a consumed seat — refunding it would incorrectly return that inventory (ADR-37).
     *
     * @param  list<string>  $orderItemIds
     */
    public function hasCheckedInForOrderItems(array $orderItemIds): bool;
}
