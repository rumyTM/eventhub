<?php

namespace App\Support\Payouts;

use App\Actions\Payouts\CalculatePayout;

/**
 * Immutable result of a {@see CalculatePayout} call. Separates raw financials
 * (gross/commission/net) from the policy decision (meetsThreshold/payable) so callers can inspect
 * both the accounting break-down and the threshold gate.
 */
final readonly class PayoutCalculation
{
    public function __construct(
        /** Sum of positive `sale` ledger entries for eligible orders. */
        public int $gross,
        /** Absolute value of `commission` entries (amount the platform deducts). */
        public int $commission,
        /** gross − commission — vendor's entitlement before refund/clawback adjustments. */
        public int $net,
        /** Sum of `refund` + `clawback` entries (typically ≤ 0). */
        public int $adjustments,
        /** net + adjustments, floored at 0; 0 when below threshold. This is the disbursable amount. */
        public int $payable,
        /** Whether payable ≥ configured minimum threshold. False → rolls into next cycle. */
        public bool $meetsThreshold,
    ) {}
}
