<?php

namespace Database\Factories;

use App\Enums\TicketKind;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 *
 * NOTE: creating ticket types directly via this factory bypasses the capacity invariant (which lives in
 * TicketTypeService). Keep per-type quantities modest, or set the parent event's capacity accordingly.
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'kind' => TicketKind::General,
            'price' => 50000,            // 500.00 BDT in poisha
            'currency' => 'BDT',
            'quantity_total' => 100,
            'quantity_sold' => 0,
            'group_size' => null,
            'group_discount' => null,
            'sales_start' => now(),
            'sales_end' => now()->addDays(29),
        ];
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => TicketKind::Vip,
            'price' => 200000, // 2,000.00 BDT
        ]);
    }

    public function earlyBird(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => TicketKind::EarlyBird,
            'price' => 30000, // 300.00 BDT
        ]);
    }

    public function groupBundle(): static
    {
        return $this->state(fn (array $attributes) => [
            'group_size' => 4,
            'group_discount' => 0.15,
        ]);
    }

    /** Attach to a specific event. */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => ['event_id' => $event->id]);
    }
}
