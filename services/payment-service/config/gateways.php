<?php

use App\Gateways\PayPalSimulator;
use App\Gateways\StripeSimulator;

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway
    |--------------------------------------------------------------------------
    | Used when a charge request does not name a specific gateway.
    */

    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'stripe_sim'),

    /*
    |--------------------------------------------------------------------------
    | Simulated gateways
    |--------------------------------------------------------------------------
    | These are SIMULATORS, never real gateways — no real PAN/CVV/token is ever handled.
    |
    |   success_rate  fraction in [0,1]; the simulator succeeds with this probability. The failure
    |                 rate is simply its complement (1 - success_rate).
    |   delay_seconds processing delay the (later) webhook-callback job waits before reporting back.
    |   force         'succeed'|'fail' — pin the outcome for deterministic tests/dev (overrides rate).
    |   seed          int — seed the RNG so a rate-based roll is reproducible. Secrets are NOT here.
    */

    'gateways' => [

        'stripe_sim' => [
            'driver' => StripeSimulator::class,
            'success_rate' => (float) env('GATEWAY_STRIPE_SUCCESS_RATE', 0.9),
            'delay_seconds' => (int) env('GATEWAY_STRIPE_DELAY_SECONDS', 0),
            'force' => env('GATEWAY_STRIPE_FORCE'),
            'seed' => env('GATEWAY_STRIPE_SEED'),
        ],

        'paypal_sim' => [
            'driver' => PayPalSimulator::class,
            'success_rate' => (float) env('GATEWAY_PAYPAL_SUCCESS_RATE', 0.85),
            'delay_seconds' => (int) env('GATEWAY_PAYPAL_DELAY_SECONDS', 0),
            'force' => env('GATEWAY_PAYPAL_FORCE'),
            'seed' => env('GATEWAY_PAYPAL_SEED'),
        ],

    ],

];
