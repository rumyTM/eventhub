<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayContract;
use App\Support\GatewayResult;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\IntervalBoundary;
use Random\Randomizer;

/**
 * Shared simulator behaviour. The outcome (succeed/fail) is decided from a configurable
 * `successRate`; tests make it deterministic two ways (CLAUDE.md §B):
 *   - `force` = 'succeed'|'fail' — pin the outcome regardless of rate (used by the required tests);
 *   - `seed` = int — seed a per-instance RNG so the rate-based roll is reproducible.
 *
 * The roll uses a per-instance \Random\Randomizer (PHP 8.2+), never the global mt_srand/mt_rand
 * state — so seeding one charge never perturbs randomness elsewhere in the worker.
 *
 * The returned `reference` is a clearly-fake simulated id (e.g. `ch_stripe_sim_...`). No real PAN,
 * CVV, or card token is ever produced, accepted, or stored.
 */
abstract class AbstractGatewaySimulator implements PaymentGatewayContract
{
    public function __construct(
        protected readonly float $successRate,
        protected readonly int $delaySeconds = 0,
        protected readonly ?string $force = null,
        protected readonly ?int $seed = null,
    ) {}

    /** The gateway's config key, e.g. `stripe_sim`. */
    abstract public function name(): string;

    /** Prefix for the simulated reference, e.g. `ch_stripe_sim`. */
    abstract protected function refPrefix(): string;

    public function charge(int $amount, string $currency, string $reference): GatewayResult
    {
        return $this->simulate('charge');
    }

    public function refund(int $amount, string $currency, string $reference): GatewayResult
    {
        return $this->simulate('refund');
    }

    public function payout(int $amount, string $currency, string $reference): GatewayResult
    {
        return $this->simulate('payout');
    }

    public function delaySeconds(): int
    {
        return $this->delaySeconds;
    }

    private function simulate(string $kind): GatewayResult
    {
        return new GatewayResult(
            succeeded: $this->decideOutcome(),
            reference: $this->mintReference($kind),
            gateway: $this->name(),
        );
    }

    /**
     * Decide success/failure. `force` wins (deterministic for tests); the rate boundaries short-
     * circuit; otherwise a per-instance RNG rolls against `successRate` — seeded (reproducible) or
     * cryptographically random.
     */
    protected function decideOutcome(): bool
    {
        if ($this->force === 'succeed') {
            return true;
        }

        if ($this->force === 'fail') {
            return false;
        }

        if ($this->successRate >= 1.0) {
            return true;
        }

        if ($this->successRate <= 0.0) {
            return false;
        }

        $randomizer = new Randomizer($this->seed !== null ? new Mt19937($this->seed) : new Secure);

        return $randomizer->getFloat(0.0, 1.0, IntervalBoundary::ClosedOpen) < $this->successRate;
    }

    /** A clearly-fake simulated reference — never card data. */
    protected function mintReference(string $kind): string
    {
        return $this->refPrefix().'_'.$kind.'_'.strtoupper(Str::random(20));
    }
}
