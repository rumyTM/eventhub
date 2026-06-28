<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\InitiateChargeJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\ChargeOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The core-api → payment-service charge client + its queued job (CLAUDE.md §F.3/§H, ADR-09/17). The
 * call carries the shared-secret bearer + a deterministic per-attempt Idempotency-Key; a transport
 * failure leaves the order pending and the job retryable; a retry never double-charges.
 */
class InitiateChargeTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'http://payment-service.test';

    private const TOKEN = 'shared-service-token';

    private const ENDPOINT = self::BASE_URL.'/api/v1/payments';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.base_url' => self::BASE_URL,
            'services.payment.service_token' => self::TOKEN,
            'services.payment.default_gateway' => 'stripe_sim',
        ]);
    }

    private function pendingOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'status' => OrderStatus::Pending->value,
            'total' => 25_000,
            'currency' => 'BDT',
        ], $overrides));
    }

    private function runJob(Order $order): void
    {
        (new InitiateChargeJob($order->id))->handle(app(ChargeOrderService::class));
    }

    private function fakeAccepted(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'success' => true,
            'data' => ['payment' => [
                'ref' => 'pay_sim_ref_[PLACEHOLDER]',
                'status' => ['value' => 'pending', 'label' => 'Pending'],
            ]],
        ], 201)]);
    }

    public function test_the_job_posts_the_charge_with_the_correct_auth_idempotency_key_and_body(): void
    {
        $this->fakeAccepted();
        $order = $this->pendingOrder();

        $this->runJob($order);

        Http::assertSent(function (Request $request) use ($order): bool {
            return $request->url() === self::ENDPOINT
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $request->hasHeader('Idempotency-Key', "charge:{$order->id}:attempt:1")
                && $request->hasHeader('Log-Trace-ID')
                && $request['order_id'] === $order->id
                && $request['gateway'] === 'stripe_sim'
                && $request['amount'] === 25_000
                && $request['currency'] === 'BDT';
        });

        // A pending core-api payments row was created with that idempotency key + the gateway ref.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'idempotency_key' => "charge:{$order->id}:attempt:1",
            'status' => PaymentStatus::Pending->value,
            'gateway' => 'stripe_sim',
            'amount' => 25_000,
            'currency' => 'BDT',
            'external_ref' => 'pay_sim_ref_[PLACEHOLDER]',
        ]);
    }

    public function test_a_5xx_leaves_the_order_pending_and_the_job_retryable(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => false], 500)]);
        $order = $this->pendingOrder();

        try {
            $this->runJob($order);
            $this->fail('Expected the charge call to throw so the job retries.');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->response->status());
        }

        // Order is NEVER advanced on a failed charge; the payment row stays pending (webhook resolves it).
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        // The job is configured to retry with backoff (bounded).
        $this->assertSame(5, (new InitiateChargeJob($order->id))->tries);
    }

    public function test_a_timeout_leaves_the_order_pending_and_bubbles_for_retry(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));
        $order = $this->pendingOrder();

        $this->expectException(ConnectionException::class);

        try {
            $this->runJob($order);
        } finally {
            $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        }
    }

    public function test_re_dispatch_reuses_the_same_idempotency_key_and_never_double_charges(): void
    {
        $this->fakeAccepted();
        $order = $this->pendingOrder();

        // Run the job twice for the same order+attempt (a queue retry / duplicate dispatch).
        $this->runJob($order);
        $this->runJob($order);

        // Exactly one core-api payment row — firstOrCreate on the unique idempotency key.
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());

        // The single row keeps its gateway ref (the second run must not clobber it).
        $this->assertSame('pay_sim_ref_[PLACEHOLDER]', Payment::query()->where('order_id', $order->id)->value('external_ref'));

        // Both outbound calls carried the identical Idempotency-Key, so the gateway de-dupes too.
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', "charge:{$order->id}:attempt:1"));
        Http::assertSentCount(2);
    }

    public function test_a_4xx_is_not_retried_and_leaves_the_order_pending(): void
    {
        // A 422 (or any 4xx) is a permanent client-side failure: the job must NOT rethrow (no retry).
        Http::fake([self::ENDPOINT => Http::response(['success' => false], 422)]);
        $order = $this->pendingOrder();

        $this->runJob($order); // must not throw — fast-fail, not a retryable exception

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status); // never advanced
        Http::assertSentCount(1);                                          // tried once, not retried in-band
    }

    public function test_the_job_is_a_noop_when_the_order_is_no_longer_pending(): void
    {
        Http::fake();
        $order = $this->pendingOrder()->fresh();
        $order->update(['status' => OrderStatus::Paid->value]); // already settled via webhook

        $this->runJob($order);

        Http::assertNothingSent();                                   // no charge for a settled order
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
    }
}
