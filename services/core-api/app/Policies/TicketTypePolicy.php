<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;

/**
 * Ticket-type authorization is derived from the parent event's ownership. Reads follow the event's
 * visibility; writes require owning the event (or admin).
 */
class TicketTypePolicy
{
    /** Visible if the parent event is live, or to the owner/admin. */
    public function view(?User $user, TicketType $ticketType): bool
    {
        $event = $ticketType->event;

        if (in_array($event->status, [EventStatus::Published, EventStatus::Ongoing], true)) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $this->ownsEvent($user, $event));
    }

    /** Create is authorized against the parent event. */
    public function create(User $user, Event $event): bool
    {
        return $user->isAdmin() || $this->ownsEvent($user, $event);
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin() || $this->ownsEvent($user, $ticketType->event);
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin() || $this->ownsEvent($user, $ticketType->event);
    }

    private function ownsEvent(User $user, Event $event): bool
    {
        return $user->isVendor()
            && $user->vendor !== null
            && $user->vendor->id === $event->vendor_id;
    }
}
