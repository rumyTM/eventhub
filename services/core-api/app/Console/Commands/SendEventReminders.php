<?php

namespace App\Console\Commands;

use App\Services\Events\SendEventRemindersService;
use Illuminate\Console\Command;

/**
 * Hourly reminder dispatch. Deduped via event_reminders (unique event_id+type), so running
 * multiple times per hour for the same event window is a safe no-op.
 */
class SendEventReminders extends Command
{
    protected $signature = 'reminders:send-event';

    protected $description = 'Enqueue event reminder notifications for events starting within 1h and 24h.';

    public function handle(SendEventRemindersService $service): int
    {
        $result = $service->handle();

        $this->info(sprintf(
            'Event reminders dispatched — 24h window: %d event(s), 1h window: %d event(s).',
            $result['sent_24h'],
            $result['sent_1h'],
        ));

        return self::SUCCESS;
    }
}
