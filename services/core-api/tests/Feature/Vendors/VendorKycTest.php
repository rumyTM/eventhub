<?php

namespace Tests\Feature\Vendors;

use App\Enums\KycStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorKycTest extends TestCase
{
    use RefreshDatabase;

    private function documentsPayload(): array
    {
        return [
            'documents' => [
                ['type' => 'trade_license', 'storage_path' => 'kyc/[PLACEHOLDER]/trade_license.pdf'],
                ['type' => 'nid', 'storage_path' => 'kyc/[PLACEHOLDER]/nid.pdf'],
            ],
        ];
    }

    // --- vendor submit ---

    public function test_vendor_can_submit_kyc_for_review(): void
    {
        $vendor = Vendor::factory()->create(); // pending, not yet submitted
        Sanctum::actingAs($vendor->user);

        $response = $this->postJson('/api/v1/vendor/kyc', $this->documentsPayload());

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.vendor.kyc_status.value', 'pending')
            ->assertJsonPath('data.vendor.id', $vendor->id);

        $this->assertNotNull($vendor->fresh()->submitted_at);
        $this->assertDatabaseCount('kyc_documents', 2);
        $this->assertDatabaseHas('kyc_documents', ['vendor_id' => $vendor->id, 'type' => 'nid']);
    }

    public function test_submit_kyc_requires_documents(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/kyc', ['documents' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['documents']);
    }

    public function test_submit_kyc_requires_authentication(): void
    {
        $this->postJson('/api/v1/vendor/kyc', $this->documentsPayload())->assertStatus(401);
    }

    public function test_already_verified_vendor_cannot_resubmit_kyc(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/kyc', $this->documentsPayload())->assertStatus(409);
    }

    // --- review authorization ---

    public function test_vendor_cannot_access_review_routes(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/admin/vendors')->assertStatus(403);
        $this->postJson("/api/v1/admin/vendors/{$vendor->id}/verify")->assertStatus(403);
    }

    public function test_attendee_is_blocked_from_review_routes(): void
    {
        Sanctum::actingAs(User::factory()->attendee()->create());

        $this->getJson('/api/v1/admin/vendors')->assertStatus(403);
    }

    // --- admin review ---

    public function test_admin_can_list_pending_vendors(): void
    {
        $submitted = Vendor::factory()->create(['submitted_at' => now()]); // pending + submitted
        Vendor::factory()->verified()->create();                            // not pending

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/admin/vendors')->assertOk();

        $ids = collect($response->json('data.vendors'))->pluck('id');
        $this->assertTrue($ids->contains($submitted->id));
        $this->assertSame(1, $response->json('data.pagination.total'));
    }

    public function test_admin_can_verify_a_pending_vendor(): void
    {
        $vendor = Vendor::factory()->create(['submitted_at' => now()]);
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/vendors/{$vendor->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.vendor.kyc_status.value', 'verified');

        $fresh = $vendor->fresh();
        $this->assertSame(KycStatus::Verified, $fresh->kyc_status);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_admin_can_reject_a_pending_vendor_with_a_reason(): void
    {
        $vendor = Vendor::factory()->create(['submitted_at' => now()]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/admin/vendors/{$vendor->id}/reject", [
            'rejection_reason' => 'Documents illegible.',
        ])
            ->assertOk()
            ->assertJsonPath('data.vendor.kyc_status.value', 'rejected')
            ->assertJsonPath('data.vendor.rejection_reason', 'Documents illegible.');

        $this->assertSame(KycStatus::Rejected, $vendor->fresh()->kyc_status);
    }

    public function test_reject_requires_a_reason(): void
    {
        $vendor = Vendor::factory()->create(['submitted_at' => now()]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/admin/vendors/{$vendor->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_redeciding_a_terminal_kyc_status_is_rejected(): void
    {
        $vendor = Vendor::factory()->verified()->create(); // terminal
        Sanctum::actingAs(User::factory()->admin()->create());

        // Verifying or rejecting an already-verified vendor is an illegal transition.
        $this->postJson("/api/v1/admin/vendors/{$vendor->id}/verify")->assertStatus(409);
        $this->postJson("/api/v1/admin/vendors/{$vendor->id}/reject", [
            'rejection_reason' => 'changed my mind',
        ])->assertStatus(409);

        $this->assertSame(KycStatus::Verified, $vendor->fresh()->kyc_status);
    }

    // --- PII / data protection ---

    public function test_responses_never_expose_encrypted_kyc_pii(): void
    {
        $vendor = Vendor::factory()->create(['submitted_at' => now()]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson("/api/v1/admin/vendors/{$vendor->id}/verify")->assertOk();

        // The encrypted identifiers + secrets must never appear anywhere in the body.
        $body = $response->getContent();
        foreach (['tin_bin', 'representative_nid', 'payout_account', 'webhook_secret', 'storage_path'] as $field) {
            $this->assertStringNotContainsString($field, $body);
        }

        $vendorPayload = $response->json('data.vendor');
        $this->assertArrayNotHasKey('tin_bin', $vendorPayload);
        $this->assertArrayNotHasKey('representative_nid', $vendorPayload);
        $this->assertArrayNotHasKey('payout_account', $vendorPayload);
        $this->assertArrayNotHasKey('webhook_secret', $vendorPayload);
    }

    public function test_submit_response_excludes_document_storage_paths(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user);

        $response = $this->postJson('/api/v1/vendor/kyc', $this->documentsPayload())->assertStatus(202);

        // Document metadata is returned, but never the storage_path reference (key or value).
        $this->assertStringNotContainsString('storage_path', $response->getContent());
        $this->assertStringNotContainsString('kyc/[PLACEHOLDER]/trade_license.pdf', $response->getContent());
        $this->assertStringNotContainsString('kyc/[PLACEHOLDER]/nid.pdf', $response->getContent());
    }
}
