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
}
