<?php

namespace App\Repositories\Eloquent;

use App\Models\EventReminder;
use App\Repositories\Contracts\EventReminderRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EventReminderRepository implements EventReminderRepositoryInterface
{
    public function hasBeenSent(string $eventId, string $type): bool
    {
        return EventReminder::query()
            ->where('event_id', $eventId)
            ->where('type', $type)
            ->exists();
    }

    public function markSent(string $eventId, string $type): EventReminder
    {
        // INSERT IGNORE equivalent: create or silently skip the unique (event_id, type) duplicate.
        // We use firstOrCreate so a concurrent cron run on the same event+type gets the existing row.
        return DB::transaction(function () use ($eventId, $type): EventReminder {
            return EventReminder::firstOrCreate(
                ['event_id' => $eventId, 'type' => $type],
                ['sent_at' => now()],
            );
        });
    }
}
