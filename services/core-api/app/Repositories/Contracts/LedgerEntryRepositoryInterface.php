<?php

namespace App\Repositories\Contracts;

use App\Models\LedgerEntry;

interface LedgerEntryRepositoryInterface
{
    /**
     * Append one signed entry to the financial ledger (never updated/deleted — ADR-13).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LedgerEntry;

    /**
     * Aggregate daily sales from ledger_entries for a given date, grouped by vendor_id.
     * Used by GenerateSalesReportService to populate the sales_reports table.
     *
     * Returns one entry per vendor that had Sale entries on the date:
     *   - gross      : SUM of Sale entry amounts for that date
     *   - commission : ABS(SUM) of Commission entry amounts for that date
     *   - tickets_sold: SUM of order_items.quantity for those orders
     *
     * @return list<array{vendor_id: string, gross: int, commission: int, tickets_sold: int}>
     */
    public function dailySalesByVendor(string $reportDate): array;

    /**
     * Return the payout amount breakdown for a vendor's eligible orders (ADR-13/20).
     *
     * - `gross`      : SUM of `sale` entries for the given eligible order IDs
     * - `commission` : ABS(SUM of `commission` entries for the same order IDs)
     * - `adjustments`: SUM of `refund` entries for completed refunds against eligible orders
     *                  PLUS SUM of all `clawback` entries for this vendor (post-payout recoveries)
     * - `per_order`  : sale+commission net per order_id — used for payout_items.settled_amount
     *
     * Callers pass only eligibility-screened orders (completed-event, not yet settled in a paid payout).
     * Clawbacks are always included: by definition they are post-settlement debts from prior cycles.
     *
     * @param  list<string>  $eligibleOrderIds
     * @return array{gross: int, commission: int, adjustments: int, per_order: array<string, int>}
     */
    public function vendorPayoutAmounts(string $vendorId, array $eligibleOrderIds): array;

    /**
     * Per-vendor sum of group-discount savings for orders whose Sale ledger entry falls on `$reportDate`.
     *
     * Returns one entry per vendor that had discounted items on that date:
     *   - vendor_id      : the vendor
     *   - total_discount : SUM((original_price − unit_price) × quantity) in minor units
     *
     * Only lines where original_price > unit_price are included (zero-discount lines contribute 0).
     * Joins ledger_entries → order_items so the date anchor matches the ledger (payment date), not
     * the order-creation date.
     *
     * @return list<array{vendor_id: string, total_discount: int}>
     */
    public function dailyDiscountByVendor(string $reportDate): array;

    /**
     * Sum of in-flight refund amounts (status = requested|pending) for the given order IDs.
     *
     * These refunds have no ledger entry yet, so they are not captured in `adjustments`. Recording
     * this amount as `reserved_refund` on the payout row surfaces the vendor's at-risk exposure to
     * the admin before the refunds settle in a future cycle.
     *
     * @param  list<string>  $orderIds
     */
    public function pendingRefundAmountForOrders(array $orderIds): int;
}
