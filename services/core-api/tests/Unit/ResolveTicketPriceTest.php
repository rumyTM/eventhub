<?php

namespace Tests\Unit;

use App\Actions\Orders\ResolveTicketPrice;
use App\Models\TicketType;
use PHPUnit\Framework\TestCase;

class ResolveTicketPriceTest extends TestCase
{
    private function ticketType(int $price, ?int $groupSize, ?float $discount): TicketType
    {
        // Unsaved model — the action is pure, so no DB is needed. group_discount is a decimal column, so
        // it round-trips as a string from the DB; pass it as a string here to mirror real reads.
        return new TicketType([
            'price' => $price,
            'group_size' => $groupSize,
            'group_discount' => $discount === null ? null : (string) $discount,
        ]);
    }

    public function test_returns_base_price_without_a_group_bundle(): void
    {
        $action = new ResolveTicketPrice;
        $this->assertSame(1000, $action->handle($this->ticketType(1000, null, null), 5));
    }

    public function test_applies_discount_when_quantity_meets_group_size(): void
    {
        $action = new ResolveTicketPrice;
        // 1000 * (1 - 0.25) = 750
        $this->assertSame(750, $action->handle($this->ticketType(1000, 4, 0.25), 4));
    }

    public function test_does_not_apply_discount_below_group_size(): void
    {
        $action = new ResolveTicketPrice;
        $this->assertSame(1000, $action->handle($this->ticketType(1000, 4, 0.25), 3));
    }

    public function test_rounds_to_nearest_minor_unit(): void
    {
        $action = new ResolveTicketPrice;
        // 999 * (1 - 0.10) = 899.1 → 899
        $this->assertSame(899, $action->handle($this->ticketType(999, 2, 0.10), 2));
    }
}
