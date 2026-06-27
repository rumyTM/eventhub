<?php

namespace App\Services\Events;

use App\Exceptions\TicketTypes\EventCapacityExceededException;
use App\Exceptions\TicketTypes\QuantityBelowSoldException;
use App\Models\Event;
use App\Models\TicketType;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class TicketTypeService
{
    public function __construct(
        private readonly TicketTypeRepositoryInterface $ticketTypes,
        private readonly EventRepositoryInterface $events,
    ) {}

    public function list(Event $event, int $perPage): LengthAwarePaginator
    {
        return $this->ticketTypes->paginateForEvent($event->id, $perPage);
    }

    /**
     * Create a ticket type, enforcing SUM(quantity_total) <= event.capacity. The event row is locked
     * (FOR UPDATE) and the sum recomputed inside the transaction so concurrent creates can't oversell capacity.
     */
    public function create(Event $event, array $data): TicketType
    {
        return DB::transaction(function () use ($event, $data): TicketType {
            $locked = $this->events->lockForUpdate($event->id);

            $this->assertWithinCapacity($locked, (int) $data['quantity_total']);

            $data['event_id'] = $locked->id;

            return $this->ticketTypes->create($data);
        });
    }

    /**
     * Update a ticket type. Re-checks the capacity invariant (excluding this row) and forbids dropping
     * quantity_total below the number already sold — both inside a transaction under the event row lock.
     */
    public function update(TicketType $ticketType, array $data): TicketType
    {
        return DB::transaction(function () use ($ticketType, $data): TicketType {
            $locked = $this->events->lockForUpdate($ticketType->event_id);

            if (array_key_exists('quantity_total', $data)) {
                $newTotal = (int) $data['quantity_total'];

                if ($newTotal < $ticketType->quantity_sold) {
                    throw new QuantityBelowSoldException;
                }

                $this->assertWithinCapacity($locked, $newTotal, exceptId: $ticketType->id);
            }

            return $this->ticketTypes->update($ticketType, $data);
        });
    }

    public function delete(TicketType $ticketType): void
    {
        $this->ticketTypes->delete($ticketType);
    }

    private function assertWithinCapacity(Event $event, int $incomingTotal, ?string $exceptId = null): void
    {
        $existing = $this->ticketTypes->sumQuantityTotalForEvent($event->id, $exceptId);

        if ($existing + $incomingTotal > $event->capacity) {
            throw new EventCapacityExceededException;
        }
    }
}
