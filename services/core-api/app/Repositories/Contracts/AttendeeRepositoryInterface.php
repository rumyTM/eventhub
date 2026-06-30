<?php

namespace App\Repositories\Contracts;

use App\Models\Attendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface AttendeeRepositoryInterface
{
    /** Create the attendee profile owned by the given user. */
    public function createForUser(User $user, array $attributes): Attendee;

    /**
     * Attendees who hold at least one issued ticket for the given event, with their user
     * relation eager-loaded so the caller can read email/name without extra queries.
     *
     * @return Collection<int, Attendee>
     */
    public function findWithTicketsForEvent(string $eventId): Collection;
}
