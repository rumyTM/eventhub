<?php

namespace App\Console\Commands;

use App\Services\Orders\ReleaseExpiredHoldsService;
use Illuminate\Console\Command;

/**
 * Cron entry point for the hold-expiry safety net (scheduled every 5 min in routes/console.php).
 * Thin wrapper — all logic lives in ReleaseExpiredHoldsService so it is unit-testable without the console.
 */
class ReleaseExpiredHolds extends Command
{
    protected $signature = 'holds:release-expired';

    protected $description = 'Release expired ticket holds and expire their pending orders (idempotent safety net).';

    public function handle(ReleaseExpiredHoldsService $service): int
    {
        $result = $service->handle();

        $this->info(sprintf(
            'Released holds for %d order(s); expired %d order(s).',
            $result['released_orders'],
            $result['expired_orders'],
        ));

        return self::SUCCESS;
    }
}
