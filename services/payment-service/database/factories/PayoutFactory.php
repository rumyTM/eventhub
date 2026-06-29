<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payout_ref' => (string) Str::ulid(),       // simulated core-api payout ID — never real data
            'vendor_id' => (string) Str::ulid(),        // simulated vendor ID — never real PII
            'amount' => fake()->numberBetween(10_000, 500_000), // minor units (poisha)
            'currency' => 'BDT',
            'status' => PayoutStatus::Pending->value,
            'gateway_ref' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => PayoutStatus::Completed->value]);
    }

    public function failed(): static
    {
        return $this->state(['status' => PayoutStatus::Failed->value]);
    }
}
