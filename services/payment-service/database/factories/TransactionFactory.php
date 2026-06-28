<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'type' => TransactionType::Charge->value,
            'amount' => fake()->numberBetween(1_000, 500_000), // signed minor units (poisha)
            'currency' => 'BDT',
            'gateway_ref' => 'sim_charge_'.strtoupper(Str::random(20)), // clearly-fake ref — never card data
        ];
    }
}
