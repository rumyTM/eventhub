<?php

namespace App\Support\Refunds;

use App\Actions\Payouts\CalculateCommission;
use App\Enums\RefundReason;
use Carbon\CarbonInterface;

/**
 * Pure refund-policy decider (no DB, no I/O — unit-testable in isolation). Decides eligibility and the
 * auto-derived refundable amount from the refund reason, the event start, and the money state. Encodes:
 *
 *  - **Event cancellation (ADR-23):** a flat **100%**, ignoring the time bands — every attendee is made
 *    whole regardless of when the event was cancelled.
 *  - **Attendee request (CLAUDE.md §F):** time-based vs the event's `starts_at` — **>48h → 100%**,
 *    **24–48h (inclusive) → 50%**, **<24h → 0%**. The 0% band is *out of policy*: the attendee gets no
 *    automatic refund (the out-of-policy contest → dispute path is a later slice, ADR-11).
 *
 * Money is integer minor units throughout (ADR-08): the percentage is applied with **half-up** rounding
 * via the basis-points trick (`(base*pct + 50) / 100`), mirroring {@see CalculateCommission}.
 * The result is then **capped** so cumulative refunds (this one + everything already refunded) can never
 * exceed the original charge — if the charge is already fully refunded, the decision is ineligible.
 *
 * Single-currency per order is assumed (ADR-12, enforced at checkout): all amounts here are in the
 * order's one currency, so the policy never mixes currencies.
 */
final class RefundPolicy
{
    private const SECONDS_FULL = 48 * 3600; // >48h before start → 100%

    private const SECONDS_HALF = 24 * 3600; // 24–48h (inclusive) before start → 50%

    /**
     * @param  RefundReason  $reason  attendee-requested (time-banded) vs event-cancelled (flat 100%)
     * @param  CarbonInterface  $eventStartsAt  the reference event start (UTC)
     * @param  CarbonInterface  $now  evaluation time (UTC) — injected so the decision is deterministic
     * @param  int  $selectedBaseMinor  100%-policy base for the tickets in THIS request (subset = partial)
     * @param  int  $chargeMinor  the original succeeded charge — the cumulative-refund ceiling
     * @param  int  $alreadyRefundedMinor  total of prior non-failed refunds against this charge
     */
    public function decide(
        RefundReason $reason,
        CarbonInterface $eventStartsAt,
        CarbonInterface $now,
        int $selectedBaseMinor,
        int $chargeMinor,
        int $alreadyRefundedMinor,
    ): RefundDecision {
        $percent = $this->policyPercent($reason, $eventStartsAt, $now);

        $rawAmount = intdiv($selectedBaseMinor * $percent + 50, 100); // half-up, integer poisha
        $remainingCap = max(0, $chargeMinor - $alreadyRefundedMinor);
        $amount = min($rawAmount, $remainingCap);

        $policyApplied = (string) $percent;

        if ($amount <= 0) {
            // Distinguish "outside the refund window" (0% band) from "already fully refunded" so the
            // caller can surface the right message.
            $denial = $percent === 0 ? 'out_of_window' : 'already_refunded';

            return RefundDecision::ineligible($policyApplied, $reason, $denial);
        }

        return RefundDecision::eligible($policyApplied, $amount, $reason);
    }

    /** The policy band as an integer percent: cancellation is flat 100%, otherwise time-based. */
    private function policyPercent(RefundReason $reason, CarbonInterface $eventStartsAt, CarbonInterface $now): int
    {
        if ($reason->isCancellation()) {
            return 100; // ADR-23 — overrides the time bands
        }

        // Seconds remaining until the event starts (signed via raw timestamps to avoid any Carbon
        // diff sign/float ambiguity); negative once the event has begun/passed → falls into the 0% band.
        $secondsUntilStart = $eventStartsAt->getTimestamp() - $now->getTimestamp();

        return match (true) {
            $secondsUntilStart > self::SECONDS_FULL => 100,
            $secondsUntilStart >= self::SECONDS_HALF => 50,
            default => 0,
        };
    }
}
