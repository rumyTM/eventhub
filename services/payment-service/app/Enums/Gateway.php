<?php

namespace App\Enums;

/**
 * The simulated payment gateways this service supports. These are NOT real gateways — no real PAN,
 * CVV, or card token is ever handled (see CLAUDE.md §B). Each value maps to a simulator + its
 * configurable success/failure rate in config/gateways.php.
 */
enum Gateway: string
{
    case StripeSim = 'stripe_sim';
    case PayPalSim = 'paypal_sim';

    public function label(): string
    {
        return match ($this) {
            self::StripeSim => 'Stripe (simulated)',
            self::PayPalSim => 'PayPal (simulated)',
        };
    }
}
