<?php

namespace App\Repositories\Eloquent;

use App\Models\TicketType;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class TicketTypeRepository implements TicketTypeRepositoryInterface
{
    public function paginateForEvent(string $eventId, int $perPage): LengthAwarePaginator
    {
        return TicketType::query()
            ->where('event_id', $eventId)
            ->orderBy('price')
            ->paginate($perPage);
    }

    public function findManyForCheckout(array $ids): Collection
    {
        return TicketType::query()
            ->with('event')
            ->whereKey($ids)
            ->get();
    }

    public function lockForUpdate(string $id): TicketType
    {
        return TicketType::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function sumQuantityTotalForEvent(string $eventId, ?string $exceptId = null): int
    {
        return (int) TicketType::query()
            ->where('event_id', $eventId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('quantity_total');
    }

    public function find(string $id): ?TicketType
    {
        return TicketType::query()->find($id);
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

    public function incrementSold(string $id, int $by): void
    {
        TicketType::query()->whereKey($id)->increment('quantity_sold', $by);
    }

    public function decrementSold(string $id, int $by): void
    {
        TicketType::query()->whereKey($id)->decrement('quantity_sold', $by);
    }
}
