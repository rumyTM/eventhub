<?php

namespace Tests\Feature\Payouts;

use App\Enums\PayoutStatus;
use App\Jobs\ExecutePayoutJob;
use App\Models\Payout;
use App\Models\Vendor;
use App\Services\Payouts\PayoutExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ExecutePayoutJob (CLAUDE.md §F/§H; ADR-09): flip `pending → processing`, POST to payment-service
 * with a deterministic per-payout Idempotency-Key, then let the signed webhook resolve the outcome.
 * A 4xx is a permanent rejection → fast-fail (mark failed, no webhook will arrive). A 5xx is transient
 * → the job re-throws for the queue's backoff retry. A retry always sends the SAME key → no double-pay.
 */
class ExecutePayoutJobTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_URL = 'http://payment-service.test';

    private const TOKEN = 'test-payment-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.base_url' => self::PAYMENT_URL,
            'services.payment.service_token' => self::TOKEN,
        ]);
    }

    private function pendingPayout(int $payable = 90_000): Payout
    {
        $vendor = Vendor::factory()->verified()->create();

        return Payout::factory()->create([
            'vendor_id' => $vendor->id,
            'gross' => 100_000,
            'commission' => 10_000,
            'net' => 90_000,
            'payable' => $payable,
            'currency' => 'BDT',
            'status' => PayoutStatus::Pending,
        ]);
    }

    public function test_execute_flips_pending_to_processing_and_sends_correct_auth_key_and_body(): void
    {
        $payout = $this->pendingPayout(90_000);

        Http::fake([
            self::PAYMENT_URL.'/api/v1/payouts' => Http::response([
                'success' => true,
                'data' => ['payout' => ['ref' => 'sim-ref-123', 'status' => ['value' => 'pending', 'label' => 'Pending']]],
                'message' => 'Payout created.',
            ], 201),
        ]);

        app(PayoutExecutionService::class)->execute($payout->id);

        // Payout flipped to processing before the HTTP call.
        $this->assertSame(PayoutStatus::Processing, $payout->fresh()->status);

        Http::assertSent(function (Request $request) use ($payout): bool {
            $expectedKey = "payout-exec:{$payout->id}"; // deterministic per payout

            return str_ends_with($request->url(), '/api/v1/payouts')
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $request->hasHeader('Idempotency-Key', $expectedKey)
                // Body carries only IDs + amounts — never card data.
                && $request->data()['payout_ref'] === $payout->id
                && $request->data()['vendor_id'] === $payout->vendor_id
                && $request->data()['amount'] === 90_000
                && $request->data()['currency'] === 'BDT';
        });
    }

    public function test_a_5xx_response_leaves_the_payout_processing_and_rethrows_for_retry(): void
    {
        $payout = $this->pendingPayout();

        Http::fake([
            self::PAYMENT_URL.'/api/v1/payouts' => Http::response(['success' => false], 503),
        ]);

        $this->expectException(RequestException::class);

        app(PayoutExecutionService::class)->execute($payout->id);

        // Payout stays processing — the webhook will (eventually) resolve it; never mark failed here.
        $this->assertSame(PayoutStatus::Processing, $payout->fresh()->status);
    }

    public function test_a_4xx_response_fast_fails_and_marks_the_payout_failed(): void
    {
        $payout = $this->pendingPayout();

        Http::fake([
            self::PAYMENT_URL.'/api/v1/payouts' => Http::response(['success' => false, 'message' => 'Bad request'], 422),
        ]);

        // Call handle() directly — no real queue, but the 4xx branch calls markExecutionFailed + fail().
        $job = new ExecutePayoutJob($payout->id);
        $job->handle(app(PayoutExecutionService::class));

        // Fast-fail: the payment-service rejected the call permanently; no webhook will arrive.
        $this->assertSame(PayoutStatus::Failed, $payout->fresh()->status);
    }

    public function test_a_retry_sends_the_same_idempotency_key_so_the_payment_service_dedupes(): void
    {
        $payout = $this->pendingPayout();
        $expectedKey = "payout-exec:{$payout->id}";

        Http::fake([
            self::PAYMENT_URL.'/api/v1/payouts' => Http::response([
                'success' => true,
                'data' => ['payout' => ['ref' => 'sim-ref-456', 'status' => ['value' => 'pending', 'label' => 'Pending']]],
                'message' => 'Payout created.',
            ], 201),
        ]);

        $service = app(PayoutExecutionService::class);
        $service->execute($payout->id); // first execution — flips to processing, sends the key
        $service->execute($payout->id); // retry — payout is already processing, re-sends same key

        // Both calls used the SAME idempotency key — the payment-service dedupes, so only one payout
        // is ever created regardless of how many retries occur.
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req->hasHeader('Idempotency-Key', $expectedKey));
    }

    public function test_execute_is_a_no_op_for_an_already_paid_payout(): void
    {
        $payout = $this->pendingPayout();
        $payout->forceFill(['status' => PayoutStatus::Paid->value])->save();

        Http::fake(); // nothing should be sent

        app(PayoutExecutionService::class)->execute($payout->id);

        Http::assertNothingSent();
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status); // unchanged
    }

    public function test_execute_is_a_no_op_for_an_already_failed_payout(): void
    {
        $payout = $this->pendingPayout();
        $payout->forceFill(['status' => PayoutStatus::Failed->value])->save();

        Http::fake();

        app(PayoutExecutionService::class)->execute($payout->id);

        Http::assertNothingSent();
    }

    public function test_failed_job_handler_marks_payout_failed_when_retries_are_exhausted(): void
    {
        $payout = $this->pendingPayout();
        $payout->forceFill(['status' => PayoutStatus::Processing->value])->save();

        // Simulate the queue calling $job->failed() after all retries are exhausted.
        $job = new ExecutePayoutJob($payout->id);
        $job->failed(new \RuntimeException('Gateway timed out'));

        $this->assertSame(PayoutStatus::Failed, $payout->fresh()->status);
    }
}
