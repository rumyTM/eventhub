<?php

namespace App\Services\Reports;

use App\Helpers\LogHelper;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;
use App\Repositories\Contracts\SalesReportRepositoryInterface;

/**
 * Daily cron service — aggregates ledger_entries into sales_reports (CLAUDE.md §G).
 *
 * Idempotency:
 *   - Per-vendor rows: `updateOrCreate` on (report_date, vendor_id) — DB unique index dedupes.
 *   - Platform-wide row (vendor_id = null): MySQL treats each NULL as distinct in unique indexes,
 *     so deduplication is at the application layer via `upsertPlatformWide` which keys on
 *     report_date + vendor_id IS NULL. Re-running the same date overwrites the values in-place
 *     (the ledger is the source of truth; re-computation is always correct).
 */
final class GenerateSalesReportService
{
    public function __construct(
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly SalesReportRepositoryInterface $reports,
    ) {}

    /**
     * @return array{report_date: string, vendors: int}
     */
    public function handle(string $reportDate): array
    {
        $rows = $this->ledger->dailySalesByVendor($reportDate);

        $platformGross = 0;
        $platformCommission = 0;
        $platformTickets = 0;

        foreach ($rows as $row) {
            $net = $row['gross'] - $row['commission'];

            $this->reports->upsertVendor(
                reportDate: $reportDate,
                vendorId: $row['vendor_id'],
                gross: $row['gross'],
                commission: $row['commission'],
                net: $net,
                ticketsSold: $row['tickets_sold'],
            );

            $platformGross += $row['gross'];
            $platformCommission += $row['commission'];
            $platformTickets += $row['tickets_sold'];
        }

        // Always write the platform-wide row (even when zero, so the dashboard has a row for every day).
        $this->reports->upsertPlatformWide(
            reportDate: $reportDate,
            gross: $platformGross,
            commission: $platformCommission,
            net: $platformGross - $platformCommission,
            ticketsSold: $platformTickets,
        );

        LogHelper::logEntry(LogHelper::LOG_INFO, 'GenerateSalesReport finished', [
            'report_date' => $reportDate,
            'vendors' => count($rows),
            'platform_gross' => $platformGross,
        ]);

        return ['report_date' => $reportDate, 'vendors' => count($rows)];
    }
}
