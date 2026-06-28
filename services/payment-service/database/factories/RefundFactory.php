<?php

namespace Database\Factories;

use App\Enums\Gateway;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'order_id' => (string) Str::ulid(),        // core-api order reference
            'gateway' => Gateway::StripeSim->value,
            'status' => RefundStatus::Pending->value,
            'amount' => fake()->numberBetween(1_000, 500_000), // minor units (poisha)
            'currency' => 'BDT',
            'gateway_ref' => null,                       // assigned only when the refund resolves
            'reason' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RefundStatus::Completed->value,
            'gateway_ref' => ($attributes['gateway'] ?? 'sim').'_refund_'.strtoupper(Str::random(20)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => RefundStatus::Failed->value,
        ]);
    }
}
