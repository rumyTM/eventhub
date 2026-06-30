<?php

namespace Tests\Feature\Orders;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderListingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAttendee(): Attendee
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        return $attendee;
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    public function test_attendee_can_list_their_own_orders_only(): void
    {
        $attendeeA = $this->actingAttendee();
        $orderA1 = Order::factory()->paid()->create([
            'attendee_id' => $attendeeA->id,
            'created_at' => now()->subMinutes(5),
        ]);
        $orderA2 = Order::factory()->create([
            'attendee_id' => $attendeeA->id,
            'created_at' => now(),
        ]);

        $attendeeB = Attendee::factory()->create();
        $orderB = Order::factory()->paid()->create(['attendee_id' => $attendeeB->id]);

        $response = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Orders retrieved.');

        // Should see Attendee A's orders, sorted by created_at descending
        $response->assertJsonCount(2, 'data.orders');
        $response->assertJsonPath('data.orders.0.id', $orderA2->id);
        $response->assertJsonPath('data.orders.1.id', $orderA1->id);

        // Should NOT see Attendee B's order
        $orderIds = collect($response->json('data.orders'))->pluck('id');
        $this->assertTrue($orderIds->contains($orderA1->id));
        $this->assertTrue($orderIds->contains($orderA2->id));
        $this->assertFalse($orderIds->contains($orderB->id));
    }

    public function test_attendee_can_view_their_own_order_details(): void
    {
        $attendee = $this->actingAttendee();
        $order = Order::factory()->paid()->create(['attendee_id' => $attendee->id]);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'id', 'status', 'total', 'currency', 'commission_rate', 'items', 'holds', 'hold_expires_at', 'created_at',
                    ],
                ],
            ]);
    }

    public function test_attendee_cannot_view_another_attendees_order_details(): void
    {
        $this->actingAttendee(); // Attendee A
        $otherAttendee = Attendee::factory()->create();
        $order = Order::factory()->paid()->create(['attendee_id' => $otherAttendee->id]); // Attendee B's order

        $this->getJson("/api/v1/orders/{$order->id}")->assertStatus(403);
    }

    public function test_vendor_cannot_view_order_details(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user);

        $attendee = Attendee::factory()->create();
        $order = Order::factory()->paid()->create(['attendee_id' => $attendee->id]);

        $this->getJson("/api/v1/orders/{$order->id}")->assertStatus(403);
    }

    public function test_vendor_sees_empty_orders_list(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user);

        $attendee = Attendee::factory()->create();
        Order::factory()->paid()->create(['attendee_id' => $attendee->id]);

        $response = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data.orders');
    }

    public function test_admin_can_list_all_orders(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $attendeeA = Attendee::factory()->create();
        $orderA = Order::factory()->paid()->create(['attendee_id' => $attendeeA->id]);

        $attendeeB = Attendee::factory()->create();
        $orderB = Order::factory()->create(['attendee_id' => $attendeeB->id]);

        $response = $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data.orders');

        $orderIds = collect($response->json('data.orders'))->pluck('id');
        $this->assertTrue($orderIds->contains($orderA->id));
        $this->assertTrue($orderIds->contains($orderB->id));
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $attendeeA = Attendee::factory()->create();
        $orderA = Order::factory()->paid()->create(['attendee_id' => $attendeeA->id]);

        $attendeeB = Attendee::factory()->create();
        $orderB = Order::factory()->create(['attendee_id' => $attendeeB->id]);

        $response = $this->getJson('/api/v1/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders');

        $this->assertSame($orderA->id, $response->json('data.orders.0.id'));
    }

    public function test_admin_can_view_any_order_details(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $attendee = Attendee::factory()->create();
        $order = Order::factory()->paid()->create(['attendee_id' => $attendee->id]);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order.id', $order->id);
    }

    public function test_listing_rejects_invalid_status_filter(): void
    {
        $this->actingAttendee();

        $this->getJson('/api/v1/orders?status=invalid_status_value')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_unauthenticated_user_cannot_view_order_details(): void
    {
        $attendee = Attendee::factory()->create();
        $order = Order::factory()->paid()->create(['attendee_id' => $attendee->id]);

        $this->getJson("/api/v1/orders/{$order->id}")->assertStatus(401);
    }

    public function test_show_returns_404_for_nonexistent_order(): void
    {
        $this->actingAttendee();

        $this->getJson('/api/v1/orders/00000000000000000000000000')->assertStatus(404);
    }

    public function test_per_page_exceeding_maximum_returns_422(): void
    {
        $this->actingAttendee();

        $this->getJson('/api/v1/orders?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_list_response_contains_pagination_metadata(): void
    {
        $this->actingAttendee();

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }
}
