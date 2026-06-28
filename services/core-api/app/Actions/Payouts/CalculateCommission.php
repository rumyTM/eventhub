<?php

namespace App\Actions\Payouts;

/**
 * Platform commission on a gross amount, using the order's snapshotted `commission_rate` (ADR-14).
 *
 * Pure integer math (ADR-08): the rate is a `decimal:4` STRING (e.g. "0.1000"), parsed to an integer
 * scaled by 10_000 with no float involved, then applied with **half-up** rounding by adding half the
 * denominator before integer division. Reused by the webhook settlement now and by payout calc later.
 */
final class CalculateCommission
{
    private const SCALE = 10_000; // decimal:4 → basis points × 100

    public function handle(int $gross, string $rate): int
    {
        // Guard the money math: a blank/non-numeric rate (e.g. a null commission_rate cast to "") would
        // otherwise parse to 0 and silently charge no commission. Fail loud instead of mis-settling.
        if ($rate === '' || ! is_numeric($rate)) {
            throw new \InvalidArgumentException('Commission rate must be a numeric decimal string.');
        }

        [$whole, $fraction] = array_pad(explode('.', $rate), 2, '');
        $fraction = substr(str_pad($fraction, 4, '0'), 0, 4);
        $scaledRate = (int) ($whole.$fraction); // "0.1000" → 1000

        // Half-up: add half the denominator before truncating integer division.
        return intdiv($gross * $scaledRate + (self::SCALE >> 1), self::SCALE);
    }
}
