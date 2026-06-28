<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
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
            'order_id' => Order::factory(),
            'gateway' => 'stripe_sim',
            'status' => PaymentStatus::Pending->value,
            'external_ref' => null,                         // gateway ref ([PLACEHOLDER]) — never card data
            'idempotency_key' => (string) Str::uuid(),
            'amount' => fake()->numberBetween(1_000, 500_000), // minor units (poisha)
            'currency' => 'BDT',
        ];
    }
}
