<?php

namespace App\Gateways;

/**
 * Simulated PayPal gateway. Outcome is governed by its configurable success rate
 * (config/gateways.php → `paypal_sim`). Not a real integration; no card data is ever handled.
 */
final class PayPalSimulator extends AbstractGatewaySimulator
{
    public function name(): string
    {
        return 'paypal_sim';
    }

    protected function refPrefix(): string
    {
        return 'pp_paypal_sim';
    }
}
