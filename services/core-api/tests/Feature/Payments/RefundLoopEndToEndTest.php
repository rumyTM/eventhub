<?php

namespace Tests\Feature\Payments;

use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Jobs\ExecuteRefundJob;
use App\Jobs\SendRefundConfirmationJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Vendor;
use App\Services\Refunds\RefundExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Slice 3, Chunk F — full REFUND loop end-to-end. Drives every real core-api decision and fakes only
 * the two hops that genuinely cross a process boundary: the outbound refund POST to payment-service
 * (Http::fake) and the inbound signed webhook (reconstructed byte-for-byte as payment-service signs it).
 *
 * Flow:
 *   paid order → attendee requests refund (real endpoint, real RefundPolicy) →
 *   ExecuteRefundJob runs (real RefundExecutionService, Http::fake) →
 *   signed refund webhook arrives → ProcessRefundWebhookService settles:
 *     SUCCESS → refund completed, reversal+commission ledger, tickets voided, confirmation enqueued
 *     FAILURE → refund failed, no ledger, no ticket/order change
 *
 * Also proves idempotency: webhook replay → zero double ledger rows; job re-dispatch after settlement
 * → zero additional payment-service calls.
 */
class RefundLoopEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_BASE_URL = 'http://payment-service.test';

    private const PAYMENT_TOKEN = 'shared-service-token';

    private const REFUND_ENDPOINT = self::PAYMENT_BASE_URL.'/api/v1/refunds';

    private const WEBHOOK_BEARER = 'core-api-bearer-token';

    private const WEBHOOK_SECRET = 'core-api-hmac-secret';

    private const WEBHOOK_URL = '/api/v1/internal/payments/refund-webhook';

    private const CHARGE_REF = 'pay_sim_ref_[PLACEHOLDER]';

    private const REFUND_SIM_REF = 'rfnd_sim_[PLACEHOLDER]';

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
     * A paid order with issued tickets, a succeeded payment, and no open refund yet. The event starts
     * 72h from now so the 100% policy window applies. Returns everything needed to drive the loop.
     *
     * @return array{order: Order, payment: Payment, attendee: Attendee, ticketType: TicketType}
     */
    private function paidOrderScenario(int $quantity = 2, int $price = 50_000): array
    {
        $attendee = Attendee::factory()->create();

        $event = Event::factory()->published()->create([
            'starts_at' => Carbon::now()->addHours(72), // >48h → 100% refund policy
        ]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => $price, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => $quantity,
        ]);

        $total = $price * $quantity;
        $order = Order::factory()->paid()->create([
            'attendee_id' => $attendee->id,
            'total' => $total,
            'currency' => 'BDT',
            'commission_rate' => '0.1000',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ]);
        for ($i = 0; $i < $quantity; $i++) {
            Ticket::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'ticket_type_id' => $ticketType->id,
                'qr_code' => 'TKT-'.Str::lower((string) Str::ulid()),
                'status' => TicketStatus::Valid->value,
            ]);
        }
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'external_ref' => self::CHARGE_REF,
            'status' => PaymentStatus::Succeeded->value,
            'amount' => $total,
            'currency' => 'BDT',
        ]);

        return compact('order', 'payment', 'attendee', 'ticketType');
    }

    /** Payment-service accepts the refund request and returns a sim ref. */
    private function fakeRefundAccepted(): void
    {
        Http::fake([self::REFUND_ENDPOINT => Http::response([
            'success' => true,
            'data' => ['refund' => [
                'ref' => self::REFUND_SIM_REF,
                'status' => ['value' => 'pending', 'label' => 'Pending'],
            ]],
            'message' => 'Refund created.',
        ], 201)]);
    }

    /** Run the real refund execution job directly (the work the queue would dispatch). */
    private function runRefundJob(string $refundId): void
    {
        (new ExecuteRefundJob($refundId))->handle(app(RefundExecutionService::class));
    }

    /**
     * Deliver the signed refund webhook exactly as payment-service's DeliverRefundResultJob would:
     * HMAC-SHA256 over the raw JSON body, with the bearer token — proves the real auth contract.
     *
     * @param  array<string, mixed>  $payload
     */
    private function deliverRefundWebhook(array $payload): TestResponse
    {
        $body = (string) json_encode($payload);

        return $this->call('POST', self::WEBHOOK_URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.self::WEBHOOK_BEARER,
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(Order $order, Refund $refund, string $status = 'completed'): array
    {
        return [
            'event' => 'refund.'.$status,
            'refund_ref' => self::REFUND_SIM_REF,
            'payment_ref' => self::CHARGE_REF,
            'order_id' => $order->id,
            'status' => ['value' => $status, 'label' => ucfirst($status)],
            'amount' => (int) $refund->amount,
            'currency' => $order->currency,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /** Hit the real attendee refund endpoint; return the newly-created Refund. */
    private function requestRefund(Attendee $attendee, Order $order): Refund
    {
        Sanctum::actingAs($attendee->user);

        $this->postJson("/api/v1/orders/{$order->id}/refund")
            ->assertStatus(202); // 202 Accepted — async execution queued

        $paymentId = Payment::query()->where('order_id', $order->id)->value('id');

        return Refund::query()->where('payment_id', $paymentId)->sole();
    }

    // --- test loop ---

    public function test_full_refund_loop_success_settles_reversal_ledger_voids_tickets_and_queues_confirmation(): void
    {
        ['order' => $order, 'attendee' => $attendee, 'ticketType' => $ticketType] = $this->paidOrderScenario(quantity: 2, price: 50_000);

        // STEP 1 — attendee requests the refund (real endpoint, real policy decision).
        $refund = $this->requestRefund($attendee, $order);
        Queue::assertPushed(ExecuteRefundJob::class);
        // Refund is in 'requested' state — decision taken, no money moved.
        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);

        // STEP 2 — execution job runs (real service; payment-service faked at the wire).
        $this->fakeRefundAccepted();
        $this->runRefundJob($refund->id);

        // Core-api POSTed to payment-service with correct bearer + deterministic idempotency key.
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/api/v1/refunds')
            && $r->hasHeader('Authorization', 'Bearer '.self::PAYMENT_TOKEN)
            && str_starts_with((string) ($r->header('Idempotency-Key')[0] ?? ''), 'refund:')
        );
        // Refund flipped to pending; no money moved yet.
        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->count());

        // STEP 3 — signed success webhook arrives (simulating payment-service callback).
        $this->deliverRefundWebhook($this->webhookPayload($order, $refund->fresh()))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Refund completed; order fully refunded; all tickets voided.
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Refunded->value)->count());
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());
        // Inventory returned: quantity_sold decremented so seats are resellable (ADR-37).
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        // Signed reversal: −100k refund (sale reversal) + +10k commission (platform earns nothing on refund).
        $reversal = LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->sole();
        $commission = LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(-100_000, $reversal->amount);
        $this->assertSame(10_000, $commission->amount);

        // Confirmation enqueued exactly once (never sent synchronously, never doubled).
        Queue::assertPushed(SendRefundConfirmationJob::class, 1);
    }

    public function test_forced_failure_writes_no_ledger_and_leaves_tickets_and_order_unchanged(): void
    {
        ['order' => $order, 'attendee' => $attendee] = $this->paidOrderScenario(quantity: 2);

        $refund = $this->requestRefund($attendee, $order);
        $this->fakeRefundAccepted();
        $this->runRefundJob($refund->id);

        // Gateway reports failure via the signed webhook.
        $this->deliverRefundWebhook($this->webhookPayload($order, $refund->fresh(), status: 'failed'))
            ->assertOk();

        // Failure: no money moved, no ledger written, no tickets voided, order still paid.
        $this->assertSame(RefundStatus::Failed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());
        Queue::assertNotPushed(SendRefundConfirmationJob::class);
    }

    public function test_webhook_replay_does_not_write_a_second_reversal_set_and_notification_queued_once(): void
    {
        ['order' => $order, 'attendee' => $attendee] = $this->paidOrderScenario(quantity: 2);

        $refund = $this->requestRefund($attendee, $order);
        $this->fakeRefundAccepted();
        $this->runRefundJob($refund->id);
        $payload = $this->webhookPayload($order, $refund->fresh());

        // First delivery resolves normally.
        $this->deliverRefundWebhook($payload)->assertOk();
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $refund->id)->count()); // reversal + commission

        // Replay (payment-service at-least-once redelivery) — must be a total no-op.
        $this->deliverRefundWebhook($payload)->assertOk();
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $refund->id)->count()); // still exactly 2
        Queue::assertPushed(SendRefundConfirmationJob::class, 1); // enqueued once, not twice
    }

    public function test_execution_job_re_dispatch_after_settlement_does_not_call_payment_service_again(): void
    {
        ['order' => $order, 'attendee' => $attendee] = $this->paidOrderScenario(quantity: 2);

        $refund = $this->requestRefund($attendee, $order);
        $this->fakeRefundAccepted();
        $this->runRefundJob($refund->id);

        // Settle via the signed webhook.
        $this->deliverRefundWebhook($this->webhookPayload($order, $refund->fresh()))->assertOk();
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);

        // Only one payment-service call so far.
        Http::assertSentCount(1);

        // Late job re-dispatch (a queue retry that arrives after the webhook already settled).
        // The service exits early on a terminal refund — no second payment-service call.
        $this->runRefundJob($refund->id);
        Http::assertSentCount(1); // unchanged — the deterministic key would have deduped anyway
    }

    /**
     * ADR-20 clawback path: when the vendor has already received a PAID payout that includes this order,
     * a subsequent refund must write a `Clawback` ledger entry (not a `Refund` reversal). The Clawback
     * signals the reconciliation layer to recover the already-disbursed funds from the vendor.
     *
     * Without this test the H-2 race (concurrent payout webhook vs refund webhook) and the M-2 test gap
     * found by financial-logic-reviewer would remain uncovered.
     */
    public function test_refund_after_paid_payout_writes_clawback_ledger_not_a_refund_reversal(): void
    {
        // Build an explicit vendor so we can link the payout to it.
        $attendee = Attendee::factory()->create();
        $vendor = Vendor::factory()->verified()->create();
        $event = Event::factory()->published()->create([
            'starts_at' => Carbon::now()->addHours(72),
            'vendor_id' => $vendor->id,
        ]);
        $tt = TicketType::factory()->forEvent($event)->create([
            'price' => 100_000, 'currency' => 'BDT', 'quantity_total' => 10, 'quantity_sold' => 1,
        ]);
        $order = Order::factory()->paid()->create([
            'attendee_id' => $attendee->id,
            'total' => 100_000, 'currency' => 'BDT', 'commission_rate' => '0.1000',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $tt->id, 'quantity' => 1, 'unit_price' => 100_000,
        ]);
        Ticket::create([
            'order_id' => $order->id, 'order_item_id' => $item->id, 'ticket_type_id' => $tt->id,
            'qr_code' => 'TKT-clawback-test', 'status' => TicketStatus::Valid->value,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id, 'external_ref' => self::CHARGE_REF,
            'status' => PaymentStatus::Succeeded->value, 'amount' => 100_000, 'currency' => 'BDT',
        ]);

        // The vendor has already been paid out for this order — a PAID payout with a PayoutItem.
        $payout = Payout::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => PayoutStatus::Paid->value,
        ]);
        PayoutItem::create([
            'payout_id' => $payout->id, 'order_id' => $order->id,
            'settled_amount' => 90_000, 'settled_at' => now(),
        ]);

        // Attendee now requests a refund (real endpoint).
        $refund = $this->requestRefund($attendee, $order);
        $this->fakeRefundAccepted();
        $this->runRefundJob($refund->id);

        // Deliver the signed success webhook.
        $this->deliverRefundWebhook($this->webhookPayload($order, $refund->fresh()))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);

        // Must be a CLAWBACK entry (not a Refund reversal) — the vendor owes back the disbursed amount.
        $this->assertSame(0, LedgerEntry::query()
            ->where('subject_id', $refund->id)
            ->where('entry_type', LedgerEntryType::Refund->value)->count());
        $clawback = LedgerEntry::query()
            ->where('subject_id', $refund->id)
            ->where('entry_type', LedgerEntryType::Clawback->value)->sole();
        $this->assertSame(-100_000, $clawback->amount); // negative: vendor owes back the full ticket amount
        $this->assertSame($vendor->id, $clawback->vendor_id);

        // Commission reversal is still written (platform earns nothing on the cancelled sale).
        $commission = LedgerEntry::query()
            ->where('subject_id', $refund->id)
            ->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(10_000, $commission->amount); // positive: platform returns its commission share

        Queue::assertPushed(SendRefundConfirmationJob::class, 1);
    }

    /**
     * ADR-37 regression — 24–48h (50% money-back) path through the full refund loop.
     *
     * Policy refunds 50% of the ticket price (event starts in the 24–48h window). Even though only
     * half the money is returned the behaviour must be:
     *   - Tickets VOIDED  — the attendee is cancelling; policy % governs money, not ticket fate.
     *   - quantity_sold DECREMENTED  — seats return to inventory for immediate resale.
     *   - Order → `partially_refunded`  — money-based (50k refunded < 100k total).
     *   - Reversal ledger nets at the 50k amount (−50k refund, +5k commission).
     */
    public function test_fifty_percent_policy_refund_voids_tickets_returns_inventory_and_marks_partially_refunded(): void
    {
        $attendee = Attendee::factory()->create();

        $event = Event::factory()->published()->create([
            'starts_at' => Carbon::now()->addHours(36), // 24–48h window → 50% policy
        ]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 50_000, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 2,
        ]);

        $total = 100_000; // 2 × 50_000
        $order = Order::factory()->paid()->create([
            'attendee_id' => $attendee->id,
            'total' => $total,
            'currency' => 'BDT',
            'commission_rate' => '0.1000',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50_000,
        ]);
        for ($i = 0; $i < 2; $i++) {
            Ticket::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'ticket_type_id' => $ticketType->id,
                'qr_code' => 'TKT-50pct-'.Str::lower((string) Str::ulid()),
                'status' => TicketStatus::Valid->value,
            ]);
        }
        Payment::factory()->create([
            'order_id' => $order->id,
            'external_ref' => self::CHARGE_REF,
            'status' => PaymentStatus::Succeeded->value,
            'amount' => $total,
            'currency' => 'BDT',
        ]);

        // STEP 1 — attendee requests refund in the 50% window (real endpoint, real policy decision).
        $refund = $this->requestRefund($attendee, $order);
        Queue::assertPushed(ExecuteRefundJob::class);

        // Policy applied 50%: refund amount is exactly half the order total.
        $this->assertSame('50', $refund->fresh()->policy_applied);
        $this->assertSame(50_000, (int) $refund->fresh()->amount);

        // STEP 2 — execution job calls payment-service with the 50k amount.
        $this->fakeRefundAccepted();
        $this->runRefundJob($refund->id);
        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);

        // STEP 3 — signed success webhook arrives (50k amount confirmed by payment-service).
        $this->deliverRefundWebhook($this->webhookPayload($order, $refund->fresh()))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);

        // Tickets VOIDED — attendee is cancelling; policy % governs money, not ticket fate (ADR-37).
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Refunded->value)->count());
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());

        // Inventory returned: seats resellable even though only 50% of the money was returned.
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        // Order: partially_refunded because MONEY refunded (50k) < order total (100k).
        $this->assertSame(OrderStatus::PartiallyRefunded, $order->fresh()->status);

        // Reversal ledger nets at the 50k amount: −50k refund reversal + +5k commission (10% of 50k).
        $reversal = LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->sole();
        $commission = LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(-50_000, $reversal->amount);
        $this->assertSame(5_000, $commission->amount);

        Queue::assertPushed(SendRefundConfirmationJob::class, 1);
    }
}
