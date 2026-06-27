<?php

namespace Tests\Feature\Auth;

use App\Enums\KycStatus;
use App\Enums\Role;
use App\Models\Attendee;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // --- Registration: happy paths + role/profile creation ---

    public function test_attendee_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha Attendee',
            'email' => 'asha@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'attendee',
            'phone' => '+8801700000000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role.value', 'attendee')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token']]);

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::where('email', 'asha@example.test')->firstOrFail();
        $this->assertSame(Role::Attendee, $user->role);
        $this->assertDatabaseHas('attendees', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('vendors', ['user_id' => $user->id]);
    }

    public function test_vendor_registration_creates_a_vendor_profile_with_pending_kyc(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Vera Vendor',
            'email' => 'vera@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'vendor',
            'business_name' => 'Vera Events Ltd',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role.value', 'vendor')
            ->assertJsonPath('data.user.vendor.business_name', 'Vera Events Ltd')
            ->assertJsonPath('data.user.vendor.kyc_status.value', 'pending');

        $user = User::where('email', 'vera@example.test')->firstOrFail();
        $vendor = Vendor::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(KycStatus::Pending, $vendor->kyc_status);
    }

    public function test_vendor_registration_without_business_name_fails_validation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Business',
            'email' => 'nobiz@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'vendor',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['business_name']);
    }

    // --- Registration: validation + security ---

    public function test_registration_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.test']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dupe',
            'email' => 'dupe@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'attendee',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_self_assigning_admin_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.test']);
    }

    // --- Login ---

    public function test_user_can_login_and_receive_a_token(): void
    {
        User::factory()->attendee()->create([
            'email' => 'login@example.test',
            'password' => 'password1234',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.test',
            'password' => 'password1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id'], 'token']]);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'real@example.test',
            'password' => 'password1234',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'real@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', null);
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.test',
            'password' => 'password1234',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    // --- me + logout ---

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->vendor()->create();
        Vendor::factory()->create(['user_id' => $user->id, 'business_name' => 'Mine Ltd']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role.value', 'vendor')
            ->assertJsonPath('data.user.vendor.business_name', 'Mine Ltd');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The revoked token can no longer authenticate. Forget the resolved guard so the next request
        // re-reads the (now-deleted) token from the DB instead of the cached in-process user.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    // --- Role gating (EnsureRole) ---

    public function test_attendee_is_blocked_from_admin_route(): void
    {
        Sanctum::actingAs(User::factory()->attendee()->create());

        $this->getJson('/api/v1/admin/ping')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_access_admin_route(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/ping')
            ->assertOk()
            ->assertJsonPath('data.area', 'admin');
    }

    public function test_unrelated_models_satisfy_factory_contract(): void
    {
        // Guards the deferred factories used by seeders/tests later.
        $this->assertInstanceOf(Attendee::class, Attendee::factory()->create());
        $this->assertInstanceOf(Vendor::class, Vendor::factory()->verified()->create());
    }
}
