<?php

namespace App\Actions\Payouts;

use App\Support\Payouts\PayoutCalculation;

/**
 * Pure computation action — no DB, no side effects (CLAUDE.md §A, ADR-06/08/13/20).
 *
 * Given the three pre-aggregated ledger components and the minimum payout threshold, derives a
 * {@see PayoutCalculation}. The caller (PayoutBuildService) is responsible for pre-filtering entries
 * to only ELIGIBLE sources: sale/commission entries from completed-event orders not yet settled, plus
 * clawback entries from already-disbursed orders — never pre-event-completion funds (ADR-20).
 *
 * Rules:
 *   net      = gross − commission   (vendor's entitlement before adjustments; NOT clamped — may be negative)
 *   payable  = net + adjustments    (adjustments are typically ≤ 0: refunds/clawbacks reduce it)
 *   payable < 0  → 0  (negative balance rolls into next cycle, never produces a negative payout)
 *   payable < threshold → 0  (below-threshold rolls into next cycle)
 *   payable ≥ threshold → payable
 */
final class CalculatePayout
{
    /**
     * @param  int  $gross  SUM of positive `sale` ledger entries for eligible orders (minor units)
     * @param  int  $commission  Absolute value of `commission` entries (minor units, > 0)
     * @param  int  $adjustments  SUM of `refund` + `clawback` entries (typically ≤ 0)
     * @param  int  $threshold  Minimum payable amount from platform settings (minor units)
     */
    public function handle(
        int $gross,
        int $commission,
        int $adjustments,
        int $threshold,
    ): PayoutCalculation {
        $net = $gross - $commission;
        $payable = $net + $adjustments;

        $meetsThreshold = $payable > 0 && $payable >= $threshold;

        return new PayoutCalculation(
            gross: $gross,
            commission: $commission,
            net: $net,
            adjustments: $adjustments,
            payable: $meetsThreshold ? $payable : 0,
            meetsThreshold: $meetsThreshold,
        );
    }
}
