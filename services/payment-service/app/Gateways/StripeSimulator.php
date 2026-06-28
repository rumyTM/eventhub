<?php

namespace App\Gateways;

/**
 * Simulated Stripe gateway. Outcome is governed by its configurable success rate
 * (config/gateways.php → `stripe_sim`). Not a real integration; no card data is ever handled.
 */
final class StripeSimulator extends AbstractGatewaySimulator
{
    public function name(): string
    {
        return 'stripe_sim';
    }

    protected function refPrefix(): string
    {
        return 'ch_stripe_sim';
    }
}
