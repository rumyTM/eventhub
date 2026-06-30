<?php

namespace App\Repositories\Contracts;

use App\Models\WaitlistEntry;

interface WaitlistRepositoryInterface
{
    /**
     * Distinct ticket_type_id values that have at least one `waiting` waitlist entry.
     * Used as the outer loop in ProcessWaitlistService.
     *
     * @return list<string>
     */
    public function ticketTypeIdsWithWaiting(): array;

    /**
     * The next waiting entry for a ticket type (lowest position, status=waiting). Null if none.
     */
    public function nextWaitingForTicketType(string $ticketTypeId): ?WaitlistEntry;

    /**
     * Flip a `waiting` entry to `offered`: stamp offered_at = now, claim_expires_at = now+30 min.
     */
    public function markOffered(WaitlistEntry $entry): WaitlistEntry;

    /**
     * Expire all `offered` entries whose claim_expires_at is in the past. Returns count expired.
     * Safe to re-run — already-expired entries are skipped.
     */
    public function expireUnclaimed(): int;
}
