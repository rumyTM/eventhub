<?php

namespace App\Repositories\Eloquent;

use App\Enums\TicketStatus;
use App\Models\Attendee;
use App\Models\User;
use App\Repositories\Contracts\AttendeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class AttendeeRepository implements AttendeeRepositoryInterface
{
    public function createForUser(User $user, array $attributes): Attendee
    {
        return $user->attendee()->create($attributes);
    }

    public function findWithTicketsForEvent(string $eventId): Collection
    {
        return Attendee::query()
            ->with('user')
            ->whereHas('orders', function ($q) use ($eventId): void {
                $q->whereHas('tickets', function ($q) use ($eventId): void {
                    $q->where('status', TicketStatus::Issued)
                        ->whereHas('ticketType', fn ($q) => $q->where('event_id', $eventId));
                });
            })
            ->get();
    }
}
