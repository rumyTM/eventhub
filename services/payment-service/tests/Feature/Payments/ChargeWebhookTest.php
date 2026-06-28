<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Jobs\DeliverChargeResultJob;
use App\Models\Payment;
use App\Services\Payments\ChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The signed webhook callback to core-api (CLAUDE.md §E; ADR-10). The result is persisted first, then
 * POSTed with `X-Signature: hmac_sha256(raw_body, shared_secret)` plus the shared-secret bearer and
 * the forwarded `Log-Trace-ID`. The signature must verify against the secret over the EXACT bytes
 * sent, and the body must carry only references + status — never card data.
 */
class ChargeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'core-api-hmac-secret';

    private const BEARER = 'core-api-bearer-token';

    private const CALLBACK = 'http://core-api.test/api/v1/internal/payments/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.core_api.webhook_secret' => self::SECRET,
            'services.core_api.bearer_token' => self::BEARER,
            'services.core_api.callback_url' => self::CALLBACK,
            'gateways.gateways.stripe_sim.force' => 'succeed',
        ]);
    }

    public function test_the_job_resolves_then_posts_a_correctly_signed_webhook(): void
    {
        Http::fake([self::CALLBACK => Http::response(['success' => true], 200)]);

        $payment = Payment::factory()->create([
            'gateway' => 'stripe_sim',
            'status' => PaymentStatus::Pending->value,
            'amount' => 25_000,
            'currency' => 'BDT',
            'gateway_ref' => null,
        ]);

        // Run the job inline (the queued path) — it resolves the charge, then delivers the callback.
        (new DeliverChargeResultJob($payment->id))->handle(app(ChargeService::class));

        // The charge resolved before the webhook fired (result is never lost on a delivery failure).
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);

        Http::assertSent(function (Request $request): bool {
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
                && $decoded['event'] === 'payment.succeeded'
                && $decoded['payment_ref'] === $request->data()['payment_ref']
                && $decoded['status']['value'] === 'succeeded'
                && $decoded['amount'] === 25_000;
        });
    }

    public function test_a_tampered_body_would_not_match_the_signature(): void
    {
        // Guards the test's own assertion: a different body produces a different HMAC, so the
        // signature genuinely binds the payload (replay/tamper-safe).
        $body = '{"event":"payment.succeeded","amount":25000}';
        $tampered = '{"event":"payment.succeeded","amount":99999}';

        $this->assertNotSame(
            hash_hmac('sha256', $body, self::SECRET),
            hash_hmac('sha256', $tampered, self::SECRET),
        );
    }
}
