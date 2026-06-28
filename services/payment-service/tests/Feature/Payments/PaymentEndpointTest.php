<?php

namespace Tests\Feature\Payments;

use App\Jobs\DeliverChargeResultJob;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The `POST /api/v1/payments` surface (CLAUDE.md §C/§F/§H). No route is publicly reachable — the
 * shared-secret middleware rejects a missing token (401) and a wrong token (403) before any
 * business logic. A valid request reserves a `pending` charge and queues the async resolution; the
 * Idempotency-Key (a header) is required and dedupes retries.
 */
class PaymentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-service-token';

    protected function setUp(): void
    {
        parent::setUp();

        // The shared secret EnsureServiceToken checks (env only in real life; set here for the test).
        config(['services.payment.service_token' => self::TOKEN]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_id' => '01J9Z0ORDER0000000000000001',
            'gateway' => 'stripe_sim',
            'amount' => 25_000,
            'currency' => 'BDT',
        ], $overrides);
    }

    private function postCharge(array $payload, ?string $token = self::TOKEN, ?string $key = 'idem-key-1')
    {
        $headers = [];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->withHeaders($headers)->postJson('/api/v1/payments', $payload);
    }

    public function test_a_request_without_a_service_token_is_401(): void
    {
        $this->postCharge($this->payload(), token: null)
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_a_request_with_a_wrong_service_token_is_403(): void
    {
        $this->postCharge($this->payload(), token: 'wrong-token')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_a_missing_idempotency_key_is_422(): void
    {
        Queue::fake();

        $this->postCharge($this->payload(), key: null)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('idempotency_key');

        Queue::assertNothingPushed();
    }

    public function test_a_non_positive_amount_is_422(): void
    {
        $this->postCharge($this->payload(['amount' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_an_unknown_gateway_is_422(): void
    {
        $this->postCharge($this->payload(['gateway' => 'visa_direct']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('gateway');
    }

    public function test_a_valid_request_creates_a_pending_charge_and_queues_resolution(): void
    {
        Queue::fake();

        $this->postCharge($this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.status.value', 'pending')
            ->assertJsonPath('data.payment.amount', 25_000)
            ->assertJsonPath('data.payment.gateway_ref', null); // not resolved yet — no card field, ever

        $this->assertSame(1, Payment::count());
        Queue::assertPushed(DeliverChargeResultJob::class, 1);
    }

    public function test_the_same_key_and_body_returns_the_same_payment_without_a_second_charge(): void
    {
        Queue::fake();

        $first = $this->postCharge($this->payload(), key: 'dupe-key')->assertStatus(201);
        $second = $this->postCharge($this->payload(), key: 'dupe-key')->assertStatus(201);

        $this->assertSame(
            $first->json('data.payment.ref'),
            $second->json('data.payment.ref'),
        );
        $this->assertSame(1, Payment::count()); // exactly one charge for the key
    }
}
