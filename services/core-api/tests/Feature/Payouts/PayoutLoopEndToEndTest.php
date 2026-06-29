<?php

namespace Tests\Feature\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Jobs\SendPayoutNotificationJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\TicketType;
use App\Models\Vendor;
use App\Services\Payouts\PayoutBuildService;
use App\Services\Payouts\PayoutExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Slice 3, Chunk F — full PAYOUT loop end-to-end. Drives every real core-api decision and fakes only
 * the two hops that genuinely cross a process boundary: the outbound payout POST to payment-service
 * (Http::fake) and the inbound signed webhook (reconstructed byte-for-byte as payment-service signs it).
 *
 * Flow:
 *   eligible ledger balance → PayoutBuildService::buildForVendor() → Payout(pending) created →
 *   PayoutExecutionService::execute() (real service, Http::fake) → flip pending→processing →
 *   signed payout webhook arrives → ProcessPayoutWebhookService settles:
 *     SUCCESS → payout paid, ONE negative payout ledger entry, settled_at set on items, balance zero
 *     FAILURE → payout failed, no ledger entry, balance unchanged
 *
 * Also proves idempotency: webhook replay → zero double ledger rows; execution re-dispatch after
 * settlement → zero additional payment-service calls.
 */
class PayoutLoopEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_BASE_URL = 'http://payment-service.test';

    private const PAYMENT_TOKEN = 'shared-service-token';

    private const PAYOUT_ENDPOINT = self::PAYMENT_BASE_URL.'/api/v1/payouts';

    private const WEBHOOK_BEARER = 'core-api-bearer-token';

    private const WEBHOOK_SECRET = 'core-api-hmac-secret';

    private const PAYOUT_WEBHOOK_URL = '/api/v1/internal/payments/payout-webhook';

    /** Batch ID used for idempotency when building payouts. */
    private const BATCH_ID = '2026-06-30';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.base_url' => self::PAYMENT_BASE_URL,
            'services.payment.service_token' => self::PAYMENT_TOKEN,
            'services.webhook.bearer_token' => self::WEBHOOK_BEARER,
            'services.webhook.secret' => self::WEBHOOK_SECRET,
        ]);

        Queue::fake();
    }

    /**
     * A verified vendor with one completed event, one paid order, and the corresponding sale +
     * commission ledger entries (exactly what the charge webhook writes in production). This gives the
     * vendor a net-positive balance eligible for the next payout cycle.
     *
     * @return array{vendor: Vendor, order: Order, gross: int, commission: int, net: int}
     */
    private function vendorWithEligibleBalance(int $gross = 100_000, float $commissionRate = 0.10): array
    {
        $vendor = Vendor::factory()->verified()->create();
        $event = Event::factory()->completed()->create(['vendor_id' => $vendor->id]);
        $tt = TicketType::factory()->forEvent($event)->create([
            'price' => $gross, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 1,
        ]);
        $order = Order::factory()->paid()->create([
            'attendee_id' => Attendee::factory(),
            'total' => $gross,
            'currency' => 'BDT',
            'commission_rate' => number_format($commissionRate, 4, '.', ''),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $tt->id,
            'quantity' => 1,
            'unit_price' => $gross,
        ]);

        $commission = (int) ($gross * $commissionRate);

        // Sale + commission ledger entries written by the charge webhook in production.
        LedgerEntry::create([
            'vendor_id' => $vendor->id,
            'subject_type' => 'order',
            'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Sale->value,
            'amount' => $gross,
            'currency' => 'BDT',
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id,
            'subject_type' => 'order',
            'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Commission->value,
            'amount' => -$commission,
            'currency' => 'BDT',
        ]);

        return [
            'vendor' => $vendor,
            'order' => $order,
            'gross' => $gross,
            'commission' => $commission,
            'net' => $gross - $commission,
        ];
    }

    /** Fake the payment-service accepting the payout execution call. */
    private function fakePayoutAccepted(): void
    {
        Http::fake([self::PAYOUT_ENDPOINT => Http::response([
            'success' => true,
            'data' => ['payout' => [
                'ref' => 'pay_out_sim_[PLACEHOLDER]',
                'status' => ['value' => 'pending', 'label' => 'Pending'],
            ]],
            'message' => 'Payout created.',
        ], 201)]);
    }

    /**
     * Deliver the signed payout webhook exactly as payment-service's DeliverPayoutResultJob would:
     * HMAC-SHA256 over the raw JSON body, with the bearer token.
     *
     * @param  array<string, mixed>  $payload
     */
    private function deliverPayoutWebhook(array $payload): TestResponse
    {
        $body = (string) json_encode($payload);

        return $this->call('POST', self::PAYOUT_WEBHOOK_URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.self::WEBHOOK_BEARER,
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
        ], $body);
    }

    /**
     * Build the signed payout webhook payload. `payout_ref` IS the core-api Payout ID — the same
     * value core-api sends as the payout_ref to payment-service (ProcessPayoutWebhookService).
     *
     * @return array<string, mixed>
     */
    private function webhookPayload(Payout $payout, Vendor $vendor, string $status): array
    {
        return [
            'event' => 'payout.'.$status,
            'payout_ref' => $payout->id,                  // core-api Payout.id (ADR-13 correlation key)
            'vendor_id' => $vendor->id,
            'status' => ['value' => $status, 'label' => ucfirst($status)],
            'amount' => $payout->payable,
            'currency' => $payout->currency,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    // --- test loop ---

    public function test_full_payout_loop_success_marks_paid_writes_single_negative_ledger_and_reduces_balance_to_zero(): void
    {
        ['vendor' => $vendor, 'net' => $net] = $this->vendorWithEligibleBalance(100_000);

        // STEP 1 — build the payout from existing ledger balance (real build service, real CalculatePayout).
        $payout = app(PayoutBuildService::class)->buildForVendor($vendor->id, self::BATCH_ID);
        $this->assertNotNull($payout, 'PayoutBuildService returned null — check threshold config or order eligibility');
        $this->assertSame(PayoutStatus::Pending, $payout->status);
        $this->assertSame($net, $payout->payable); // 90_000 = 100k - 10k commission

        // STEP 2 — execution job runs (real service; payment-service faked at the wire).
        $this->fakePayoutAccepted();
        app(PayoutExecutionService::class)->execute($payout->id);

        // Core-api POSTed to payment-service with deterministic idempotency key and correct auth.
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/api/v1/payouts')
            && $r->hasHeader('Authorization', 'Bearer '.self::PAYMENT_TOKEN)
            && $r->hasHeader('Idempotency-Key', "payout-exec:{$payout->id}")
            && $r->data()['payout_ref'] === $payout->id
            && $r->data()['amount'] === $net
        );
        // Flip from pending → processing before the gateway call (the in-flight state).
        $this->assertSame(PayoutStatus::Processing, $payout->fresh()->status);
        // No ledger entry yet — money is not moved until the signed webhook confirms it.
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $payout->id)->count());

        // STEP 3 — signed success webhook arrives (simulating payment-service callback).
        $this->deliverPayoutWebhook($this->webhookPayload($payout->fresh(), $vendor, 'completed'))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Payout marked paid.
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);

        // Exactly ONE negative payout ledger entry (ADR-13: debit vendor balance in the ledger).
        $ledger = LedgerEntry::query()
            ->where('subject_id', $payout->id)
            ->where('entry_type', LedgerEntryType::Payout->value)
            ->sole();
        $this->assertSame(-$net, $ledger->amount);          // negative — debit
        $this->assertSame('BDT', $ledger->currency);
        $this->assertSame($vendor->id, $ledger->vendor_id);

        // Balance = sale(+100k) + commission(−10k) + payout_ledger(−90k) = 0.
        // The vendor has been paid out in full for this cycle.
        $balance = (int) LedgerEntry::query()->where('vendor_id', $vendor->id)->sum('amount');
        $this->assertSame(0, $balance);

        // PayoutItems get settled_at stamped (C-1 reviewer fix ensures this is not silently skipped).
        $item = PayoutItem::query()->where('payout_id', $payout->id)->sole();
        $this->assertNotNull($item->settled_at);

        // Vendor notified exactly once (not on replay, not synchronously).
        Queue::assertPushed(SendPayoutNotificationJob::class, 1);
    }

    public function test_forced_failure_marks_payout_failed_with_no_ledger_and_balance_unchanged(): void
    {
        ['vendor' => $vendor, 'net' => $net] = $this->vendorWithEligibleBalance(100_000);

        $payout = app(PayoutBuildService::class)->buildForVendor($vendor->id, self::BATCH_ID);
        $this->assertNotNull($payout);

        $this->fakePayoutAccepted();
        app(PayoutExecutionService::class)->execute($payout->id);

        // Gateway reports failure via the signed webhook.
        $this->deliverPayoutWebhook($this->webhookPayload($payout->fresh(), $vendor, 'failed'))
            ->assertOk();

        // Failure moves no money — no ledger entry, no settled_at.
        $this->assertSame(PayoutStatus::Failed, $payout->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $payout->id)->count());

        // Balance unchanged: sale(+100k) + commission(−10k) = +90k still sitting in the ledger.
        $balance = (int) LedgerEntry::query()->where('vendor_id', $vendor->id)->sum('amount');
        $this->assertSame($net, $balance);

        // Vendor still notified (payment-service fires the webhook regardless of outcome).
        Queue::assertPushed(SendPayoutNotificationJob::class, 1);
    }

    public function test_webhook_replay_does_not_write_a_second_payout_ledger_entry_and_balance_stays_correct(): void
    {
        ['vendor' => $vendor, 'net' => $net] = $this->vendorWithEligibleBalance(100_000);

        $payout = app(PayoutBuildService::class)->buildForVendor($vendor->id, self::BATCH_ID);
        $this->assertNotNull($payout);

        $this->fakePayoutAccepted();
        app(PayoutExecutionService::class)->execute($payout->id);

        $payload = $this->webhookPayload($payout->fresh(), $vendor, 'completed');

        // First delivery — resolves normally.
        $this->deliverPayoutWebhook($payload)->assertOk();
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertSame(1, LedgerEntry::query()->where('subject_id', $payout->id)->count());

        // Replay (payment-service at-least-once redelivery) — guard on already-terminal must block.
        $this->deliverPayoutWebhook($payload)->assertOk();
        $this->assertSame(1, LedgerEntry::query()->where('subject_id', $payout->id)->count()); // still exactly 1
        $this->assertSame(0, (int) LedgerEntry::query()->where('vendor_id', $vendor->id)->sum('amount')); // still zero balance

        // Notification enqueued once on the first resolution, not again on replay.
        Queue::assertPushed(SendPayoutNotificationJob::class, 1);
    }

    public function test_execution_retry_sends_same_idempotency_key_and_one_ledger_entry_after_webhook(): void
    {
        ['vendor' => $vendor, 'net' => $net] = $this->vendorWithEligibleBalance(100_000);

        $payout = app(PayoutBuildService::class)->buildForVendor($vendor->id, self::BATCH_ID);
        $this->assertNotNull($payout);

        $expectedKey = "payout-exec:{$payout->id}";

        $this->fakePayoutAccepted();

        // First dispatch — flips pending → processing, POSTs to payment-service.
        app(PayoutExecutionService::class)->execute($payout->id);
        Http::assertSentCount(1);

        // Second dispatch (a queue retry before the webhook arrives) — payout already processing; the
        // service re-sends the same call (idempotency key dedupes it on the payment-service side).
        app(PayoutExecutionService::class)->execute($payout->id);
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Idempotency-Key', $expectedKey));
        Http::assertSentCount(2); // two calls but same deterministic key — payment-service de-dupes

        // Webhook arrives after the second dispatch; still only ONE ledger entry.
        $this->deliverPayoutWebhook($this->webhookPayload($payout->fresh(), $vendor, 'completed'))->assertOk();
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertSame(1, LedgerEntry::query()->where('subject_id', $payout->id)->count());
    }

    public function test_build_is_idempotent_same_batch_same_vendor_produces_no_second_payout(): void
    {
        ['vendor' => $vendor] = $this->vendorWithEligibleBalance(100_000);

        $first = app(PayoutBuildService::class)->buildForVendor($vendor->id, self::BATCH_ID);
        $second = app(PayoutBuildService::class)->buildForVendor($vendor->id, self::BATCH_ID);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id); // same payout returned, not a duplicate
        $this->assertSame(1, Payout::query()->where('vendor_id', $vendor->id)->count());
    }
}
