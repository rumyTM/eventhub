<?php

namespace Tests\Feature\Refunds;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\ExecuteRefundJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the CLAUDE.md §F / ADR-23 requirement that cancelling a published event refunds every attendee
 * automatically — previously the refund machinery existed but was only reachable one order at a time via
 * the admin endpoint (see EventService::update() -> RefundEventOrdersJob -> EventCancellationRefundService).
 */
class EventCancellationRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Let the event-cancellation batch job run for real (sync queue in tests); only fake the
        // per-refund execution job so no HTTP call to payment-service happens in this chunk.
        Queue::fake([ExecuteRefundJob::class]);
    }

    /** A verified vendor, acting as themselves, owning a published event. */
    private function actingVendorWithEvent(array $eventOverrides = []): array
    {
        $vendor = Vendor::factory()->verified()->create();
        Sanctum::actingAs($vendor->user);
        $event = Event::factory()->published()->forVendor($vendor)->create($eventOverrides);

        return [$vendor, $event];
    }

    /** A paid order (one line) for $attendee against $event, with a succeeded payment for the full total. */
    private function paidOrderFor(Event $event, Attendee $attendee, int $unitPrice = 50000, int $quantity = 2): Order
    {
        $tt = TicketType::factory()->forEvent($event)->create([
            'price' => $unitPrice, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => $quantity,
        ]);

        $total = $unitPrice * $quantity;
        $order = Order::factory()->paid()->create([
            'attendee_id' => $attendee->id, 'total' => $total, 'currency' => 'BDT',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $tt->id, 'quantity' => $quantity, 'unit_price' => $unitPrice,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id, 'status' => PaymentStatus::Succeeded->value, 'amount' => $total, 'currency' => 'BDT',
        ]);

        return $order;
    }

    public function test_cancelling_an_event_refunds_every_paid_order_at_full_amount(): void
    {
        [, $event] = $this->actingVendorWithEvent([
            'starts_at' => Carbon::now()->addHours(6), // <24h — would be 0% for an attendee request
        ]);

        $orderOne = $this->paidOrderFor($event, Attendee::factory()->create());
        $orderTwo = $this->paidOrderFor($event, Attendee::factory()->create());

        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.event.status.value', 'cancelled');

        $this->assertDatabaseCount('refunds', 2);
        foreach ([$orderOne, $orderTwo] as $order) {
            $this->assertDatabaseHas('refunds', [
                'payment_id' => $order->payments()->first()->id,
                'amount' => $order->total,
                'reason' => 'event_cancelled',
                'policy_applied' => '100',
                'status' => RefundStatus::Requested->value,
            ]);
        }
        Queue::assertPushed(ExecuteRefundJob::class, 2);
    }

    public function test_cancellation_refund_skips_orders_that_are_not_paid(): void
    {
        [, $event] = $this->actingVendorWithEvent();
        $tt = TicketType::factory()->forEvent($event)->create();

        $pendingOrder = Order::factory()->create(['status' => OrderStatus::Pending->value]);
        OrderItem::create([
            'order_id' => $pendingOrder->id, 'ticket_type_id' => $tt->id, 'quantity' => 1, 'unit_price' => 1000,
        ]);

        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'cancelled'])->assertOk();

        $this->assertDatabaseCount('refunds', 0);
        Queue::assertNotPushed(ExecuteRefundJob::class);
    }

    public function test_transition_other_than_into_cancelled_does_not_trigger_refunds(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        Sanctum::actingAs($vendor->user);
        $draft = Event::factory()->draft()->forVendor($vendor)->create();

        $liveEvent = Event::factory()->published()->forVendor($vendor)->create();
        $this->paidOrderFor($liveEvent, Attendee::factory()->create());

        $this->putJson("/api/v1/events/{$draft->id}", ['status' => 'published'])->assertOk();

        $this->assertDatabaseCount('refunds', 0);
        Queue::assertNotPushed(ExecuteRefundJob::class);
    }

    public function test_repeat_cancellation_does_not_refund_twice(): void
    {
        [, $event] = $this->actingVendorWithEvent();
        $this->paidOrderFor($event, Attendee::factory()->create());

        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'cancelled'])->assertOk();
        $this->assertDatabaseCount('refunds', 1);

        // Re-submitting the same (no-op) status is allowed but must not re-fire the batch.
        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'cancelled'])->assertOk();

        $this->assertDatabaseCount('refunds', 1);
        Queue::assertPushed(ExecuteRefundJob::class, 1);
    }
}
