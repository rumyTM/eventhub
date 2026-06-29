<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Jobs\ExecuteRefundJob;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Refunds\RefundExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The core-api → payment-service refund client + its queued job (CLAUDE.md §F/§H, ADR-09) — the mirror
 * of the charge client. The call carries the shared-secret bearer + a deterministic per-refund
 * Idempotency-Key; the refund is flipped `requested` → `pending` before the call and is NEVER completed
 * here (the webhook resolves it). A 5xx leaves it pending + retryable; a 4xx fast-fails it; a retry never
 * double-refunds.
 */
class ExecuteRefundJobTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'http://payment-service.test';

    private const TOKEN = 'shared-service-token';

    private const ENDPOINT = self::BASE_URL.'/api/v1/refunds';

    private const CHARGE_REF = 'pay_sim_ref_[PLACEHOLDER]';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.base_url' => self::BASE_URL,
            'services.payment.service_token' => self::TOKEN,
        ]);
    }

    private function requestedRefund(int $amount = 25_000): Refund
    {
        $payment = Payment::factory()->create([
            'external_ref' => self::CHARGE_REF, 'status' => PaymentStatus::Succeeded->value,
            'amount' => $amount, 'currency' => 'BDT',
        ]);

        return Refund::factory()->requested()->create([
            'payment_id' => $payment->id, 'amount' => $amount,
            'policy_applied' => '100', 'reason' => RefundReason::AttendeeRequested->value,
        ]);
    }

    private function runJob(Refund $refund): void
    {
        (new ExecuteRefundJob($refund->id))->handle(app(RefundExecutionService::class));
    }

    private function fakeAccepted(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'success' => true,
            'data' => ['refund' => [
                'ref' => 'rfnd_sim_[PLACEHOLDER]',
                'status' => ['value' => 'pending', 'label' => 'Pending'],
            ]],
        ], 201)]);
    }

    public function test_the_job_posts_the_refund_with_the_correct_auth_idempotency_key_and_body(): void
    {
        $this->fakeAccepted();
        $refund = $this->requestedRefund(25_000);

        $this->runJob($refund);

        Http::assertSent(function (Request $request) use ($refund): bool {
            return $request->url() === self::ENDPOINT
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $request->hasHeader('Idempotency-Key', "refund:{$refund->id}")
                && $request->hasHeader('Log-Trace-ID')
                && $request['payment_ref'] === self::CHARGE_REF
                && $request['amount'] === 25_000
                && $request['currency'] === 'BDT'
                && $request['reason'] === 'attendee_requested';
        });

        // Flipped to pending before the call; NEVER completed here (the webhook resolves it).
        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
    }

    public function test_a_5xx_leaves_the_refund_pending_and_the_job_retryable(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['success' => false], 500)]);
        $refund = $this->requestedRefund();

        try {
            $this->runJob($refund);
            $this->fail('Expected the refund call to throw so the job retries.');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->response->status());
        }

        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status); // stays pending, never failed
        $this->assertSame(5, (new ExecuteRefundJob($refund->id))->tries);
    }

    public function test_a_timeout_leaves_the_refund_pending_and_bubbles_for_retry(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));
        $refund = $this->requestedRefund();

        $this->expectException(ConnectionException::class);

        try {
            $this->runJob($refund);
        } finally {
            $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
        }
    }

    public function test_a_4xx_is_not_retried_and_marks_the_refund_failed(): void
    {
        // A 422 (or any 4xx) is a permanent rejection: no webhook will arrive, so the refund is resolved
        // failed locally (no money moved, no ledger) and the job does not rethrow.
        Http::fake([self::ENDPOINT => Http::response(['success' => false], 422)]);
        $refund = $this->requestedRefund();

        $this->runJob($refund); // must not throw — fast-fail

        $this->assertSame(RefundStatus::Failed, $refund->fresh()->status);
        Http::assertSentCount(1); // tried once, not retried in-band
    }

    public function test_re_dispatch_reuses_the_same_idempotency_key_and_never_double_refunds(): void
    {
        $this->fakeAccepted();
        $refund = $this->requestedRefund();

        $this->runJob($refund); // flips to pending, POSTs
        $this->runJob($refund); // retry: still pending, re-POSTs with the same key

        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', "refund:{$refund->id}"));
        Http::assertSentCount(2); // identical key both times → the payment-service de-dupes
    }

    public function test_the_job_is_a_noop_when_the_refund_is_already_terminal(): void
    {
        Http::fake();
        $refund = $this->requestedRefund();
        $refund->update(['status' => RefundStatus::Completed->value]); // already resolved via webhook

        $this->runJob($refund);

        Http::assertNothingSent(); // no refund call for an already-resolved refund
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
    }

    public function test_failed_callback_marks_refund_failed(): void
    {
        // Simulate all retries exhausted (5xx loop): the queue worker calls failed() which must mark
        // the refund failed so the one-open guard on the order is released (C-1/H-5).
        Http::fake();
        $refund = $this->requestedRefund();
        $refund->update(['status' => RefundStatus::Pending->value]); // in-flight when retries ran out

        (new ExecuteRefundJob($refund->id))->failed(new \RuntimeException('connection reset by peer'));

        $this->assertSame(RefundStatus::Failed, $refund->fresh()->status);
        Http::assertNothingSent(); // failed() resolves locally, never calls the payment service
    }
}
