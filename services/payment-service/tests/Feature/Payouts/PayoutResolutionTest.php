<?php

namespace Tests\Feature\Payouts;

use App\Enums\PayoutStatus;
use App\Enums\TransactionType;
use App\Jobs\DeliverPayoutResultJob;
use App\Models\Payout;
use App\Models\Transaction;
use App\Services\Payments\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Payout resolution (CLAUDE.md §B/§G): the configured simulator decides the outcome deterministically
 * (forced) and the result is written once to the append-only `transactions` ledger. A successful payout
 * is money OUT to the vendor (NEGATIVE signed amount); a failed one moved nothing and is recorded as 0.
 * Re-resolving (a job retry) never rolls the gateway or writes the ledger twice.
 */
class PayoutResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function pendingPayout(int $amount = 90_000): Payout
    {
        return Payout::factory()->create([
            'payout_ref' => (string) Str::ulid(),
            'vendor_id' => (string) Str::ulid(),
            'amount' => $amount,
            'currency' => 'BDT',
            'status' => PayoutStatus::Pending->value,
            'gateway_ref' => null,
        ]);
    }

    private function forceGateway(string $outcome): void
    {
        config(['gateways.gateways.stripe_sim.force' => $outcome]); // 'succeed' | 'fail'
    }

    public function test_a_forced_success_marks_the_payout_completed_and_writes_a_negative_ledger_row(): void
    {
        $this->forceGateway('succeed');
        $payout = $this->pendingPayout(90_000);

        $resolved = app(PayoutService::class)->resolve($payout->id);

        $this->assertSame(PayoutStatus::Completed, $resolved->status);
        $this->assertNotNull($resolved->gateway_ref); // a clearly-fake simulated ref — never card data

        $this->assertSame(1, Transaction::count());
        $ledger = Transaction::first();
        $this->assertSame(TransactionType::Payout, $ledger->type);
        $this->assertSame(-90_000, $ledger->amount); // money OUT to vendor — negative signed amount
        $this->assertSame('BDT', $ledger->currency);
        $this->assertNull($ledger->payment_id);     // payout rows have no associated charge
        $this->assertSame($payout->id, $ledger->payout_id);
    }

    public function test_a_forced_failure_marks_the_payout_failed_and_writes_a_zero_ledger_row(): void
    {
        $this->forceGateway('fail');
        $payout = $this->pendingPayout(90_000);

        $resolved = app(PayoutService::class)->resolve($payout->id);

        $this->assertSame(PayoutStatus::Failed, $resolved->status);

        $this->assertSame(1, Transaction::count());
        $ledger = Transaction::first();
        $this->assertSame(TransactionType::Payout, $ledger->type);
        $this->assertSame(0, $ledger->amount); // a failed payout moved no money
        $this->assertNull($ledger->payment_id);
        $this->assertSame($payout->id, $ledger->payout_id);
    }

    public function test_resolving_twice_does_not_roll_the_gateway_or_write_the_ledger_again(): void
    {
        $this->forceGateway('succeed');
        $payout = $this->pendingPayout();

        $first = app(PayoutService::class)->resolve($payout->id);
        $second = app(PayoutService::class)->resolve($payout->id);

        $this->assertSame($first->gateway_ref, $second->gateway_ref); // same outcome, not re-rolled
        $this->assertSame(PayoutStatus::Completed, $second->status);
        $this->assertSame(1, Transaction::count()); // exactly one ledger row across both calls
    }

    public function test_the_job_resolves_then_posts_a_correctly_signed_webhook(): void
    {
        $this->forceGateway('succeed');

        $payout = $this->pendingPayout(90_000);

        $callbackUrl = 'http://core-api.test/api/v1/internal/payments/payout-webhook';
        $secret = 'core-api-hmac-secret';
        $bearer = 'core-api-bearer-token';

        config([
            'services.core_api.payout_callback_url' => $callbackUrl,
            'services.core_api.webhook_secret' => $secret,
            'services.core_api.bearer_token' => $bearer,
        ]);

        Http::fake([
            $callbackUrl => Http::response(['success' => true], 200),
        ]);

        (new DeliverPayoutResultJob($payout->id))->handle(app(PayoutService::class));

        // The payout resolved before the webhook fired (result never lost on delivery failure).
        $this->assertSame(PayoutStatus::Completed, $payout->fresh()->status);

        Http::assertSent(function (Request $request) use ($payout, $callbackUrl, $secret, $bearer): bool {
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, $secret);
            $decoded = json_decode($body, true);

            return $request->url() === $callbackUrl
                && $request->method() === 'POST'
                // Signature verifies with the HMAC secret over the exact bytes sent.
                && $request->hasHeader('X-Signature', $expectedSignature)
                // Bearer is a SEPARATE secret from the HMAC key.
                && $request->hasHeader('Authorization', 'Bearer '.$bearer)
                && $request->hasHeader('Log-Trace-ID')
                // Payload carries references + status only — no card data.
                && $decoded['event'] === 'payout.completed'
                && $decoded['payout_ref'] === $payout->payout_ref
                && $decoded['vendor_id'] === $payout->vendor_id
                && $decoded['status']['value'] === 'completed'
                && $decoded['amount'] === 90_000;
        });
    }
}
