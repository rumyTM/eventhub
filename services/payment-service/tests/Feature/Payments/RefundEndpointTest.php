<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Jobs\DeliverRefundResultJob;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The `POST /api/v1/refunds` surface (CLAUDE.md §C/§F/§H), mirroring the charge endpoint. No route is
 * publicly reachable — the shared-secret middleware rejects a missing token (401) and a wrong token (403)
 * before any business logic. A valid request reserves a `pending` refund and queues the async resolution;
 * the Idempotency-Key (a header) is required and dedupes retries.
 */
class RefundEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-service-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment.service_token' => self::TOKEN]);
    }

    private function charge(int $amount = 25_000): Payment
    {
        return Payment::factory()->succeeded()->create([
            'gateway' => 'stripe_sim', 'amount' => $amount, 'currency' => 'BDT',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Payment $charge, array $overrides = []): array
    {
        return array_merge([
            'payment_ref' => $charge->id,
            'amount' => 10_000,
            'currency' => 'BDT',
            'reason' => 'attendee_requested',
        ], $overrides);
    }

    private function postRefund(array $payload, ?string $token = self::TOKEN, ?string $key = 'refund-key-1')
    {
        $headers = [];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->withHeaders($headers)->postJson('/api/v1/refunds', $payload);
    }

    public function test_a_request_without_a_service_token_is_401(): void
    {
        $this->postRefund($this->payload($this->charge()), token: null)
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_a_request_with_a_wrong_service_token_is_403(): void
    {
        $this->postRefund($this->payload($this->charge()), token: 'wrong-token')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_a_missing_idempotency_key_is_422(): void
    {
        Queue::fake();

        $this->postRefund($this->payload($this->charge()), key: null)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('idempotency_key');

        Queue::assertNothingPushed();
    }

    public function test_a_non_positive_amount_is_422(): void
    {
        $this->postRefund($this->payload($this->charge(), ['amount' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_an_unknown_payment_ref_is_422(): void
    {
        $this->postRefund($this->payload($this->charge(), ['payment_ref' => '01J9Z0UNKNOWN00000000000001']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_ref');
    }

    public function test_a_refund_exceeding_the_original_charge_is_422(): void
    {
        $charge = $this->charge(amount: 10_000);

        $this->postRefund($this->payload($charge, ['amount' => 10_001]))
            ->assertStatus(422); // local sanity guard — never refund more than was charged
    }

    public function test_a_refund_against_a_pending_charge_is_422(): void
    {
        Queue::fake();
        // A pending charge may still fail — there is nothing captured to give back yet.
        $charge = Payment::factory()->create([
            'gateway' => 'stripe_sim', 'status' => PaymentStatus::Pending->value, 'amount' => 25_000, 'currency' => 'BDT',
        ]);

        $this->postRefund($this->payload($charge))->assertStatus(422);

        $this->assertSame(0, Refund::count());
        Queue::assertNothingPushed();
    }

    public function test_a_refund_against_a_failed_charge_is_422(): void
    {
        Queue::fake();
        // A failed charge never captured money — refunding it would invent a negative ledger entry.
        $charge = Payment::factory()->failed()->create([
            'gateway' => 'stripe_sim', 'amount' => 25_000, 'currency' => 'BDT',
        ]);

        $this->postRefund($this->payload($charge))->assertStatus(422);

        $this->assertSame(0, Refund::count());
        Queue::assertNothingPushed();
    }

    public function test_a_refund_with_a_mismatched_currency_is_422(): void
    {
        Queue::fake();
        $charge = $this->charge(); // BDT

        $this->postRefund($this->payload($charge, ['currency' => 'USD']))->assertStatus(422);

        $this->assertSame(0, Refund::count());
        Queue::assertNothingPushed();
    }

    public function test_a_valid_request_creates_a_pending_refund_and_queues_resolution(): void
    {
        Queue::fake();
        $charge = $this->charge();

        $this->postRefund($this->payload($charge))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.refund.status.value', 'pending')
            ->assertJsonPath('data.refund.amount', 10_000)
            ->assertJsonPath('data.refund.payment_ref', $charge->id)
            ->assertJsonPath('data.refund.gateway_ref', null); // not resolved yet — no card field, ever

        $this->assertSame(1, Refund::count());
        Queue::assertPushed(DeliverRefundResultJob::class, 1);
    }

    public function test_the_same_key_and_body_returns_the_same_refund_without_a_second_refund(): void
    {
        Queue::fake();
        $charge = $this->charge();

        $first = $this->postRefund($this->payload($charge), key: 'dupe-refund')->assertStatus(201);
        $second = $this->postRefund($this->payload($charge), key: 'dupe-refund')->assertStatus(201);

        $this->assertSame(
            $first->json('data.refund.ref'),
            $second->json('data.refund.ref'),
        );
        $this->assertSame(1, Refund::count()); // exactly one refund for the key
    }
}
