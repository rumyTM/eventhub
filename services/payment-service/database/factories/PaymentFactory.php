<?php

namespace Database\Factories;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => (string) Str::ulid(),         // core-api order reference
            'gateway' => Gateway::StripeSim->value,
            'status' => PaymentStatus::Pending->value,
            'amount' => fake()->numberBetween(1_000, 500_000), // minor units (poisha)
            'currency' => 'BDT',
            'gateway_ref' => null,                       // assigned only when the charge resolves
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Succeeded->value,
            'gateway_ref' => ($attributes['gateway'] ?? 'sim').'_charge_'.strtoupper(Str::random(20)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed->value,
        ]);
    }
}
