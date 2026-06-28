<?php

namespace Tests\Feature\Payments;

use App\Enums\HoldStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketHold;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The payment webhook receiver closes the purchase loop (CLAUDE.md §F.3–4; ADR-07/09/13/14/17). It is
 * a service callback (no Sanctum) authenticated by a shared-secret bearer + an HMAC of the raw body,
 * and it must be idempotent: a replayed/late success never double-issues tickets, double-writes the
 * ledger, or re-increments quantity_sold.
 */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const BEARER = 'core-api-bearer-token';

    private const SECRET = 'core-api-hmac-secret';

    private const URL = '/api/v1/internal/payments/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.webhook.bearer_token' => self::BEARER,
            'services.webhook.secret' => self::SECRET,
        ]);

        // Checkout dispatches the async charge job, and a paid order queues a confirmation — fake both.
        Queue::fake();
    }

    /**
     * Drive a real checkout to get a pending order with items + holds, then attach the Chunk-C
     * pending payment row (external_ref = the gateway payment ref the webhook will echo back).
     *
     * @return array{order: Order, ticketType: TicketType, payment: Payment, vendorId: string}
     */
    private function pendingScenario(int $quantity = 2, int $price = 50_000): array
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        $event = Event::factory()->published()->create();
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => $price,
            'currency' => 'BDT',
            'quantity_total' => 100,
            'quantity_sold' => 0,
        ]);

        $orderId = $this->withHeader('Idempotency-Key', 'key-'.uniqid())
            ->postJson('/api/v1/orders', ['items' => [['ticket_type_id' => $ticketType->id, 'quantity' => $quantity]]])
            ->assertCreated()
            ->json('data.order.id');

        $order = Order::findOrFail($orderId);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'external_ref' => 'pay_sim_ref_[PLACEHOLDER]',
            'status' => PaymentStatus::Pending->value,
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        return ['order' => $order, 'ticketType' => $ticketType, 'payment' => $payment, 'vendorId' => $event->vendor_id];
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
    private function successPayload(Order $order, Payment $payment): array
    {
        return [
            'event' => 'payment.succeeded',
            'payment_ref' => $payment->external_ref,
            'order_id' => $order->id,
            'status' => ['value' => 'succeeded', 'label' => 'Succeeded'],
            'amount' => (int) $order->total,
            'currency' => $order->currency,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_a_valid_success_issues_tickets_increments_sold_writes_ledger_and_pays_the_order(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment, 'vendorId' => $vendorId] = $this->pendingScenario(quantity: 2, price: 50_000);

        $this->postWebhook($this->successPayload($order, $payment))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Exactly N valid tickets, each with a QR code; quantity_sold moved here (not at checkout).
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->whereNotNull('qr_code')->distinct('qr_code')->count('qr_code'));
        $this->assertSame(2, $tt->fresh()->quantity_sold);

        // Order paid, payment succeeded, holds converted.
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(0, TicketHold::query()->where('order_id', $order->id)->where('status', HoldStatus::Active->value)->count());
        $this->assertSame(1, TicketHold::query()->where('order_id', $order->id)->where('status', HoldStatus::Converted->value)->count());

        // Signed ledger: +sale (full gross) and −commission (10% of 100000), attributed to the vendor.
        $sale = LedgerEntry::query()->where('subject_id', $order->id)->where('entry_type', LedgerEntryType::Sale->value)->sole();
        $commission = LedgerEntry::query()->where('subject_id', $order->id)->where('entry_type', LedgerEntryType::Commission->value)->sole();
        $this->assertSame(100_000, $sale->amount);
        $this->assertSame(-10_000, $commission->amount);
        $this->assertSame($vendorId, $sale->vendor_id);
        $this->assertSame($vendorId, $commission->vendor_id);

        Queue::assertPushed(SendOrderConfirmationJob::class, 1);
    }

    public function test_a_bad_signature_is_401_and_mutates_nothing(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment] = $this->pendingScenario();

        $this->postWebhook($this->successPayload($order, $payment), signingSecret: 'wrong-secret')
            ->assertStatus(401);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, $tt->fresh()->quantity_sold);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $order->id)->count());
    }

    public function test_a_missing_bearer_token_is_401(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->pendingScenario();

        $this->postWebhook($this->successPayload($order, $payment), bearer: null)
            ->assertStatus(401);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_a_replayed_success_webhook_is_a_noop(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment] = $this->pendingScenario(quantity: 2);
        $payload = $this->successPayload($order, $payment);

        $this->postWebhook($payload)->assertOk(); // first: settles
        $this->postWebhook($payload)->assertOk(); // replay: must be a no-op

        // Still exactly one set of side effects.
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, $tt->fresh()->quantity_sold);
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $order->id)->count()); // 1 sale + 1 commission
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        Queue::assertPushed(SendOrderConfirmationJob::class, 1); // confirmation queued once, not twice
    }

    public function test_a_failure_result_marks_the_payment_failed_and_issues_nothing(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment] = $this->pendingScenario();

        $payload = $this->successPayload($order, $payment);
        $payload['event'] = 'payment.failed';
        $payload['status'] = ['value' => 'failed', 'label' => 'Failed'];

        $this->postWebhook($payload)->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status); // left for the hold-expiry safety net
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, $tt->fresh()->quantity_sold);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $order->id)->count());
        Queue::assertNotPushed(SendOrderConfirmationJob::class);
    }

    public function test_an_amount_mismatch_is_rejected_and_mutates_nothing(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment] = $this->pendingScenario();

        $payload = $this->successPayload($order, $payment);
        $payload['amount'] = (int) $order->total + 1; // does not match what the order owes

        $this->postWebhook($payload)->assertStatus(422);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, $tt->fresh()->quantity_sold);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $order->id)->count());
    }

    public function test_a_success_arriving_after_hold_expiry_issues_nothing_and_does_not_oversell(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment] = $this->pendingScenario(quantity: 2);

        // The 15-min window lapsed before the (successful) charge confirmed. Those seats were already
        // freed for other buyers at read time, so the late webhook must NOT issue tickets / move sold.
        TicketHold::query()->where('order_id', $order->id)->update(['expires_at' => now()->subMinute()]);

        $this->postWebhook($this->successPayload($order, $payment))->assertOk();

        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, $tt->fresh()->quantity_sold);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $order->id)->count());
        // Order stays pending for the expiry net; the charge is recorded succeeded (a refund concern).
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(0, TicketHold::query()->where('order_id', $order->id)->where('status', HoldStatus::Converted->value)->count());
        Queue::assertNotPushed(SendOrderConfirmationJob::class);
    }

    public function test_a_success_with_no_matching_payment_row_is_a_noop(): void
    {
        ['order' => $order, 'ticketType' => $tt, 'payment' => $payment] = $this->pendingScenario();

        $payload = $this->successPayload($order, $payment);
        $payload['payment_ref'] = 'pay_sim_ref_unknown_[PLACEHOLDER]'; // no payment row carries this ref

        $this->postWebhook($payload)->assertOk();

        // Never settle an order without its payment of record.
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, $tt->fresh()->quantity_sold);
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $order->id)->count());
        Queue::assertNotPushed(SendOrderConfirmationJob::class);
    }

    public function test_a_multi_vendor_cart_writes_correct_per_vendor_ledger_rows(): void
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        // Two events → two vendors, same currency so the cart is valid.
        $eventA = Event::factory()->published()->create();
        $eventB = Event::factory()->published()->create();
        $ttA = TicketType::factory()->forEvent($eventA)->create(['price' => 50_000, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 0]);
        $ttB = TicketType::factory()->forEvent($eventB)->create(['price' => 30_000, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 0]);

        $orderId = $this->withHeader('Idempotency-Key', 'key-multi-'.uniqid())
            ->postJson('/api/v1/orders', ['items' => [
                ['ticket_type_id' => $ttA->id, 'quantity' => 2], // vendor A gross = 100000
                ['ticket_type_id' => $ttB->id, 'quantity' => 1], // vendor B gross = 30000
            ]])
            ->assertCreated()
            ->json('data.order.id');

        $order = Order::findOrFail($orderId);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'external_ref' => 'pay_sim_ref_[PLACEHOLDER]',
            'status' => PaymentStatus::Pending->value,
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $this->postWebhook($this->successPayload($order, $payment))->assertOk();

        // Per-vendor: +sale and −commission (10%) for each of the two owning vendors.
        $this->assertSame(4, LedgerEntry::query()->where('subject_id', $order->id)->count());

        $vendorA = $eventA->vendor_id;
        $vendorB = $eventB->vendor_id;
        $this->assertSame(100_000, LedgerEntry::query()->where('vendor_id', $vendorA)->where('entry_type', LedgerEntryType::Sale->value)->sole()->amount);
        $this->assertSame(-10_000, LedgerEntry::query()->where('vendor_id', $vendorA)->where('entry_type', LedgerEntryType::Commission->value)->sole()->amount);
        $this->assertSame(30_000, LedgerEntry::query()->where('vendor_id', $vendorB)->where('entry_type', LedgerEntryType::Sale->value)->sole()->amount);
        $this->assertSame(-3_000, LedgerEntry::query()->where('vendor_id', $vendorB)->where('entry_type', LedgerEntryType::Commission->value)->sole()->amount);

        // Both ticket types' sold counters moved.
        $this->assertSame(2, $ttA->fresh()->quantity_sold);
        $this->assertSame(1, $ttB->fresh()->quantity_sold);
    }
}
