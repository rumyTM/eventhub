<?php

namespace App\Repositories\Contracts;

use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TicketTypeRepositoryInterface
{
    public function paginateForEvent(string $eventId, int $perPage): LengthAwarePaginator;

    /**
     * Load the given ticket types (non-trashed) with their event, for checkout validation.
     *
     * @param  list<string>  $ids
     * @return Collection<int, TicketType>
     */
    public function findManyForCheckout(array $ids): Collection;

    /** Re-read a ticket type under a row lock (FOR UPDATE) — call inside a transaction (ADR-07). */
    public function lockForUpdate(string $id): TicketType;

    /**
     * Sum of quantity_total across an event's ticket types, optionally excluding one row (for updates).
     * Used to enforce SUM(quantity_total) <= event.capacity inside a transaction.
     */
    public function sumQuantityTotalForEvent(string $eventId, ?string $exceptId = null): int;

    /** Find a ticket type by ID, or null if not found (non-trashed only). */
    public function find(string $id): ?TicketType;

    public function create(array $attributes): TicketType;

    public function update(TicketType $ticketType, array $attributes): TicketType;

    public function delete(TicketType $ticketType): void;

    /**
     * Atomically move the denormalized sold counter on payment success: a single
     * `UPDATE … SET quantity_sold = quantity_sold + N`. Never called at checkout (holds reserve there).
     */
    public function incrementSold(string $id, int $by): void;

    /**
     * Atomically return inventory on full-order refund: a single
     * `UPDATE … SET quantity_sold = quantity_sold - N`, floored at 0 to guard against data races.
     */
    public function decrementSold(string $id, int $by): void;
}
