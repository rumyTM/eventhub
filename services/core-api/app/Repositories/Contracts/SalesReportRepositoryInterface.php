<?php

namespace App\Repositories\Contracts;

use App\Models\SalesReport;

interface SalesReportRepositoryInterface
{
    /**
     * Upsert the daily vendor-scoped sales row. Safe to call multiple times for the same
     * (report_date, vendor_id) — always produces exactly one row.
     */
    public function upsertVendor(
        string $reportDate,
        string $vendorId,
        int $gross,
        int $commission,
        int $net,
        int $ticketsSold,
        int $totalDiscount,
    ): SalesReport;

    /**
     * Upsert the platform-wide daily roll-up (vendor_id = null). MySQL treats each NULL as distinct
     * so the unique index on (report_date, vendor_id) does not dedupe the null row — this method
     * uses an application-level updateOrCreate keyed only on report_date + null vendor.
     */
    public function upsertPlatformWide(
        string $reportDate,
        int $gross,
        int $commission,
        int $net,
        int $ticketsSold,
        int $totalDiscount,
    ): SalesReport;
}
