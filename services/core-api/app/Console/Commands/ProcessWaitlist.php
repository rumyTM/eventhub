<?php

namespace App\Console\Commands;

use App\Services\Waitlist\ProcessWaitlistService;
use Illuminate\Console\Command;

/**
 * Waitlist processing — runs every 5 min (after ReleaseExpiredHolds frees holds) to offer
 * newly-available inventory to the next attendee in each waitlist queue.
 */
class ProcessWaitlist extends Command
{
    protected $signature = 'waitlist:process';

    protected $description = 'Offer freed ticket inventory to the next waitlisted attendees.';

    public function handle(ProcessWaitlistService $service): int
    {
        $result = $service->handle();

        $this->info(sprintf(
            'Waitlist processed: %d offer(s) sent, %d unclaimed offer(s) expired.',
            $result['offered'],
            $result['expired_unclaimed'],
        ));

        return self::SUCCESS;
    }
}
