<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Vendor} */
    private function vendorUser(): array
    {
        $vendor = Vendor::factory()->verified()->create();

        return [$vendor->user, $vendor];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'general',
            'price' => 50000,
            'currency' => 'BDT',
            'quantity_total' => 50,
            'sales_start' => now()->toIso8601String(),
            'sales_end' => now()->addDays(10)->toIso8601String(),
        ], $overrides);
    }

    // --- store ---

    public function test_vendor_can_create_ticket_type(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 500]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/events/{$event->id}/ticket-types", $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.ticket_type.kind.value', 'general')
            ->assertJsonPath('data.ticket_type.price', 50000);

        $this->assertDatabaseHas('ticket_types', ['event_id' => $event->id, 'quantity_total' => 50]);
    }

    public function test_create_ticket_type_validation_fails(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/events/{$event->id}/ticket-types", [
            'kind' => 'platinum',      // not a valid kind
            'price' => -5,             // negative
            'quantity_total' => 0,     // below min
            'group_size' => 3,         // requires group_discount
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kind', 'price', 'quantity_total', 'currency', 'group_discount']);
    }

    public function test_create_ticket_type_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $this->postJson("/api/v1/events/{$event->id}/ticket-types", $this->validPayload())
            ->assertStatus(401);
    }

    public function test_attendee_cannot_create_ticket_type(): void
    {
        $event = Event::factory()->create();
        Sanctum::actingAs(User::factory()->attendee()->create());

        $this->postJson("/api/v1/events/{$event->id}/ticket-types", $this->validPayload())
            ->assertStatus(403);
    }

    public function test_vendor_cannot_create_ticket_type_on_another_vendors_event(): void
    {
        $event = Event::factory()->create(); // owned by some other vendor
        [$otherUser] = $this->vendorUser();
        Sanctum::actingAs($otherUser);

        $this->postJson("/api/v1/events/{$event->id}/ticket-types", $this->validPayload())
            ->assertStatus(403);
    }

    // --- capacity invariant ---

    public function test_create_rejected_when_it_would_exceed_event_capacity(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 100]);
        TicketType::factory()->forEvent($event)->create(['quantity_total' => 80]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/events/{$event->id}/ticket-types", $this->validPayload([
            'quantity_total' => 30, // 80 + 30 > 100
        ]))->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseCount('ticket_types', 1);
    }

    public function test_update_rejected_when_it_would_exceed_event_capacity(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 100]);
        TicketType::factory()->forEvent($event)->create(['quantity_total' => 60]);
        $target = TicketType::factory()->forEvent($event)->create(['quantity_total' => 30]);
        Sanctum::actingAs($user);

        // 60 (other) + 50 (new) = 110 > 100
        $this->putJson("/api/v1/events/{$event->id}/ticket-types/{$target->id}", ['quantity_total' => 50])
            ->assertStatus(422);

        $this->assertDatabaseHas('ticket_types', ['id' => $target->id, 'quantity_total' => 30]);
    }

    public function test_update_rejected_when_quantity_drops_below_sold(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 500]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity_total' => 100,
            'quantity_sold' => 10,
        ]);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}/ticket-types/{$ticketType->id}", ['quantity_total' => 5])
            ->assertStatus(422);

        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id, 'quantity_total' => 100]);
    }

    // --- read / show ---

    public function test_public_can_list_ticket_types_for_published_event(): void
    {
        $event = Event::factory()->published()->create();
        TicketType::factory()->forEvent($event)->count(2)->create();

        $this->getJson("/api/v1/events/{$event->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_listing_ticket_types_of_draft_event_forbidden_for_other_vendor(): void
    {
        $event = Event::factory()->draft()->create();
        TicketType::factory()->forEvent($event)->create();
        [$otherUser] = $this->vendorUser();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/events/{$event->id}/ticket-types")->assertStatus(403);
    }

    public function test_show_ticket_type_for_published_event(): void
    {
        $event = Event::factory()->published()->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();

        $this->getJson("/api/v1/events/{$event->id}/ticket-types/{$ticketType->id}")
            ->assertOk()
            ->assertJsonPath('data.ticket_type.id', $ticketType->id);
    }

    public function test_show_returns_404_when_ticket_type_belongs_to_another_event(): void
    {
        $event = Event::factory()->published()->create();
        $otherEvent = Event::factory()->published()->create();
        $foreign = TicketType::factory()->forEvent($otherEvent)->create();

        // Scoped binding: the ticket type is not a child of {event} → 404.
        $this->getJson("/api/v1/events/{$event->id}/ticket-types/{$foreign->id}")->assertStatus(404);
    }

    public function test_show_missing_ticket_type_returns_404(): void
    {
        $event = Event::factory()->published()->create();

        $this->getJson("/api/v1/events/{$event->id}/ticket-types/".Str::ulid())->assertStatus(404);
    }

    // --- update / delete happy + ownership ---

    public function test_vendor_can_update_own_ticket_type(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 500]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity_total' => 50]);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}/ticket-types/{$ticketType->id}", ['price' => 75000])
            ->assertOk()
            ->assertJsonPath('data.ticket_type.price', 75000);
    }

    public function test_vendor_cannot_update_another_vendors_ticket_type(): void
    {
        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        [$otherUser] = $this->vendorUser();
        Sanctum::actingAs($otherUser);

        $this->putJson("/api/v1/events/{$event->id}/ticket-types/{$ticketType->id}", ['price' => 1])
            ->assertStatus(403);
    }

    public function test_vendor_can_delete_own_ticket_type(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/events/{$event->id}/ticket-types/{$ticketType->id}")->assertOk();
        $this->assertSoftDeleted('ticket_types', ['id' => $ticketType->id]);
    }

    public function test_update_missing_ticket_type_returns_404(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}/ticket-types/".Str::ulid(), ['price' => 1])
            ->assertStatus(404);
    }
}
