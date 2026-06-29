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
}
