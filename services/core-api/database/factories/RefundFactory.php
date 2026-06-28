<?php

namespace Database\Factories;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'amount' => fake()->numberBetween(1_000, 500_000), // minor units (poisha)
            'policy_applied' => '100',
            'status' => RefundStatus::Requested->value,
            'reason' => RefundReason::AttendeeRequested->value,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (): array => ['status' => RefundStatus::Requested->value]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => RefundStatus::Pending->value]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => RefundStatus::Completed->value]);
    }

    public function cancellation(): static
    {
        return $this->state(fn (): array => [
            'reason' => RefundReason::EventCancelled->value,
            'policy_applied' => '100',
        ]);
    }
}
