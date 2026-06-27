<?php

namespace App\Repositories\Contracts;

use App\Models\TicketHold;

interface TicketHoldRepositoryInterface
{
    /**
     * Sum of quantity across ACTIVE, non-expired holds for a ticket type — the inventory reserved right
     * now. Expiry is enforced at read time (status=active AND expires_at > now), so an expired hold stops
     * consuming inventory immediately, independently of the ReleaseExpiredHolds cron.
     */
    public function sumActiveQuantityForTicketType(string $ticketTypeId): int;

    public function create(array $attributes): TicketHold;

    /**
     * Flip active holds whose expires_at <= now to `released`. Returns the distinct order ids that had
     * such holds (so callers can expire the orders). Never touches converted/released holds.
     *
     * @return list<string>
     */
    public function releaseDueActiveHolds(): array;
}
