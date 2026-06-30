<?php

namespace App\Console\Commands;

use App\Services\Reports\GenerateSalesReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily sales report generation. Re-running for the same date is safe (updateOrCreate per vendor;
 * platform-wide null row deduped at application level).
 */
class GenerateSalesReport extends Command
{
    protected $signature = 'reports:generate-sales {--date= : Report date (YYYY-MM-DD); defaults to yesterday}';

    protected $description = 'Aggregate daily sales from ledger entries into the sales_reports table.';

    public function handle(GenerateSalesReportService $service): int
    {
        // Default to yesterday so the full day's ledger is available when the daily cron fires.
        $reportDate = $this->option('date') ?? Carbon::yesterday()->toDateString();

        $result = $service->handle($reportDate);

        $this->info(sprintf(
            'Sales report for %s generated: %d vendor row(s) + 1 platform-wide row.',
            $result['report_date'],
            $result['vendors'],
        ));

        return self::SUCCESS;
    }
}
