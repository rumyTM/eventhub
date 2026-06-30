<?php

namespace App\Services\Events;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\AttendeeRepositoryInterface;
use App\Repositories\Contracts\EventReminderRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Hourly cron service — dispatches reminder notifications for events starting soon (CLAUDE.md §G).
 *
 * Two reminder windows per event:
 *   `24h` — dispatched when the event starts within the next 24 hours (checked hourly).
 *   `1h`  — dispatched when the event starts within the next 1 hour.
 *
 * Deduplication: `event_reminders` has a unique index on (event_id, type). `markSent` uses
 * firstOrCreate, so re-running (or two overlapping cron workers) is always a no-op for an
 * already-dispatched window.
 */
final class SendEventRemindersService
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly EventReminderRepositoryInterface $reminders,
        private readonly AttendeeRepositoryInterface $attendees,
        private readonly NotificationPublisherContract $notifications,
    ) {}

    /**
     * @return array{sent_24h: int, sent_1h: int}
     */
    public function handle(): array
    {
        $now = Carbon::now();
        $sent24h = $this->dispatchWindow($now, $now->copy()->addHours(24), '24h');
        $sent1h = $this->dispatchWindow($now, $now->copy()->addHours(1), '1h');

        LogHelper::logEntry(LogHelper::LOG_INFO, 'SendEventReminders finished', [
            'sent_24h' => $sent24h,
            'sent_1h' => $sent1h,
        ]);

        return ['sent_24h' => $sent24h, 'sent_1h' => $sent1h];
    }

    private function dispatchWindow(Carbon $from, Carbon $to, string $type): int
    {
        $eventsInWindow = $this->events->findStartingInWindow($from, $to);
        $dispatched = 0;

        foreach ($eventsInWindow as $event) {
            if ($this->reminders->hasBeenSent($event->id, $type)) {
                continue;
            }

            // Mark sent BEFORE enqueuing — if publishing fails for one attendee we still won't
            // re-blast everyone on the next cron tick; notification-service handles per-recipient retry.
            $this->reminders->markSent($event->id, $type);

            $ticketHolders = $this->attendees->findWithTicketsForEvent($event->id);

            foreach ($ticketHolders as $attendee) {
                $user = $attendee->user;
                if ($user === null) {
                    continue;
                }

                $this->notifications->publishEmail(
                    type: 'event.reminder',
                    recipient: ['email' => $user->email, 'name' => $user->name],
                    data: [
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'starts_at' => $event->starts_at?->toIso8601String(),
                        'reminder_type' => $type,
                    ],
                    idempotencyKey: "reminder:{$event->id}:{$type}:{$attendee->id}",
                );
            }

            $dispatched++;
        }

        return $dispatched;
    }
}
