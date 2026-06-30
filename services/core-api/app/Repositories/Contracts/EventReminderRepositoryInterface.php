<?php

namespace App\Repositories\Contracts;

use App\Models\EventReminder;

interface EventReminderRepositoryInterface
{
    /**
     * Whether a reminder of the given type has already been dispatched for this event.
     * Guards against re-sending (unique constraint dedupes at DB level; this is the fast-path check).
     */
    public function hasBeenSent(string $eventId, string $type): bool;

    /**
     * Record that a reminder batch was dispatched for this event+type. The `created_at` timestamp
     * doubles as `sent_at` — callers treat this as an idempotent insert (unique on event_id+type).
     */
    public function markSent(string $eventId, string $type): EventReminder;
}
