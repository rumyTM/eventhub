<?php

namespace Tests\Feature\Payments;

use App\Enums\RefundStatus;
use App\Jobs\DeliverRefundResultJob;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The signed refund webhook callback to core-api (CLAUDE.md §E; ADR-10), mirroring the charge webhook.
 * The result is persisted first, then POSTed with `X-Signature: hmac_sha256(raw_body, shared_secret)` plus
 * the shared-secret bearer and the forwarded `Log-Trace-ID`. The signature must verify against the secret
 * over the EXACT bytes sent, and the body must carry only references + status — never card data.
 */
class RefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'core-api-hmac-secret';

    private const BEARER = 'core-api-bearer-token';

    private const CALLBACK = 'http://core-api.test/api/v1/internal/payments/refunds/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.core_api.webhook_secret' => self::SECRET,
            'services.core_api.bearer_token' => self::BEARER,
            'services.core_api.refund_callback_url' => self::CALLBACK,
            'gateways.gateways.stripe_sim.force' => 'succeed',
        ]);
    }

    public function test_the_job_resolves_then_posts_a_correctly_signed_webhook(): void
    {
        Http::fake([self::CALLBACK => Http::response(['success' => true], 200)]);

        $charge = Payment::factory()->succeeded()->create([
            'gateway' => 'stripe_sim', 'amount' => 25_000, 'currency' => 'BDT',
        ]);
        $refund = Refund::factory()->create([
            'payment_id' => $charge->id,
            'order_id' => $charge->order_id,
            'gateway' => 'stripe_sim',
            'status' => RefundStatus::Pending->value,
            'amount' => 25_000,
            'currency' => 'BDT',
            'gateway_ref' => null,
        ]);

        // Run the job inline (the queued path) — it resolves the refund, then delivers the callback.
        (new DeliverRefundResultJob($refund->id))->handle(app(RefundService::class));

        // The refund resolved before the webhook fired (result is never lost on a delivery failure).
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);

        Http::assertSent(function (Request $request) use ($charge, $refund): bool {
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, self::SECRET);

            $decoded = json_decode($body, true);

            return $request->url() === self::CALLBACK
                && $request->method() === 'POST'
                // Signature verifies with the HMAC secret over the exact bytes sent.
                && $request->hasHeader('X-Signature', $expectedSignature)
                // Bearer is a SEPARATE secret from the HMAC key, and the trace id is forwarded.
                && $request->hasHeader('Authorization', 'Bearer '.self::BEARER)
                && $request->hasHeader('Log-Trace-ID')
                // Payload carries references + status only — no card data.
                && $decoded['event'] === 'refund.completed'
                && $decoded['refund_ref'] === $refund->id
                && $decoded['payment_ref'] === $charge->id
                && $decoded['order_id'] === $charge->order_id
                && $decoded['status']['value'] === 'completed'
                && $decoded['amount'] === 25_000;
        });
    }

    public function test_a_retry_after_a_failed_delivery_resends_without_re_refunding(): void
    {
        // core-api is unreachable on the first attempt (500), then recovers (200). Because the result is
        // persisted BEFORE delivery and resolve() is idempotent, the retry re-sends the webhook without
        // rolling the gateway again or writing a second ledger row.
        Http::fake([self::CALLBACK => Http::sequence()
            ->push(['success' => false], 500)
            ->push(['success' => true], 200),
        ]);

        $charge = Payment::factory()->succeeded()->create([
            'gateway' => 'stripe_sim', 'amount' => 25_000, 'currency' => 'BDT',
        ]);
        $refund = Refund::factory()->create([
            'payment_id' => $charge->id,
            'order_id' => $charge->order_id,
            'gateway' => 'stripe_sim',
            'status' => RefundStatus::Pending->value,
            'amount' => 25_000,
            'currency' => 'BDT',
            'gateway_ref' => null,
        ]);

        // First attempt resolves the refund, then throws on the 500 (a retryable failure).
        try {
            (new DeliverRefundResultJob($refund->id))->handle(app(RefundService::class));
            $this->fail('Expected the first delivery to throw on a 500.');
        } catch (\Throwable) {
            // expected — the queue would schedule a backed-off retry
        }

        // Retry: resolve() short-circuits the already-terminal refund and re-sends the webhook.
        (new DeliverRefundResultJob($refund->id))->handle(app(RefundService::class));

        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        $this->assertSame(1, Transaction::count()); // resolved exactly once across both attempts
        Http::assertSentCount(2);                               // delivery attempted twice
    }

    public function test_a_tampered_body_would_not_match_the_signature(): void
    {
        // Guards the test's own assertion: a different body produces a different HMAC, so the signature
        // genuinely binds the payload (replay/tamper-safe).
        $body = '{"event":"refund.completed","amount":25000}';
        $tampered = '{"event":"refund.completed","amount":99999}';

        $this->assertNotSame(
            hash_hmac('sha256', $body, self::SECRET),
            hash_hmac('sha256', $tampered, self::SECRET),
        );
    }
}
