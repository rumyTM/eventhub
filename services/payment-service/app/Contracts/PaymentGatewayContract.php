<?php

namespace App\Contracts;

use App\Support\GatewayResult;

/**
 * The gateway abstraction (CLAUDE.md §B). Implemented by the simulators (StripeSimulator,
 * PayPalSimulator) and resolved by name via GatewayManager. A real gateway is NEVER integrated and
 * no real PAN/CVV/token is ever passed across this boundary — amounts are integer minor units and
 * the only identifier returned is a clearly-fake simulated reference.
 *
 * All three operations share one shape: (amount minor units, currency, correlation reference) ->
 * a GatewayResult whose outcome is decided by the simulator's configurable success/failure rate.
 */
interface PaymentGatewayContract
{
    public function charge(int $amount, string $currency, string $reference): GatewayResult;

    public function refund(int $amount, string $currency, string $reference): GatewayResult;

    public function payout(int $amount, string $currency, string $reference): GatewayResult;

    /** The gateway's config key (e.g. `stripe_sim`). */
    public function name(): string;

    /** Configured processing delay in seconds — used by the (later) webhook-callback job. */
    public function delaySeconds(): int;
}
