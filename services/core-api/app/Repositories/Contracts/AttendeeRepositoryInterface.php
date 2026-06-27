<?php

namespace App\Repositories\Contracts;

use App\Models\Attendee;
use App\Models\User;

interface AttendeeRepositoryInterface
{
    /** Create the attendee profile owned by the given user. */
    public function createForUser(User $user, array $attributes): Attendee;
}
