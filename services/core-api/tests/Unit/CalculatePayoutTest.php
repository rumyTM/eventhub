<?php

namespace Tests\Unit;

use App\Actions\Payouts\CalculatePayout;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the `CalculatePayout` pure action (no DB, no Laravel boot).
 *
 * Rules under test (CLAUDE.md §F Payout management + ADR-08/13/20):
 *   net      = gross − commission   (vendor entitlement before adjustments; may be negative if commission > gross)
 *   payable  = net + adjustments    (adjustments ≤ 0 for refunds/clawbacks)
 *   payable < 0  → 0               (never produce a negative payout)
 *   payable < threshold → 0, meetsThreshold=false  (rolls into next cycle)
 *   payable ≥ threshold → payable, meetsThreshold=true
 */
class CalculatePayoutTest extends TestCase
{
    private CalculatePayout $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculatePayout;
    }

    public function test_computes_net_correctly(): void
    {
        $result = $this->action->handle(
            gross: 900_000,
            commission: 90_000,
            adjustments: 0,
            threshold: 10_000,
        );

        $this->assertSame(900_000, $result->gross);
        $this->assertSame(90_000, $result->commission);
        $this->assertSame(810_000, $result->net);     // 900_000 − 90_000
        $this->assertSame(0, $result->adjustments);
        $this->assertSame(810_000, $result->payable);
        $this->assertTrue($result->meetsThreshold);
    }

    public function test_adjustments_reduce_payable(): void
    {
        $result = $this->action->handle(
            gross: 900_000,
            commission: 90_000,
            adjustments: -50_000,   // refund/clawback
            threshold: 10_000,
        );

        $this->assertSame(-50_000, $result->adjustments);
        $this->assertSame(760_000, $result->payable);  // 810_000 + (−50_000)
        $this->assertTrue($result->meetsThreshold);
    }

    public function test_meets_threshold_at_exact_threshold(): void
    {
        $result = $this->action->handle(
            gross: 10_000,
            commission: 0,
            adjustments: 0,
            threshold: 10_000,
        );

        $this->assertSame(10_000, $result->payable);
        $this->assertTrue($result->meetsThreshold);
    }

    public function test_below_threshold_produces_zero_payable(): void
    {
        $result = $this->action->handle(
            gross: 9_999,
            commission: 0,
            adjustments: 0,
            threshold: 10_000,
        );

        $this->assertSame(0, $result->payable);
        $this->assertFalse($result->meetsThreshold);
        // The raw net is still exposed so the caller can log/skip intelligently.
        $this->assertSame(9_999, $result->net);
    }

    public function test_negative_payable_is_floored_at_zero(): void
    {
        // Large clawbacks wipe the whole balance — payable must never go below zero.
        $result = $this->action->handle(
            gross: 100_000,
            commission: 10_000,
            adjustments: -200_000,  // clawbacks exceed gross-commission
            threshold: 10_000,
        );

        $this->assertSame(-110_000, $result->net + $result->adjustments);  // raw sum is negative
        $this->assertSame(0, $result->payable);
        $this->assertFalse($result->meetsThreshold);
    }

    public function test_exactly_zero_payable_does_not_meet_threshold(): void
    {
        // Adjustments exactly cancel net: payable = 0, which does NOT satisfy "payable > 0".
        $result = $this->action->handle(
            gross: 90_000,
            commission: 0,
            adjustments: -90_000,
            threshold: 0,
        );

        $this->assertSame(0, $result->payable);
        $this->assertFalse($result->meetsThreshold);
    }

    public function test_zero_threshold_passes_any_positive_payable(): void
    {
        $result = $this->action->handle(
            gross: 1,
            commission: 0,
            adjustments: 0,
            threshold: 0,
        );

        $this->assertSame(1, $result->payable);
        $this->assertTrue($result->meetsThreshold);
    }

    public function test_commission_larger_than_gross_produces_negative_net(): void
    {
        // Should not happen in practice, but the action must not produce a negative payout.
        $result = $this->action->handle(
            gross: 50_000,
            commission: 70_000,
            adjustments: 0,
            threshold: 10_000,
        );

        $this->assertSame(-20_000, $result->net);  // exposed for observability
        $this->assertSame(0, $result->payable);
        $this->assertFalse($result->meetsThreshold);
    }

    public function test_all_breakdown_fields_are_exposed_correctly(): void
    {
        $result = $this->action->handle(
            gross: 500_000,
            commission: 50_000,
            adjustments: -25_000,
            threshold: 1_000,
        );

        $this->assertSame(500_000, $result->gross);
        $this->assertSame(50_000, $result->commission);
        $this->assertSame(450_000, $result->net);
        $this->assertSame(-25_000, $result->adjustments);
        $this->assertSame(425_000, $result->payable);
        $this->assertTrue($result->meetsThreshold);
    }
}
