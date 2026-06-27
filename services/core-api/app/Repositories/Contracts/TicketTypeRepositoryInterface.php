<?php

namespace App\Repositories\Contracts;

use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TicketTypeRepositoryInterface
{
    public function paginateForEvent(string $eventId, int $perPage): LengthAwarePaginator;

    /**
     * Sum of quantity_total across an event's ticket types, optionally excluding one row (for updates).
     * Used to enforce SUM(quantity_total) <= event.capacity inside a transaction.
     */
    public function sumQuantityTotalForEvent(string $eventId, ?string $exceptId = null): int;

    public function create(array $attributes): TicketType;

    public function update(TicketType $ticketType, array $attributes): TicketType;

    public function delete(TicketType $ticketType): void;
}
