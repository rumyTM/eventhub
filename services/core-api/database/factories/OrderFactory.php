<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendee_id' => Attendee::factory(),
            'status' => OrderStatus::Pending->value,
            'total' => fake()->numberBetween(1_000, 500_000), // minor units (poisha)
            'currency' => 'BDT',
            'commission_rate' => '0.1000',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::Paid->value]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::Expired->value]);
    }
}
