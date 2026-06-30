<?php

namespace App\Services\Waitlist;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\TicketHoldRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;

/**
 * Cron service — notifies the next waitlisted attendee when inventory frees (CLAUDE.md §G).
 *
 * Run every 5 min (after ReleaseExpiredHolds so freed holds are visible). For each ticket type
 * with waiting entries, checks real-time available inventory and offers the next position.
 *
 * Idempotency: an `offered` entry keeps its offer until claim_expires_at; re-running before
 * expiry produces no second offer for the same position.
 */
final class ProcessWaitlistService
{
    public function __construct(
        private readonly WaitlistRepositoryInterface $waitlist,
        private readonly TicketTypeRepositoryInterface $ticketTypes,
        private readonly TicketHoldRepositoryInterface $holds,
        private readonly NotificationPublisherContract $notifications,
    ) {}

    /**
     * @return array{offered: int, expired_unclaimed: int}
     */
    public function handle(): array
    {
        $expiredUnclaimed = $this->waitlist->expireUnclaimed();

        $ticketTypeIds = $this->waitlist->ticketTypeIdsWithWaiting();
        $offered = 0;

        foreach ($ticketTypeIds as $ticketTypeId) {
            $ticketType = $this->ticketTypes->find($ticketTypeId);
            if ($ticketType === null) {
                continue;
            }

            $activeHolds = $this->holds->sumActiveQuantityForTicketType($ticketTypeId);
            $available = $ticketType->quantity_total - $ticketType->quantity_sold - $activeHolds;

            if ($available <= 0) {
                continue;
            }

            $entry = $this->waitlist->nextWaitingForTicketType($ticketTypeId);
            if ($entry === null) {
                continue;
            }

            $this->waitlist->markOffered($entry);

            // Load the attendee's user for the notification recipient.
            $attendee = $entry->attendee()->with('user')->first();
            $user = $attendee?->user;

            if ($user !== null) {
                $this->notifications->publishEmail(
                    type: 'waitlist.offered',
                    recipient: ['email' => $user->email, 'name' => $user->name],
                    data: [
                        'event_id' => $entry->event_id,
                        'ticket_type_id' => $ticketTypeId,
                        'claim_expires_at' => $entry->claim_expires_at?->toIso8601String(),
                    ],
                    idempotencyKey: "waitlist-offer:{$entry->id}",
                );
            }

            $offered++;
        }

        LogHelper::logEntry(LogHelper::LOG_INFO, 'ProcessWaitlist finished', [
            'offered' => $offered,
            'expired_unclaimed' => $expiredUnclaimed,
        ]);

        return ['offered' => $offered, 'expired_unclaimed' => $expiredUnclaimed];
    }
}
