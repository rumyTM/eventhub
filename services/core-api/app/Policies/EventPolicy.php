<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

/**
 * Ownership boundary for events. A vendor may only read/mutate events reachable through its own
 * vendor_id; admins may do anything; the public may only view live (published/ongoing) events.
 * Guests are allowed into the `view` check (nullable $user) so the public catalog works.
 */
class EventPolicy
{
    /** Live events are world-readable; otherwise only the owner or an admin may view. */
    public function view(?User $user, Event $event): bool
    {
        if (in_array($event->status, [EventStatus::Published, EventStatus::Ongoing], true)) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $this->owns($user, $event));
    }

    /** Only vendors create events (the route also gates role:vendor). */
    public function create(User $user): bool
    {
        return $user->isVendor();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isAdmin() || $this->owns($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->isAdmin() || $this->owns($user, $event);
    }

    private function owns(User $user, Event $event): bool
    {
        return $user->isVendor()
            && $user->vendor !== null
            && $user->vendor->id === $event->vendor_id;
    }
}
