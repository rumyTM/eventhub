<?php

namespace Tests\Feature\Orders;

use App\Enums\HoldStatus;
use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketHold;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReleaseExpiredHoldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Checkout dispatches the async charge job; fake the queue so this hold-expiry test stays
        // isolated from the payment-service call (covered in InitiateChargeTest).
        Queue::fake();
    }

    private function checkoutOne(int $quantityTotal = 10): Order
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        $event = Event::factory()->published()->create();
        $tt = TicketType::factory()->forEvent($event)->create(['quantity_total' => $quantityTotal]);

        $response = $this->withHeader('Idempotency-Key', 'key-'.uniqid())
            ->postJson('/api/v1/orders', ['items' => [['ticket_type_id' => $tt->id, 'quantity' => 1]]])
            ->assertCreated();

        return Order::findOrFail($response->json('data.order.id'));
    }

    public function test_it_releases_expired_holds_and_expires_their_pending_orders(): void
    {
        $order = $this->checkoutOne();
        TicketHold::query()->where('order_id', $order->id)->update(['expires_at' => now()->subMinutes(20)]);

        $this->artisan('holds:release-expired')->assertSuccessful();

        $this->assertSame(HoldStatus::Released, TicketHold::query()->where('order_id', $order->id)->first()->status);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_it_is_idempotent_when_re_run(): void
    {
        $order = $this->checkoutOne();
        TicketHold::query()->where('order_id', $order->id)->update(['expires_at' => now()->subMinutes(20)]);

        $this->artisan('holds:release-expired')->assertSuccessful();
        $this->artisan('holds:release-expired')->assertSuccessful(); // no error, no further change

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(HoldStatus::Released, TicketHold::query()->where('order_id', $order->id)->first()->status);
    }

    public function test_it_does_not_touch_unexpired_holds_or_pending_orders(): void
    {
        $order = $this->checkoutOne(); // hold expires in ~15 min (not due)

        $this->artisan('holds:release-expired')->assertSuccessful();

        $this->assertSame(HoldStatus::Active, TicketHold::query()->where('order_id', $order->id)->first()->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_it_never_touches_converted_holds_or_paid_orders(): void
    {
        $order = $this->checkoutOne();
        // Simulate a completed purchase whose hold was converted, then a (stale) past expiry.
        TicketHold::query()->where('order_id', $order->id)->update([
            'status' => HoldStatus::Converted,
            'expires_at' => now()->subMinutes(20),
        ]);
        $order->update(['status' => OrderStatus::Paid]);

        $this->artisan('holds:release-expired')->assertSuccessful();

        $this->assertSame(HoldStatus::Converted, TicketHold::query()->where('order_id', $order->id)->first()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
