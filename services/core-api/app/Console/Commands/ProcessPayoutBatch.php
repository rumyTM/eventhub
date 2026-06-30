<?php

namespace App\Console\Commands;

use App\Services\Payouts\ProcessPayoutBatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily payout batch command. batchId = today (YYYY-MM-DD) so re-running on the same day is
 * a no-op (idempotency key `payout:{vendorId}:{batchId}` deduplicates at the DB level).
 */
class ProcessPayoutBatch extends Command
{
    protected $signature = 'payouts:process-batch {--date= : Override batch date (YYYY-MM-DD); defaults to today}';

    protected $description = 'Build pending payout records for all eligible vendors (daily batch; idempotent).';

    public function handle(ProcessPayoutBatchService $service): int
    {
        $batchId = $this->option('date') ?? Carbon::today()->toDateString();

        $result = $service->handle($batchId);

        $this->info(sprintf(
            'Payout batch %s complete: %d payout(s) built.',
            $result['batch_id'],
            $result['built'],
        ));

        return self::SUCCESS;
    }
}
