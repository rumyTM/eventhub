<?php

namespace Tests\Feature\Payouts;

use App\Jobs\DeliverPayoutResultJob;
use App\Models\Payout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The `POST /api/v1/payouts` surface (CLAUDE.md §C/§F/§H), mirroring the refund endpoint. No route is
 * publicly reachable — the shared-secret middleware rejects a missing token (401) and a wrong token (403).
 * A valid request reserves a `pending` Payout and queues the async resolution; the Idempotency-Key (a
 * header) is required and dedupes retries. No card data is accepted or returned here.
 */
class PayoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-service-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment.service_token' => self::TOKEN]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'payout_ref' => (string) Str::ulid(),
            'vendor_id' => (string) Str::ulid(),
            'amount' => 90_000,
            'currency' => 'BDT',
        ], $overrides);
    }

    private function postPayout(array $payload, ?string $token = self::TOKEN, ?string $key = 'payout-key-1')
    {
        $headers = [];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->withHeaders($headers)->postJson('/api/v1/payouts', $payload);
    }

    public function test_a_request_without_a_service_token_is_401(): void
    {
        $this->postPayout($this->payload(), token: null)
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_a_request_with_a_wrong_service_token_is_403(): void
    {
        $this->postPayout($this->payload(), token: 'wrong-token')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_a_missing_idempotency_key_is_422(): void
    {
        Queue::fake();

        $this->postPayout($this->payload(), key: null)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('idempotency_key');

        Queue::assertNothingPushed();
    }

    public function test_a_non_positive_amount_is_422(): void
    {
        $this->postPayout($this->payload(['amount' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_missing_payout_ref_is_422(): void
    {
        $this->postPayout($this->payload(['payout_ref' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payout_ref');
    }

    public function test_a_valid_request_creates_a_pending_payout_and_queues_resolution(): void
    {
        Queue::fake();
        $pRef = (string) Str::ulid();

        $this->postPayout($this->payload(['payout_ref' => $pRef]))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payout.status.value', 'pending')
            ->assertJsonPath('data.payout.amount', 90_000)
            ->assertJsonPath('data.payout.payout_ref', $pRef)
            ->assertJsonPath('data.payout.gateway_ref', null); // not resolved yet — never card data

        $this->assertSame(1, Payout::count());
        Queue::assertPushed(DeliverPayoutResultJob::class, 1);
    }

    public function test_the_same_key_and_body_returns_the_same_payout_without_a_second_execution(): void
    {
        Queue::fake();
        $payload = $this->payload();

        $first = $this->postPayout($payload, key: 'dupe-payout-key')->assertStatus(201);
        $second = $this->postPayout($payload, key: 'dupe-payout-key')->assertStatus(201);

        $this->assertSame(
            $first->json('data.payout.ref'),
            $second->json('data.payout.ref'),
        );
        $this->assertSame(1, Payout::count()); // exactly one payout for the key
    }

    public function test_the_same_key_with_a_different_body_is_409(): void
    {
        Queue::fake();

        $this->postPayout($this->payload(['amount' => 90_000]), key: 'conflict-key')->assertStatus(201);
        $this->postPayout($this->payload(['amount' => 99_999]), key: 'conflict-key')->assertStatus(409);

        $this->assertSame(1, Payout::count()); // second request must not create a second payout
    }
}
