<?php

namespace App\Repositories\Eloquent;

use App\Models\TicketType;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TicketTypeRepository implements TicketTypeRepositoryInterface
{
    public function paginateForEvent(string $eventId, int $perPage): LengthAwarePaginator
    {
        return TicketType::query()
            ->where('event_id', $eventId)
            ->orderBy('price')
            ->paginate($perPage);
    }

    public function sumQuantityTotalForEvent(string $eventId, ?string $exceptId = null): int
    {
        return (int) TicketType::query()
            ->where('event_id', $eventId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('quantity_total');
    }

    public function create(array $attributes): TicketType
    {
        return TicketType::create($attributes);
    }

    public function update(TicketType $ticketType, array $attributes): TicketType
    {
        $ticketType->fill($attributes)->save();

        return $ticketType->refresh();
    }

    public function delete(TicketType $ticketType): void
    {
        $ticketType->delete();
    }
}
