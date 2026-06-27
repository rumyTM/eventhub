<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(30);

        return [
            // Default to a verified vendor so events are publishable; use a non-verified vendor explicitly
            // (via vendor_id) when testing the publish KYC gate.
            'vendor_id' => Vendor::factory()->verified(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'timezone' => 'Asia/Dhaka',
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(4),
            'capacity' => 500,
            'status' => EventStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Published]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Ongoing]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Cancelled]);
    }

    /** Attach this event to a specific vendor. */
    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (array $attributes) => ['vendor_id' => $vendor->id]);
    }
}
