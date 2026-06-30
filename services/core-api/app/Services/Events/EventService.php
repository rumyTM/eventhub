<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Exceptions\Events\CapacityBelowAllocatedException;
use App\Exceptions\Events\InvalidEventTransitionException;
use App\Exceptions\Events\VendorNotVerifiedException;
use App\Jobs\RefundEventOrdersJob;
use App\Models\Event;
use App\Models\User;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly TicketTypeRepositoryInterface $ticketTypes,
    ) {}

    /**
     * Listing scope by audience: admin → all; authenticated vendor → own events (all statuses);
     * everyone else (guests/attendees) → the public published catalog.
     */
    public function list(?User $user, int $perPage): LengthAwarePaginator
    {
        if ($user?->isAdmin()) {
            return $this->events->paginateAll($perPage);
        }

        if ($user?->isVendor() && $user->vendor !== null) {
            return $this->events->paginateForVendor($user->vendor->id, $perPage);
        }

        return $this->events->paginatePublished($perPage);
    }

    /** Create a draft event owned by the acting vendor. */
    public function create(User $vendorUser, array $data): Event
    {
        $data['vendor_id'] = $vendorUser->vendor->id;
        $data['status'] = EventStatus::Draft->value;

        return $this->events->create($data);
    }

    /**
     * Apply a partial update. If `status` changes, the transition must be legal; publishing additionally
     * requires the owning vendor's KYC to be verified. If `capacity` changes, it may not drop below the
     * sum of the event's ticket-type allocations — checked under the event row lock.
     */
    public function update(Event $event, array $data): Event
    {
        $previousStatus = $event->status;

        if (array_key_exists('status', $data)) {
            $this->guardStatusTransition($event, EventStatus::from($data['status']));
        }

        $updated = array_key_exists('capacity', $data)
            ? DB::transaction(function () use ($event, $data): Event {
                $locked = $this->events->lockForUpdate($event->id);
                $allocated = $this->ticketTypes->sumQuantityTotalForEvent($locked->id);

                if ((int) $data['capacity'] < $allocated) {
                    throw new CapacityBelowAllocatedException;
                }

                return $this->events->update($event, $data);
            })
            : $this->events->update($event, $data);

        if ($this->cancelledJustNow($previousStatus, $updated->status)) {
            RefundEventOrdersJob::dispatch($updated->id)->afterCommit();
        }

        return $updated;
    }

    /** True only on the transition INTO cancelled — never re-fires on a repeat no-op update. */
    private function cancelledJustNow(EventStatus $previous, EventStatus $current): bool
    {
        return $current === EventStatus::Cancelled && $previous !== EventStatus::Cancelled;
    }

    public function delete(Event $event): void
    {
        $this->events->delete($event);
    }

    private function guardStatusTransition(Event $event, EventStatus $target): void
    {
        if ($target === $event->status) {
            return; // no-op
        }

        if (! $event->status->canTransitionTo($target)) {
            throw new InvalidEventTransitionException;
        }

        if ($target === EventStatus::Published && ! $event->vendor->kyc_status->canTransact()) {
            throw new VendorNotVerifiedException;
        }
    }
}
