<?php

namespace Tests\Feature\Orders;

use App\Enums\HoldStatus;
use App\Jobs\InitiateChargeJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Setting;
use App\Models\TicketHold;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Checkout now dispatches the async charge job; fake the queue so these tests stay focused on
        // hold/inventory/idempotency behaviour (the job itself is covered in InitiateChargeTest).
        Queue::fake();
    }

    private function actingAttendee(): Attendee
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        return $attendee;
    }

    private function ticketType(array $overrides = []): TicketType
    {
        $event = Event::factory()->published()->create();

        return TicketType::factory()->forEvent($event)->create(array_merge([
            'price' => 50000,
            'currency' => 'BDT',
            'quantity_total' => 100,
            'quantity_sold' => 0,
        ], $overrides));
    }

    private function checkout(string $key, array $items): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', ['items' => $items]);
    }

    // --- happy path ---

    public function test_checkout_reserves_holds_and_creates_a_pending_order(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType(['price' => 50000, 'quantity_total' => 100]);

        $response = $this->checkout('key-happy', [['ticket_type_id' => $tt->id, 'quantity' => 2]]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status.value', 'pending')
            ->assertJsonPath('data.order.total', 100000)
            ->assertJsonPath('data.order.currency', 'BDT')
            ->assertJsonPath('data.order.commission_rate', '0.1000'); // exact decimal string, not a float

        $this->assertNotNull($response->json('data.order.hold_expires_at'));

        // A pending order with one active hold of qty 2; no tickets issued; quantity_sold untouched.
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseHas('ticket_holds', [
            'ticket_type_id' => $tt->id, 'quantity' => 2, 'status' => HoldStatus::Active->value,
        ]);
        $this->assertDatabaseHas('order_items', ['ticket_type_id' => $tt->id, 'quantity' => 2, 'unit_price' => 50000]);
        $this->assertSame(0, $tt->fresh()->quantity_sold);

        // A pending order kicks off the charge off the request path.
        $orderId = $response->json('data.order.id');
        Queue::assertPushed(InitiateChargeJob::class, fn (InitiateChargeJob $job): bool => $job->orderId === $orderId);
    }

    public function test_commission_rate_snapshot_reads_from_settings_when_present(): void
    {
        Setting::factory()->create(['key' => 'platform_commission_rate', 'value' => '0.15']);
        $this->actingAttendee();
        $tt = $this->ticketType();

        $this->checkout('key-commission', [['ticket_type_id' => $tt->id, 'quantity' => 1]])
            ->assertCreated()
            ->assertJsonPath('data.order.commission_rate', '0.1500');
    }

    // --- group-bundle pricing ---

    public function test_group_bundle_discount_applies_when_quantity_meets_group_size(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType([
            'price' => 1000, 'quantity_total' => 100, 'group_size' => 4, 'group_discount' => 0.25,
        ]);

        // qty >= group_size → unit_price = 1000 * (1 - 0.25) = 750; total = 4 * 750.
        $this->checkout('key-bundle', [['ticket_type_id' => $tt->id, 'quantity' => 4]])
            ->assertCreated()
            ->assertJsonPath('data.order.total', 3000);
    }

    public function test_group_bundle_discount_not_applied_below_group_size(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType([
            'price' => 1000, 'quantity_total' => 100, 'group_size' => 4, 'group_discount' => 0.25,
        ]);

        $this->checkout('key-nobundle', [['ticket_type_id' => $tt->id, 'quantity' => 3]])
            ->assertCreated()
            ->assertJsonPath('data.order.total', 3000); // 3 * 1000, no discount
    }

    // --- validation / authz / edge ---

    public function test_checkout_requires_an_idempotency_key(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType();

        $this->postJson('/api/v1/orders', ['items' => [['ticket_type_id' => $tt->id, 'quantity' => 1]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_checkout_requires_authentication(): void
    {
        $tt = $this->ticketType();

        $this->checkout('key-noauth', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(401);
    }

    public function test_non_attendee_cannot_checkout(): void
    {
        Sanctum::actingAs(Vendor::factory()->create()->user); // a vendor
        $tt = $this->ticketType();

        $this->checkout('key-vendor', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(403);
    }

    public function test_mixed_currency_cart_is_rejected(): void
    {
        $this->actingAttendee();
        $bdt = $this->ticketType(['currency' => 'BDT']);
        $usd = $this->ticketType(['currency' => 'USD']);

        $this->checkout('key-mixed', [
            ['ticket_type_id' => $bdt->id, 'quantity' => 1],
            ['ticket_type_id' => $usd->id, 'quantity' => 1],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0); // nothing persisted on a rejected cart
    }

    public function test_checkout_on_unpublished_event_is_rejected(): void
    {
        $this->actingAttendee();
        $event = Event::factory()->draft()->create();
        $tt = TicketType::factory()->forEvent($event)->create(['quantity_total' => 10]);

        $this->checkout('key-draft', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(422);
    }

    public function test_checkout_on_cancelled_event_is_rejected(): void
    {
        $this->actingAttendee();
        $event = Event::factory()->cancelled()->create();
        $tt = TicketType::factory()->forEvent($event)->create(['quantity_total' => 10]);

        $this->checkout('key-cancelled', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_a_soft_deleted_ticket_type(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType();
        $tt->delete(); // soft delete — the `exists` gate must exclude it (C-2)

        $this->checkout('key-deleted', [['ticket_type_id' => $tt->id, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.ticket_type_id']);
    }

    public function test_checkout_returns_422_when_attendee_profile_is_missing(): void
    {
        // A user with the attendee role but no attendee profile row (inconsistent state) → 422, not 500.
        Sanctum::actingAs(User::factory()->attendee()->create());
        $tt = $this->ticketType();

        $this->checkout('key-noprofile', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(422);
    }

    public function test_checkout_with_closed_sales_window_is_rejected(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType([
            'sales_start' => now()->subDays(10),
            'sales_end' => now()->subDay(), // window already closed
        ]);

        $this->checkout('key-closed', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(422);
    }

    // --- idempotency (ADR-09) ---

    public function test_same_idempotency_key_and_body_returns_the_same_order(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType(['quantity_total' => 100]);
        $items = [['ticket_type_id' => $tt->id, 'quantity' => 2]];

        $first = $this->checkout('key-dup', $items)->assertCreated();
        $second = $this->checkout('key-dup', $items)->assertCreated();

        $this->assertSame($first->json('data.order.id'), $second->json('data.order.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('ticket_holds', 1); // no second hold
    }

    public function test_same_key_with_a_different_body_is_a_conflict(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType(['quantity_total' => 100]);

        $this->checkout('key-conflict', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertCreated();
        $this->checkout('key-conflict', [['ticket_type_id' => $tt->id, 'quantity' => 2]])->assertStatus(409);

        $this->assertDatabaseCount('orders', 1);
    }

    // --- availability + oversell ---

    public function test_expired_hold_is_excluded_from_availability(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType(['quantity_total' => 1]);

        // Claim the only ticket.
        $this->checkout('key-a', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertCreated();
        // No inventory left → 409.
        $this->checkout('key-b', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertStatus(409);

        // Expire the first hold → its inventory frees immediately at read time.
        TicketHold::query()->where('ticket_type_id', $tt->id)
            ->update(['expires_at' => now()->subMinutes(20)]);

        $this->checkout('key-c', [['ticket_type_id' => $tt->id, 'quantity' => 1]])->assertCreated();
    }

    public function test_oversell_is_prevented_across_sequential_checkouts(): void
    {
        // SQLite serializes writes, so this proves the inventory math + availability accounting under the
        // lock ordering; the FOR UPDATE row-lock guarantee against true parallel contention is verified on
        // MySQL (see WORKLOG). The lock-held test below proves the cache-lock front in isolation.
        $this->actingAttendee();
        $tt = $this->ticketType(['quantity_total' => 3]);

        $succeeded = 0;
        $rejected = 0;
        foreach (range(1, 5) as $i) {
            $status = $this->checkout("key-oversell-{$i}", [['ticket_type_id' => $tt->id, 'quantity' => 1]])
                ->getStatusCode();
            $status === 201 ? $succeeded++ : ($status === 409 ? $rejected++ : null);
        }

        $this->assertSame(3, $succeeded);
        $this->assertSame(2, $rejected);

        $activeHeld = (int) TicketHold::query()->where('ticket_type_id', $tt->id)
            ->where('status', HoldStatus::Active->value)->sum('quantity');
        $this->assertLessThanOrEqual($tt->fresh()->quantity_total, $activeHeld + $tt->fresh()->quantity_sold);
        $this->assertSame(3, $activeHeld);
    }

    public function test_checkout_cannot_proceed_while_the_ticket_type_lock_is_held(): void
    {
        $this->actingAttendee();
        $tt = $this->ticketType(['quantity_total' => 10]);

        // Hold the per-ticket_type cache lock that checkout must acquire (ADR-07 distributed front).
        $lock = Cache::lock('checkout:ticket_type:'.$tt->id, 10);
        $this->assertTrue($lock->get());

        try {
            $this->checkout('key-locked', [['ticket_type_id' => $tt->id, 'quantity' => 1]])
                ->assertStatus(409);
            $this->assertDatabaseCount('orders', 0);
        } finally {
            $lock->release();
        }
    }
}
