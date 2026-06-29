<?php

namespace Tests\Feature\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Jobs\SendPayoutNotificationJob;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The payout webhook receiver (CLAUDE.md §F/§H; ADR-09/10/13/20) — the mirror of the refund webhook.
 * A service callback (no Sanctum) authenticated by the shared-secret bearer + raw-body HMAC, idempotent
 * on replay: a completed payout writes ONE NEGATIVE ledger entry and marks the payout paid; a replay is
 * a no-op. A failed webhook marks the payout failed with no ledger write.
 */
class PayoutWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const BEARER = 'core-api-bearer-token';

    private const SECRET = 'core-api-hmac-secret';

    private const URL = '/api/v1/internal/payments/payout-webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.webhook.bearer_token' => self::BEARER,
            'services.webhook.secret' => self::SECRET,
        ]);

        Queue::fake();
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
            'status' => PayoutStatus::Processing,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload, ?string $bearer = self::BEARER, ?string $signingSecret = self::SECRET): TestResponse
    {
        $body = (string) json_encode($payload);

        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($bearer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }
        if ($signingSecret !== null) {
            $server['HTTP_X_SIGNATURE'] = hash_hmac('sha256', $body, $signingSecret);
        }

        return $this->call('POST', self::URL, [], [], [], $server, $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Payout $payout, string $status = 'completed'): array
    {
        return [
            'event' => 'payout.'.$status,
            'payout_ref' => $payout->id,      // core-api Payout ID — echoed back by payment-service
            'vendor_id' => $payout->vendor_id,
            'status' => ['value' => $status, 'label' => ucfirst($status)],
            'amount' => $payout->payable,
            'currency' => $payout->currency,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_a_completed_payout_marks_paid_writes_negative_ledger_and_queues_notification(): void
    {
        $payout = $this->pendingPayout(90_000);

        $this->postWebhook($this->payload($payout))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Payout is now paid.
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);

        // Exactly ONE payout ledger entry — NEGATIVE (debits vendor balance per ADR-13).
        $this->assertSame(1, LedgerEntry::query()->where('subject_id', $payout->id)->count());
        $entry = LedgerEntry::query()->where('subject_id', $payout->id)->sole();
        $this->assertSame(LedgerEntryType::Payout, $entry->entry_type);
        $this->assertSame(-90_000, $entry->amount);  // negative — money debited from vendor balance
        $this->assertSame('BDT', $entry->currency);
        $this->assertSame($payout->vendor_id, $entry->vendor_id);
        $this->assertSame('payout', $entry->subject_type);

        // Notification is enqueued after commit (fire-and-forget).
        Queue::assertPushed(SendPayoutNotificationJob::class, 1);
    }

    public function test_a_failed_payout_marks_failed_with_no_ledger_and_no_notification(): void
    {
        $payout = $this->pendingPayout(90_000);

        $this->postWebhook($this->payload($payout, status: 'failed'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(PayoutStatus::Failed, $payout->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $payout->id)->count());
        // Vendor is notified on failure too (no money moved, but the status change is still communicated).
        Queue::assertPushed(SendPayoutNotificationJob::class, 1);
    }

    public function test_a_bad_signature_is_401_and_mutates_nothing(): void
    {
        $payout = $this->pendingPayout();

        $this->postWebhook($this->payload($payout), signingSecret: 'wrong-secret')->assertStatus(401);

        $this->assertSame(PayoutStatus::Processing, $payout->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $payout->id)->count());
        Queue::assertNotPushed(SendPayoutNotificationJob::class);
    }

    public function test_a_missing_bearer_is_401(): void
    {
        $payout = $this->pendingPayout();

        $this->postWebhook($this->payload($payout), bearer: null)->assertStatus(401);

        $this->assertSame(PayoutStatus::Processing, $payout->fresh()->status);
    }

    public function test_replay_of_an_already_paid_payout_is_a_no_op(): void
    {
        $payout = $this->pendingPayout();

        // First delivery — marks paid, writes ledger.
        $this->postWebhook($this->payload($payout))->assertOk();
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertSame(1, LedgerEntry::query()->where('subject_id', $payout->id)->count());

        // Re-delivery (payment-service retrying) — must be a no-op: no second ledger row.
        $this->postWebhook($this->payload($payout))->assertOk();
        $this->assertSame(1, LedgerEntry::query()->where('subject_id', $payout->id)->count()); // still exactly one
    }

    public function test_an_amount_mismatch_is_422_and_mutates_nothing(): void
    {
        $payout = $this->pendingPayout(90_000);

        $tampered = $this->payload($payout);
        $tampered['amount'] = 99_999; // differs from payable — tamper guard

        $this->postWebhook($tampered)->assertStatus(422);

        $this->assertSame(PayoutStatus::Processing, $payout->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $payout->id)->count());
    }

    public function test_an_unknown_payout_ref_is_a_no_op(): void
    {
        $unknownId = '01JZZ99999999999999999999X';

        $this->postWebhook([
            'event' => 'payout.completed',
            'payout_ref' => $unknownId,
            'vendor_id' => '01JZZ00000000000000000000V',
            'status' => ['value' => 'completed', 'label' => 'Completed'],
            'amount' => 90_000,
            'currency' => 'BDT',
            'occurred_at' => now()->toIso8601String(),
        ])->assertOk(); // idempotent no-op — never 404 on a stale callback

        $this->assertSame(0, LedgerEntry::count());
        Queue::assertNotPushed(SendPayoutNotificationJob::class);
    }
}
