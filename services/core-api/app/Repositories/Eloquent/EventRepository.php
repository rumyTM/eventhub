<?php

namespace App\Repositories\Eloquent;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class EventRepository implements EventRepositoryInterface
{
    public function paginatePublished(int $perPage): LengthAwarePaginator
    {
        return Event::query()
            ->where('status', EventStatus::Published)
            ->orderBy('starts_at')
            ->paginate($perPage);
    }

    public function paginateForVendor(string $vendorId, int $perPage): LengthAwarePaginator
    {
        return Event::query()
            ->where('vendor_id', $vendorId)
            ->with('ticketTypes')
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function paginateAll(int $perPage): LengthAwarePaginator
    {
        return Event::query()
            ->with('ticketTypes')
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }

    public function update(Event $event, array $attributes): Event
    {
        $event->fill($attributes)->save();

        return $event->refresh();
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }

    public function lockForUpdate(string $id): Event
    {
        return Event::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function findStartingInWindow(Carbon $from, Carbon $to): Collection
    {
        return Event::query()
            ->whereIn('status', [EventStatus::Published->value, EventStatus::Ongoing->value])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();
    }
}
