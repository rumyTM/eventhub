<?php

namespace App\Repositories\Eloquent;

use App\Models\Attendee;
use App\Models\User;
use App\Repositories\Contracts\AttendeeRepositoryInterface;

final class AttendeeRepository implements AttendeeRepositoryInterface
{
    public function createForUser(User $user, array $attributes): Attendee
    {
        return $user->attendee()->create($attributes);
    }
}
