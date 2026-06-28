<?php

namespace Tests\Unit\Gateways;

use App\Contracts\PaymentGatewayContract;
use App\Enums\PaymentStatus;
use App\Gateways\GatewayManager;
use App\Gateways\PayPalSimulator;
use App\Gateways\StripeSimulator;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The gateway simulators must be DETERMINISTIC under a forced or seeded outcome (CLAUDE.md §B / §H),
 * so charge success/failure can be exercised without real randomness. These boot the framework (for
 * config + the manager) but touch no database.
 */
class GatewaySimulatorTest extends TestCase
{
    public function test_forced_success_always_succeeds(): void
    {
        $gateway = new StripeSimulator(successRate: 0.0, force: 'succeed');

        $result = $gateway->charge(10_000, 'BDT', 'order-ref');

        $this->assertTrue($result->succeeded);
        $this->assertSame(PaymentStatus::Succeeded, $result->status());
        $this->assertSame('stripe_sim', $result->gateway);
        $this->assertStringStartsWith('ch_stripe_sim_charge_', $result->reference);
    }

    public function test_forced_failure_always_fails(): void
    {
        $gateway = new StripeSimulator(successRate: 1.0, force: 'fail');

        $result = $gateway->charge(10_000, 'BDT', 'order-ref');

        $this->assertFalse($result->succeeded);
        $this->assertSame(PaymentStatus::Failed, $result->status());
    }

    public function test_success_rate_of_one_always_succeeds(): void
    {
        $gateway = new StripeSimulator(successRate: 1.0);

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($gateway->charge(1_000, 'BDT', "ref-{$i}")->succeeded);
        }
    }

    public function test_success_rate_of_zero_always_fails(): void
    {
        $gateway = new StripeSimulator(successRate: 0.0);

        for ($i = 0; $i < 50; $i++) {
            $this->assertFalse($gateway->charge(1_000, 'BDT', "ref-{$i}")->succeeded);
        }
    }

    public function test_a_fixed_seed_makes_the_rate_based_roll_reproducible(): void
    {
        $a = new StripeSimulator(successRate: 0.5, seed: 12345);
        $b = new StripeSimulator(successRate: 0.5, seed: 12345);

        $this->assertSame(
            $a->charge(1_000, 'BDT', 'ref')->succeeded,
            $b->charge(1_000, 'BDT', 'ref')->succeeded,
        );
    }

    public function test_refund_and_payout_honour_the_forced_outcome(): void
    {
        $gateway = new PayPalSimulator(successRate: 1.0, force: 'fail');

        $this->assertFalse($gateway->refund(5_000, 'BDT', 'ref')->succeeded);
        $this->assertFalse($gateway->payout(5_000, 'BDT', 'ref')->succeeded);

        $charge = $gateway->charge(1, 'BDT', 'ref');
        $this->assertFalse($charge->succeeded);                          // forced fail still applies
        $this->assertStringStartsWith('pp_paypal_sim_', $charge->reference); // a ref is minted even on failure
    }

    public function test_manager_resolves_each_configured_gateway_by_name(): void
    {
        config([
            'gateways.gateways.stripe_sim.force' => 'succeed',
            'gateways.gateways.paypal_sim.force' => 'succeed',
        ]);

        $manager = new GatewayManager;

        $stripe = $manager->make('stripe_sim');
        $paypal = $manager->make('paypal_sim');

        $this->assertInstanceOf(StripeSimulator::class, $stripe);
        $this->assertInstanceOf(PayPalSimulator::class, $paypal);
        $this->assertInstanceOf(PaymentGatewayContract::class, $stripe);
        $this->assertSame('stripe_sim', $stripe->name());
        $this->assertSame('paypal_sim', $paypal->name());
        $this->assertTrue($stripe->charge(1_000, 'BDT', 'ref')->succeeded);
    }

    public function test_manager_carries_the_configured_delay(): void
    {
        config(['gateways.gateways.stripe_sim.delay_seconds' => 7]);

        $this->assertSame(7, (new GatewayManager)->make('stripe_sim')->delaySeconds());
    }

    public function test_manager_rejects_an_unknown_gateway(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GatewayManager)->make('does_not_exist');
    }
}
