<?php

namespace App\Repositories\Eloquent;

use App\Models\SalesReport;
use App\Repositories\Contracts\SalesReportRepositoryInterface;

final class SalesReportRepository implements SalesReportRepositoryInterface
{
    public function upsertVendor(
        string $reportDate,
        string $vendorId,
        int $gross,
        int $commission,
        int $net,
        int $ticketsSold,
        int $totalDiscount,
    ): SalesReport {
        // Use whereDate() not direct equality: SQLite stores date columns as '2026-06-29 00:00:00'
        // while MySQL uses '2026-06-29', so `= '2026-06-29'` fails on SQLite in test environments.
        $report = SalesReport::whereDate('report_date', $reportDate)
            ->where('vendor_id', $vendorId)
            ->first();

        if ($report === null) {
            $report = new SalesReport(['report_date' => $reportDate, 'vendor_id' => $vendorId]);
        }

        $report->fill([
            'gross' => $gross,
            'commission' => $commission,
            'net' => $net,
            'tickets_sold' => $ticketsSold,
            'total_discount' => $totalDiscount,
            'currency' => 'BDT',
        ])->save();

        return $report;
    }

    public function upsertPlatformWide(
        string $reportDate,
        int $gross,
        int $commission,
        int $net,
        int $ticketsSold,
        int $totalDiscount,
    ): SalesReport {
        // MySQL treats each NULL as distinct for unique indexes, so the DB-level dedup does not
        // apply to the platform-wide row. Explicit find-by-date + null vendor_id is the guard.
        $report = SalesReport::whereDate('report_date', $reportDate)
            ->whereNull('vendor_id')
            ->first();

        if ($report === null) {
            $report = new SalesReport(['report_date' => $reportDate, 'vendor_id' => null]);
        }

        $report->fill([
            'gross' => $gross,
            'commission' => $commission,
            'net' => $net,
            'tickets_sold' => $ticketsSold,
            'total_discount' => $totalDiscount,
            'currency' => 'BDT',
        ])->save();

        return $report;
    }
}
