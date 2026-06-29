<?php

namespace Tests\Feature\Payments;

use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The refund webhook receiver (CLAUDE.md §F/§H; ADR-09/10/13/14/20/23) — the mirror of the charge
 * webhook. A service callback (no Sanctum) authenticated by the SAME shared-secret bearer + raw-body
 * HMAC, idempotent on replay: a completed refund writes ONE signed reversal set, voids tickets, and
 * flips the order's refunded state; a replay is a no-op.
 */
class RefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const BEARER = 'core-api-bearer-token';

    private const SECRET = 'core-api-hmac-secret';

    private const URL = '/api/v1/internal/payments/refund-webhook';

    private const CHARGE_REF = 'pay_sim_ref_[PLACEHOLDER]';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.webhook.bearer_token' => self::BEARER,
            'services.webhook.secret' => self::SECRET,
        ]);

        Queue::fake(); // assert the refund-confirmation publish without running it
    }

    /**
     * A paid order (one vendor) with issued tickets, a succeeded payment, and an OPEN (pending) refund.
     *
     * @return array{order: Order, ticketType: TicketType, payment: Payment, refund: Refund, vendorId: string}
     */
    private function scenario(int $quantity = 2, int $price = 50_000, ?int $refundAmount = null): array
    {
        $event = Event::factory()->published()->create();
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => $price, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => $quantity,
        ]);

        $total = $price * $quantity;
        $order = Order::factory()->paid()->create([
            'attendee_id' => Attendee::factory(), 'total' => $total, 'currency' => 'BDT', 'commission_rate' => '0.1000',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => $quantity, 'unit_price' => $price,
        ]);
        for ($i = 0; $i < $quantity; $i++) {
            Ticket::create([
                'order_id' => $order->id, 'order_item_id' => $item->id, 'ticket_type_id' => $ticketType->id,
                'qr_code' => 'TKT-'.Str::lower((string) Str::ulid()), 'status' => TicketStatus::Valid->value,
            ]);
        }

        $payment = Payment::factory()->create([
            'order_id' => $order->id, 'external_ref' => self::CHARGE_REF,
            'status' => PaymentStatus::Succeeded->value, 'amount' => $total, 'currency' => 'BDT',
        ]);
        $refund = Refund::factory()->pending()->create([
            'payment_id' => $payment->id, 'amount' => $refundAmount ?? $total,
            'policy_applied' => '100', 'reason' => RefundReason::AttendeeRequested->value,
        ]);

        return ['order' => $order, 'ticketType' => $ticketType, 'payment' => $payment, 'refund' => $refund, 'vendorId' => $event->vendor_id];
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
    private function payload(Order $order, Refund $refund, string $status = 'completed'): array
    {
        return [
            'event' => 'refund.'.$status,
            'refund_ref' => 'rfnd_sim_[PLACEHOLDER]',
            'payment_ref' => self::CHARGE_REF,
            'order_id' => $order->id,
            'status' => ['value' => $status, 'label' => ucfirst($status)],
            'amount' => (int) $refund->amount,
            'currency' => $order->currency,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_a_completed_refund_writes_reversal_ledger_voids_tickets_and_marks_refunded(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'refund' => $refund, 'vendorId' => $vendorId] = $this->scenario(quantity: 2, price: 50_000);

        $this->postWebhook($this->payload($order, $refund))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Refund completed; order fully refunded; all tickets voided.
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Refunded->value)->count());
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());

        // Signed reversal, attributed to the vendor, subject = the refund: −refund (sale reversal) and
        // +commission (platform returns its cut). Original sale (+100000/−10000) + this nets to zero.
        $reversal = LedgerEntry::query()->where('subject_type', 'refund')->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->sole();
        $commission = LedgerEntry::query()->where('subject_type', 'refund')->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(-100_000, $reversal->amount);
        $this->assertSame(10_000, $commission->amount);
        $this->assertSame($vendorId, $reversal->vendor_id);
        $this->assertSame($vendorId, $commission->vendor_id);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Clawback->value)->count());

        Queue::assertPushed(SendRefundConfirmationJob::class, 1);
    }

    public function test_a_bad_signature_is_401_and_mutates_nothing(): void
    {
        ['order' => $order, 'refund' => $refund] = $this->scenario();

        $this->postWebhook($this->payload($order, $refund), signingSecret: 'wrong-secret')->assertStatus(401);

        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->count());
        Queue::assertNotPushed(SendRefundConfirmationJob::class);
    }

    public function test_a_missing_bearer_is_401(): void
    {
        ['order' => $order, 'refund' => $refund] = $this->scenario();

        $this->postWebhook($this->payload($order, $refund), bearer: null)->assertStatus(401);

        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
    }

    public function test_a_replayed_completed_webhook_is_a_noop(): void
    {
        ['order' => $order, 'refund' => $refund] = $this->scenario(quantity: 2);
        $payload = $this->payload($order, $refund);

        $this->postWebhook($payload)->assertOk(); // first: resolves
        $this->postWebhook($payload)->assertOk(); // replay: no-op

        // Exactly one reversal set (1 refund + 1 commission), tickets voided once, confirmation once.
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $refund->id)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Refunded->value)->count());
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        Queue::assertPushed(SendRefundConfirmationJob::class, 1);
    }

    public function test_a_failed_refund_writes_no_ledger_and_changes_no_tickets(): void
    {
        ['order' => $order, 'refund' => $refund] = $this->scenario(quantity: 2);

        $this->postWebhook($this->payload($order, $refund, status: 'failed'))->assertOk();

        $this->assertSame(RefundStatus::Failed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status); // unchanged
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());
        Queue::assertNotPushed(SendRefundConfirmationJob::class);
    }

    public function test_an_amount_mismatch_is_rejected_and_mutates_nothing(): void
    {
        ['order' => $order, 'refund' => $refund] = $this->scenario();

        $payload = $this->payload($order, $refund);
        $payload['amount'] = (int) $refund->amount + 1; // does not match the open refund

        $this->postWebhook($payload)->assertStatus(422);

        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->count());
    }

    public function test_a_partial_refund_marks_partially_refunded_and_leaves_tickets_valid(): void
    {
        // 50% of a 100000 order = 50000 refunded — a partial, so the order is partially_refunded and the
        // tickets are NOT voided (the per-item selection isn't persisted on the refund).
        ['order' => $order, 'refund' => $refund] = $this->scenario(quantity: 2, price: 50_000, refundAmount: 50_000);

        $this->postWebhook($this->payload($order, $refund))->assertOk();

        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::PartiallyRefunded, $order->fresh()->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());
        $this->assertSame(-50_000, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->sole()->amount);
        $this->assertSame(5_000, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole()->amount);
    }

    public function test_a_refund_after_payout_writes_a_clawback_instead_of_a_refund_reversal(): void
    {
        ['order' => $order, 'refund' => $refund, 'vendorId' => $vendorId] = $this->scenario(quantity: 2, price: 50_000);

        // The vendor's revenue for this order was ALREADY paid out — a paid payout settled it.
        $payout = Payout::create([
            'vendor_id' => $vendorId, 'gross' => 100_000, 'commission' => 10_000,
            'net' => 90_000, 'payable' => 90_000,
            'reserved_refund' => 0, 'currency' => 'BDT', 'status' => PayoutStatus::Paid->value,
            'batch_id' => '2026-06-30', 'idempotency_key' => (string) Str::uuid(),
        ]);
        PayoutItem::create(['payout_id' => $payout->id, 'order_id' => $order->id, 'settled_amount' => 90_000]);

        $this->postWebhook($this->payload($order, $refund))->assertOk();

        // The vendor-side reversal is a CLAWBACK (recovering disbursed funds), not a plain refund (ADR-20).
        $this->assertSame(-100_000, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Clawback->value)->sole()->amount);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->count());
        $this->assertSame(10_000, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole()->amount);
        $this->assertSame($vendorId, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Clawback->value)->sole()->vendor_id);
    }

    public function test_an_unknown_order_is_a_noop(): void
    {
        ['refund' => $refund] = $this->scenario();

        $payload = $this->payload(Order::factory()->paid()->make(['id' => 'order-does-not-exist']), $refund);
        $payload['order_id'] = '01J9Z0UNKNOWNORDER0000000001';

        $this->postWebhook($payload)->assertOk(); // authentic but unknown → no-op

        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
        Queue::assertNotPushed(SendRefundConfirmationJob::class);
    }

    /**
     * Build a paid order spanning two vendors: vendor A owns 60k of gross, vendor B owns 40k.
     *
     * @return array{order: Order, payment: Payment, refund: Refund, vendorAId: string, vendorBId: string}
     */
    private function twoVendorScenario(int $refundAmount = 100_000): array
    {
        $eventA = Event::factory()->published()->create();
        $ttA = TicketType::factory()->forEvent($eventA)->create([
            'price' => 60_000, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 1,
        ]);

        $eventB = Event::factory()->published()->create();
        $ttB = TicketType::factory()->forEvent($eventB)->create([
            'price' => 40_000, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 1,
        ]);

        $order = Order::factory()->paid()->create([
            'attendee_id' => Attendee::factory(), 'total' => 100_000, 'currency' => 'BDT', 'commission_rate' => '0.1000',
        ]);

        $itemA = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ttA->id, 'quantity' => 1, 'unit_price' => 60_000,
        ]);
        Ticket::create([
            'order_id' => $order->id, 'order_item_id' => $itemA->id, 'ticket_type_id' => $ttA->id,
            'qr_code' => 'TKT-'.Str::lower((string) Str::ulid()), 'status' => TicketStatus::Valid->value,
        ]);

        $itemB = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ttB->id, 'quantity' => 1, 'unit_price' => 40_000,
        ]);
        Ticket::create([
            'order_id' => $order->id, 'order_item_id' => $itemB->id, 'ticket_type_id' => $ttB->id,
            'qr_code' => 'TKT-'.Str::lower((string) Str::ulid()), 'status' => TicketStatus::Valid->value,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id, 'external_ref' => self::CHARGE_REF,
            'status' => PaymentStatus::Succeeded->value, 'amount' => 100_000, 'currency' => 'BDT',
        ]);
        $refund = Refund::factory()->pending()->create([
            'payment_id' => $payment->id, 'amount' => $refundAmount,
            'policy_applied' => '100', 'reason' => RefundReason::AttendeeRequested->value,
        ]);

        return [
            'order' => $order, 'payment' => $payment, 'refund' => $refund,
            'vendorAId' => $eventA->vendor_id, 'vendorBId' => $eventB->vendor_id,
        ];
    }

    public function test_a_completed_refund_splits_proportionally_across_multiple_vendors(): void
    {
        // Two-vendor order: A owns 60k, B owns 40k. Full refund of 100k.
        // allocate(): floor(100k * 60k / 100k) = 60k for A; last-vendor remainder = 40k for B (H-1).
        ['order' => $order, 'refund' => $refund, 'vendorAId' => $vendorA, 'vendorBId' => $vendorB] = $this->twoVendorScenario();

        $this->postWebhook($this->payload($order, $refund))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Vendor A: −60k refund reversal, +6k commission reversal (10% of 60k).
        $reversalA = LedgerEntry::query()->where('vendor_id', $vendorA)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->sole();
        $commA = LedgerEntry::query()->where('vendor_id', $vendorA)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(-60_000, $reversalA->amount);
        $this->assertSame(6_000, $commA->amount);

        // Vendor B: −40k refund reversal, +4k commission reversal (10% of 40k).
        $reversalB = LedgerEntry::query()->where('vendor_id', $vendorB)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->sole();
        $commB = LedgerEntry::query()->where('vendor_id', $vendorB)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(-40_000, $reversalB->amount);
        $this->assertSame(4_000, $commB->amount);

        // 4 ledger rows total; both tickets voided; order fully refunded.
        $this->assertSame(4, LedgerEntry::query()->where('subject_id', $refund->id)->count());
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        Queue::assertPushed(SendRefundConfirmationJob::class, 1);
    }

    public function test_multi_vendor_clawback_for_paid_vendor_and_refund_for_unpaid_vendor(): void
    {
        // Vendor A was already paid out; vendor B was not. A full refund must produce:
        //   vendor A → clawback (recovering disbursed funds, ADR-20)
        //   vendor B → ordinary refund reversal of unsettled revenue
        ['order' => $order, 'refund' => $refund, 'vendorAId' => $vendorA, 'vendorBId' => $vendorB] = $this->twoVendorScenario();

        $payout = Payout::create([
            'vendor_id' => $vendorA, 'gross' => 60_000, 'commission' => 6_000,
            'net' => 54_000, 'payable' => 54_000,
            'reserved_refund' => 0, 'currency' => 'BDT', 'status' => PayoutStatus::Paid->value,
            'batch_id' => '2026-06-30', 'idempotency_key' => (string) Str::uuid(),
        ]);
        PayoutItem::create(['payout_id' => $payout->id, 'order_id' => $order->id, 'settled_amount' => 54_000]);

        $this->postWebhook($this->payload($order, $refund))->assertOk();

        // Vendor A (paid out) → clawback, not refund.
        $this->assertSame(1, LedgerEntry::query()->where('vendor_id', $vendorA)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Clawback->value)->count());
        $this->assertSame(0, LedgerEntry::query()->where('vendor_id', $vendorA)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->count());

        // Vendor B (not paid out) → refund, not clawback.
        $this->assertSame(1, LedgerEntry::query()->where('vendor_id', $vendorB)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Refund->value)->count());
        $this->assertSame(0, LedgerEntry::query()->where('vendor_id', $vendorB)->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Clawback->value)->count());

        // Both vendors still get their commission reversals.
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $refund->id)->where('entry_type', LedgerEntryType::Commission->value)->count());
    }

    public function test_a_refund_on_a_soft_deleted_event_completes_successfully(): void
    {
        // H-3 regression: writeReversalLedger loads ticketType + event withTrashed so a refund on a
        // cancelled event is still resolved correctly and never 500-loops.
        ['order' => $order, 'ticketType' => $ticketType, 'refund' => $refund] = $this->scenario(quantity: 2, price: 50_000);

        $ticketType->event->delete(); // soft-delete the event (simulates an event cancellation)

        $this->postWebhook($this->payload($order, $refund))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $refund->id)->count()); // reversal set still written
    }
}
