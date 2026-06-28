<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayContract;
use InvalidArgumentException;

/**
 * Resolves a gateway simulator by its config key (`stripe_sim`/`paypal_sim`), building it from the
 * per-gateway settings in config/gateways.php (success rate, delay, and the test-only force/seed
 * levers). Bound as a singleton in AppServiceProvider; services depend on this, not on a concrete
 * simulator, so a FakeGateway can be swapped in for tests.
 */
final class GatewayManager
{
    public function make(string $name): PaymentGatewayContract
    {
        /** @var array<string, mixed>|null $config */
        $config = config("gateways.gateways.{$name}");

        if ($config === null) {
            throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
        }

        /** @var class-string<PaymentGatewayContract> $driver */
        $driver = $config['driver'];

        if (! is_a($driver, PaymentGatewayContract::class, true)) {
            throw new InvalidArgumentException("Gateway driver [{$driver}] must implement PaymentGatewayContract.");
        }

        return new $driver(
            successRate: (float) $config['success_rate'],
            delaySeconds: (int) ($config['delay_seconds'] ?? 0),
            force: $config['force'] ?? null,
            seed: isset($config['seed']) && $config['seed'] !== null ? (int) $config['seed'] : null,
        );
    }

    public function default(): PaymentGatewayContract
    {
        return $this->make((string) config('gateways.default'));
    }
}
