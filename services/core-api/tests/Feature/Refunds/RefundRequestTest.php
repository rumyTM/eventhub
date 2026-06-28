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
use App\Models\Refund;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The execution job is dispatched only after the policy approves; fake the queue so we can assert
        // it is (or is NOT) pushed, and so no execution runs in this chunk (execution is Chunk C).
        Queue::fake();
    }

    /**
     * Build a paid order (one line) for $attendee whose event starts in $startHours hours, with a
     * succeeded payment for the full total. Returns the order with its single order item.
     */
    private function paidOrder(Attendee $attendee, float $startHours = 72, int $unitPrice = 50000, int $quantity = 2, int $soldCounter = 2): array
    {
        $event = Event::factory()->published()->create([
            'starts_at' => Carbon::now()->addMinutes((int) round($startHours * 60)),
        ]);
        $tt = TicketType::factory()->forEvent($event)->create([
            'price' => $unitPrice, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => $soldCounter,
        ]);

        $total = $unitPrice * $quantity;
        $order = Order::factory()->paid()->create([
            'attendee_id' => $attendee->id, 'total' => $total, 'currency' => 'BDT',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $tt->id, 'quantity' => $quantity, 'unit_price' => $unitPrice,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id, 'status' => PaymentStatus::Succeeded->value, 'amount' => $total, 'currency' => 'BDT',
        ]);

        return [$order, $tt, $item];
    }

    private function actingAttendee(): Attendee
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        return $attendee;
    }

    // --- happy path: full refund outside the window (100%) ---

    public function test_attendee_requests_full_refund_and_no_money_moves(): void
    {
        $attendee = $this->actingAttendee();
        [$order, $tt] = $this->paidOrder($attendee, startHours: 72); // >48h → 100%

        $response = $this->postJson("/api/v1/orders/{$order->id}/refund");

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.refund.status.value', RefundStatus::Requested->value)
            ->assertJsonPath('data.refund.reason.value', 'attendee_requested')
            ->assertJsonPath('data.refund.policy_applied', '100')
            ->assertJsonPath('data.refund.amount', 100000);

        // A single requested refund row exists; the execution job is queued.
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseHas('refunds', ['amount' => 100000, 'status' => RefundStatus::Requested->value]);
        Queue::assertPushed(ExecuteRefundJob::class, 1);

        // NO money moved in this chunk: no ledger entry, payment still succeeded, counter untouched,
        // order still paid, no tickets touched.
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame(PaymentStatus::Succeeded, $order->payments()->first()->status);
        $this->assertSame(2, $tt->fresh()->quantity_sold);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_fifty_percent_window_halves_the_amount(): void
    {
        $attendee = $this->actingAttendee();
        [$order] = $this->paidOrder($attendee, startHours: 36); // 24–48h → 50%

        $this->postJson("/api/v1/orders/{$order->id}/refund")
            ->assertStatus(202)
            ->assertJsonPath('data.refund.policy_applied', '50')
            ->assertJsonPath('data.refund.amount', 50000);
    }

    // --- idempotency: one open refund per order ---

    public function test_duplicate_request_returns_the_same_refund_and_queues_one_job(): void
    {
        $attendee = $this->actingAttendee();
        [$order] = $this->paidOrder($attendee, startHours: 72);

        $first = $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(202);
        $second = $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(202);

        $this->assertSame($first->json('data.refund.id'), $second->json('data.refund.id'));
        $this->assertDatabaseCount('refunds', 1);            // no second row
        Queue::assertPushed(ExecuteRefundJob::class, 1);     // job fired only for the created refund
    }

    // --- ineligible / out-of-policy ---

    public function test_request_inside_zero_window_is_rejected_with_no_refund_or_job(): void
    {
        $attendee = $this->actingAttendee();
        [$order] = $this->paidOrder($attendee, startHours: 12); // <24h → 0%

        $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(422);

        $this->assertDatabaseCount('refunds', 0);
        Queue::assertNotPushed(ExecuteRefundJob::class);
    }

    public function test_fully_refunded_order_is_refused_with_no_new_row_or_job(): void
    {
        $attendee = $this->actingAttendee();
        [$order] = $this->paidOrder($attendee, startHours: 72); // would be 100% if anything remained
        $payment = $order->payments()->first();

        // A prior COMPLETED refund already covers the whole charge — the cumulative cap (evaluated under
        // the order row lock) must refuse a further refund, even though the window is wide open.
        Refund::factory()->completed()->create([
            'payment_id' => $payment->id, 'amount' => $order->total, 'policy_applied' => '100',
        ]);

        $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(422);

        $this->assertDatabaseCount('refunds', 1); // only the seeded completed one — no new row
        Queue::assertNotPushed(ExecuteRefundJob::class);
    }

    public function test_cannot_refund_an_unpaid_order(): void
    {
        $attendee = $this->actingAttendee();
        $order = Order::factory()->create(['attendee_id' => $attendee->id, 'status' => OrderStatus::Pending->value]);

        $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(422);
        $this->assertDatabaseCount('refunds', 0);
        Queue::assertNotPushed(ExecuteRefundJob::class);
    }

    // --- partial (subset of tickets) ---

    public function test_partial_refund_of_a_subset_derives_the_subset_amount(): void
    {
        $attendee = $this->actingAttendee();
        // 4 tickets @ 2500; refund 2 of them at 100% → amount 5000.
        [$order, , $item] = $this->paidOrder($attendee, startHours: 72, unitPrice: 2500, quantity: 4, soldCounter: 4);

        $this->postJson("/api/v1/orders/{$order->id}/refund", [
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.refund.amount', 5000)
            ->assertJsonPath('data.refund.policy_applied', '100');
    }

    public function test_partial_quantity_exceeding_the_line_is_rejected(): void
    {
        $attendee = $this->actingAttendee();
        [$order, , $item] = $this->paidOrder($attendee, startHours: 72, quantity: 2, soldCounter: 2);

        $this->postJson("/api/v1/orders/{$order->id}/refund", [
            'items' => [['order_item_id' => $item->id, 'quantity' => 5]], // only 2 on the line
        ])->assertStatus(422);

        $this->assertDatabaseCount('refunds', 0);
    }

    // --- auth / ownership / role ---

    public function test_refund_requires_authentication(): void
    {
        $attendee = Attendee::factory()->create();
        [$order] = $this->paidOrder($attendee, startHours: 72);

        $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(401);
    }

    public function test_attendee_cannot_refund_another_attendees_order(): void
    {
        $this->actingAttendee(); // acting as attendee A
        $other = Attendee::factory()->create();
        [$order] = $this->paidOrder($other, startHours: 72); // order owned by attendee B

        $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(403);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_non_attendee_cannot_use_the_attendee_refund_route(): void
    {
        Sanctum::actingAs(Vendor::factory()->create()->user); // a vendor
        $attendee = Attendee::factory()->create();
        [$order] = $this->paidOrder($attendee, startHours: 72);

        $this->postJson("/api/v1/orders/{$order->id}/refund")->assertStatus(403);
    }

    // --- admin path: cancellation 100% even inside the 0% window (ADR-23) ---

    public function test_admin_can_initiate_cancellation_refund_inside_zero_window(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $attendee = Attendee::factory()->create();
        [$order] = $this->paidOrder($attendee, startHours: 6); // <24h — would be 0% for an attendee

        $this->postJson("/api/v1/admin/orders/{$order->id}/refund", ['reason' => 'event_cancelled'])
            ->assertStatus(202)
            ->assertJsonPath('data.refund.policy_applied', '100')
            ->assertJsonPath('data.refund.reason.value', 'event_cancelled')
            ->assertJsonPath('data.refund.amount', 100000);

        $this->assertDatabaseCount('refunds', 1);
        Queue::assertPushed(ExecuteRefundJob::class, 1);
        $this->assertDatabaseCount('ledger_entries', 0); // still no money movement in this chunk
    }
}
