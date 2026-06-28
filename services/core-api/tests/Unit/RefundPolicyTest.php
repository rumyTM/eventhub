<?php

namespace Tests\Unit;

use App\Enums\RefundReason;
use App\Support\Refunds\RefundPolicy;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for the refund-policy decider — no DB, no framework. Covers the time bands, the
 * cancellation override, partial vs full base, half-up rounding, and the cumulative-refund cap.
 */
class RefundPolicyTest extends TestCase
{
    private RefundPolicy $policy;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RefundPolicy;
        $this->now = Carbon::parse('2026-06-30 12:00:00', 'UTC');
    }

    /** start = now + given hours (seconds-precise, matching the policy's raw-timestamp math). */
    private function startInHours(float $hours): Carbon
    {
        return $this->now->copy()->addSeconds((int) round($hours * 3600));
    }

    // --- attendee time bands ---

    public function test_more_than_48h_before_start_refunds_100_percent(): void
    {
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(72), $this->now, 10000, 10000, 0
        );

        $this->assertTrue($d->eligible);
        $this->assertSame('100', $d->policyApplied);
        $this->assertSame(10000, $d->amountMinor);
    }

    public function test_exactly_48h_before_start_refunds_50_percent(): void
    {
        // 48h is the upper edge of the 24–48h (inclusive) band → 50%, not 100%.
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(48), $this->now, 10000, 10000, 0
        );

        $this->assertTrue($d->eligible);
        $this->assertSame('50', $d->policyApplied);
        $this->assertSame(5000, $d->amountMinor);
    }

    public function test_between_24h_and_48h_refunds_50_percent(): void
    {
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(36), $this->now, 10000, 10000, 0
        );

        $this->assertSame('50', $d->policyApplied);
        $this->assertSame(5000, $d->amountMinor);
    }

    public function test_exactly_24h_before_start_refunds_50_percent(): void
    {
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(24), $this->now, 10000, 10000, 0
        );

        $this->assertTrue($d->eligible);
        $this->assertSame('50', $d->policyApplied);
    }

    public function test_inside_24h_window_is_ineligible_zero_percent(): void
    {
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(12), $this->now, 10000, 10000, 0
        );

        $this->assertFalse($d->eligible);
        $this->assertSame('0', $d->policyApplied);
        $this->assertSame(0, $d->amountMinor);
        $this->assertSame('out_of_window', $d->denialReason);
    }

    public function test_event_already_started_is_ineligible(): void
    {
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(-5), $this->now, 10000, 10000, 0
        );

        $this->assertFalse($d->eligible);
        $this->assertSame('0', $d->policyApplied);
    }

    // --- cancellation override (ADR-23) ---

    public function test_event_cancellation_refunds_100_percent_even_inside_zero_window(): void
    {
        // A cancellation ignores the time bands entirely — full refund even with the event hours away.
        $d = $this->policy->decide(
            RefundReason::EventCancelled, $this->startInHours(2), $this->now, 10000, 10000, 0
        );

        $this->assertTrue($d->eligible);
        $this->assertSame('100', $d->policyApplied);
        $this->assertSame(10000, $d->amountMinor);
    }

    public function test_event_cancellation_refunds_100_percent_even_after_start(): void
    {
        $d = $this->policy->decide(
            RefundReason::EventCancelled, $this->startInHours(-10), $this->now, 10000, 10000, 0
        );

        $this->assertTrue($d->eligible);
        $this->assertSame('100', $d->policyApplied);
    }

    // --- partial (subset) vs full base ---

    public function test_partial_base_refunds_the_subset_amount(): void
    {
        // selected base is a subset (e.g. 2 of 4 tickets at 2500 each = 5000) → 100% of that subset.
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(72), $this->now, 5000, 10000, 0
        );

        $this->assertTrue($d->eligible);
        $this->assertSame(5000, $d->amountMinor);
    }

    // --- half-up rounding on the 50% band ---

    public function test_fifty_percent_rounds_half_up(): void
    {
        // 999 * 50% = 499.5 → 500 (half-up), never a float.
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(36), $this->now, 999, 999, 0
        );

        $this->assertSame('50', $d->policyApplied);
        $this->assertSame(500, $d->amountMinor);
    }

    // --- cumulative-refund cap (already-refunded) ---

    public function test_fully_refunded_charge_is_ineligible(): void
    {
        // charge already fully refunded → nothing remains, even on a 100% band.
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(72), $this->now, 10000, 10000, 10000
        );

        $this->assertFalse($d->eligible);
        $this->assertSame('already_refunded', $d->denialReason);
        $this->assertSame(0, $d->amountMinor);
    }

    public function test_partial_prior_refund_caps_the_amount_to_the_remainder(): void
    {
        // charge 10000, 8000 already refunded → a 100% request for 10000 is capped to the 2000 remainder.
        $d = $this->policy->decide(
            RefundReason::AttendeeRequested, $this->startInHours(72), $this->now, 10000, 10000, 8000
        );

        $this->assertTrue($d->eligible);
        $this->assertSame(2000, $d->amountMinor);
    }
}
