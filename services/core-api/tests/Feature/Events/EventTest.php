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

class EventTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Vendor} */
    private function vendorUser(bool $verified = true): array
    {
        $vendor = $verified
            ? Vendor::factory()->verified()->create()
            : Vendor::factory()->create();

        return [$vendor->user, $vendor];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Dhaka Tech Summit',
            'description' => 'A one-day conference.',
            'timezone' => 'Asia/Dhaka',
            'starts_at' => now()->addDays(20)->toIso8601String(),
            'ends_at' => now()->addDays(20)->addHours(6)->toIso8601String(),
            'capacity' => 300,
        ], $overrides);
    }

    // --- index / visibility ---

    public function test_public_index_lists_only_published_events(): void
    {
        $published = Event::factory()->published()->create();
        $draft = Event::factory()->draft()->create();

        $response = $this->getJson('/api/v1/events')->assertOk()->assertJsonPath('success', true);

        $ids = collect($response->json('data.events'))->pluck('id');
        $this->assertTrue($ids->contains($published->id));
        $this->assertFalse($ids->contains($draft->id));
    }

    public function test_admin_index_lists_all_events(): void
    {
        Event::factory()->published()->create();
        Event::factory()->draft()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/events')->assertOk();
        $this->assertSame(2, $response->json('data.pagination.total'));
    }

    // --- store ---

    public function test_vendor_can_create_event(): void
    {
        [$user, $vendor] = $this->vendorUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/events', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.event.status.value', 'draft')
            ->assertJsonPath('data.event.vendor_id', $vendor->id);

        $this->assertDatabaseHas('events', ['title' => 'Dhaka Tech Summit', 'vendor_id' => $vendor->id]);
    }

    public function test_create_event_validation_fails(): void
    {
        [$user] = $this->vendorUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/events', $this->validPayload([
            'title' => '',
            'capacity' => 0,
            'ends_at' => now()->addDays(5)->toIso8601String(),
            'starts_at' => now()->addDays(10)->toIso8601String(), // starts after ends
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'capacity', 'ends_at']);
    }

    public function test_create_event_requires_authentication(): void
    {
        $this->postJson('/api/v1/events', $this->validPayload())->assertStatus(401);
    }

    public function test_attendee_cannot_create_event(): void
    {
        Sanctum::actingAs(User::factory()->attendee()->create());

        $this->postJson('/api/v1/events', $this->validPayload())->assertStatus(403);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        [$user] = $this->vendorUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/events', $this->validPayload(['timezone' => 'Mars/Phobos']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    // --- show ---

    public function test_show_published_event_is_public(): void
    {
        $event = Event::factory()->published()->create();

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.event.id', $event->id);
    }

    public function test_show_draft_event_is_forbidden_for_other_vendor(): void
    {
        $draft = Event::factory()->draft()->create();
        [$otherUser] = $this->vendorUser();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/events/{$draft->id}")->assertStatus(403);
    }

    public function test_owner_can_view_own_draft_event(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $draft = Event::factory()->draft()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/events/{$draft->id}")->assertOk();
    }

    public function test_show_missing_event_returns_404(): void
    {
        $this->getJson('/api/v1/events/'.Str::ulid())->assertStatus(404);
    }

    // --- update + lifecycle ---

    public function test_vendor_can_update_own_event(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->draft()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.event.title', 'Renamed');
    }

    public function test_vendor_cannot_update_other_vendors_event(): void
    {
        $event = Event::factory()->draft()->create();
        [$otherUser] = $this->vendorUser();
        Sanctum::actingAs($otherUser);

        $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Hijack'])->assertStatus(403);
    }

    public function test_update_rejects_invalid_lifecycle_transition(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->draft()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        // draft may only go to published or cancelled — not completed.
        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'completed'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
    }

    public function test_verified_vendor_can_publish_event(): void
    {
        [$user, $vendor] = $this->vendorUser(verified: true);
        $event = Event::factory()->draft()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.event.status.value', 'published');
    }

    public function test_unverified_vendor_cannot_publish_event(): void
    {
        [$user, $vendor] = $this->vendorUser(verified: false);
        $event = Event::factory()->draft()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}", ['status' => 'published'])
            ->assertStatus(422);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft']);
    }

    public function test_update_missing_event_returns_404(): void
    {
        [$user] = $this->vendorUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/events/'.Str::ulid(), ['title' => 'x'])->assertStatus(404);
    }

    // --- destroy ---

    public function test_vendor_can_delete_own_event(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->draft()->forVendor($vendor)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/events/{$event->id}")->assertOk();
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_vendor_cannot_delete_other_vendors_event(): void
    {
        $event = Event::factory()->draft()->create();
        [$otherUser] = $this->vendorUser();
        Sanctum::actingAs($otherUser);

        $this->deleteJson("/api/v1/events/{$event->id}")->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    // --- capacity invariant on update (STEP 0) ---

    public function test_update_rejected_when_capacity_drops_below_allocated_tickets(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 500]);
        TicketType::factory()->forEvent($event)->create(['quantity_total' => 200]);
        TicketType::factory()->forEvent($event)->create(['quantity_total' => 100]); // allocated = 300
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}", ['capacity' => 200])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'capacity' => 500]);
    }

    public function test_update_allows_lowering_capacity_to_exactly_allocated(): void
    {
        [$user, $vendor] = $this->vendorUser();
        $event = Event::factory()->forVendor($vendor)->create(['capacity' => 500]);
        TicketType::factory()->forEvent($event)->create(['quantity_total' => 200]);
        TicketType::factory()->forEvent($event)->create(['quantity_total' => 100]); // allocated = 300
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/events/{$event->id}", ['capacity' => 300])
            ->assertOk()
            ->assertJsonPath('data.event.capacity', 300);
    }
}
