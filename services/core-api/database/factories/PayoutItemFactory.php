<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutItem>
 */
class PayoutItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payout_id' => Payout::factory(),
            'order_id' => Order::factory()->paid(),
            'settled_amount' => fake()->numberBetween(10_000, 100_000),
        ];
    }
}
