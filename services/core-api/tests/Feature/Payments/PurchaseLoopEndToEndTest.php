<?php

namespace Tests\Feature\Payments;

use App\Enums\HoldStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Jobs\InitiateChargeJob;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketHold;
use App\Models\TicketType;
use App\Services\Payments\ChargeOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Slice 2, Chunk E — proves the WHOLE purchase loop end-to-end, closing the slice. It drives the real
 * core-api code at every hop and only fakes what genuinely crosses a process boundary:
 *
 *   checkout (real) → InitiateChargeJob → ChargeOrderService → PaymentClient (HTTP — Http::fake the
 *   payment-service charge) → [payment-service resolves the charge] → signed webhook back (constructed
 *   exactly as payment-service's DeliverChargeResultJob signs it) → webhook receiver (real) → order
 *   paid, tickets issued, quantity_sold moved, ledger written, confirmation enqueued.
 *
 * core-api and payment-service are separate Laravel apps with separate databases, so they can't share
 * one in-process test runner; the two cross-service hops (the outbound charge POST and the inbound
 * webhook) are therefore faked/replicated at the wire, while every core-api decision runs for real.
 * The payment-service's own charge/idempotency/webhook-signing logic is covered by its suite.
 */
class PurchaseLoopEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_BASE_URL = 'http://payment-service.test';

    private const PAYMENT_TOKEN = 'shared-service-token';

    private const PAYMENT_ENDPOINT = self::PAYMENT_BASE_URL.'/api/v1/payments';

    private const WEBHOOK_BEARER = 'core-api-bearer-token';

    private const WEBHOOK_SECRET = 'core-api-hmac-secret';

    private const WEBHOOK_URL = '/api/v1/internal/payments/webhook';

    /** The gateway-side ref the (faked) charge returns; the webhook echoes it back as payment_ref. */
    private const GATEWAY_REF = 'pay_sim_ref_[PLACEHOLDER]';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payment.base_url' => self::PAYMENT_BASE_URL,
            'services.payment.service_token' => self::PAYMENT_TOKEN,
            'services.payment.default_gateway' => 'stripe_sim',
            'services.webhook.bearer_token' => self::WEBHOOK_BEARER,
            'services.webhook.secret' => self::WEBHOOK_SECRET,
        ]);

        // Fake the queue so we can (a) assert checkout kicked off the charge, (b) run the charge job
        // deterministically ourselves, and (c) assert the order-confirmation job is enqueued (not sent).
        Queue::fake();
    }

    // --- the cross-service hops, replicated at the wire -------------------------------------------

    /** Stand in for the payment-service accepting a charge: returns pending + a gateway ref. */
    private function fakeChargeAccepted(): void
    {
        Http::fake([self::PAYMENT_ENDPOINT => Http::response([
            'success' => true,
            'data' => ['payment' => [
                'ref' => self::GATEWAY_REF,
                'status' => ['value' => 'pending', 'label' => 'Pending'],
            ]],
        ], 201)]);
    }

    /** Run the real charge job (the work checkout enqueued) against the faked payment-service. */
    private function runChargeJob(string $orderId, int $attempt = 1): void
    {
        (new InitiateChargeJob($orderId, $attempt))->handle(app(ChargeOrderService::class));
    }

    /**
     * Deliver the signed webhook exactly as payment-service's DeliverChargeResultJob would: HMAC over
     * the RAW body keyed by the shared secret, with the bearer token. The receiver re-signs the raw
     * bytes and rejects a mismatch — so this proves the real signature contract, not a re-encode.
     *
     * @param  array<string, mixed>  $payload
     */
    private function deliverWebhook(array $payload): TestResponse
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
    private function resultPayload(Order $order, string $statusValue): array
    {
        return [
            'event' => 'payment.'.$statusValue,
            'payment_ref' => self::GATEWAY_REF, // = the external_ref the charge recorded
            'order_id' => $order->id,
            'status' => ['value' => $statusValue, 'label' => ucfirst($statusValue)],
            'amount' => (int) $order->total,
            'currency' => $order->currency,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /** Real checkout → a pending order with an active hold. Returns the order. */
    private function checkout(int $quantity = 2, int $price = 50_000): Order
    {
        $attendee = Attendee::factory()->create();
        Sanctum::actingAs($attendee->user);

        $event = Event::factory()->published()->create();
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => $price, 'currency' => 'BDT', 'quantity_total' => 100, 'quantity_sold' => 0,
        ]);

        $orderId = $this->withHeader('Idempotency-Key', 'e2e-'.uniqid())
            ->postJson('/api/v1/orders', ['items' => [['ticket_type_id' => $ticketType->id, 'quantity' => $quantity]]])
            ->assertCreated()
            ->assertJsonPath('data.order.status.value', 'pending')
            ->json('data.order.id');

        // Payment is NOT initiated automatically — the attendee calls POST /orders/{id}/pay explicitly.
        Queue::assertNotPushed(InitiateChargeJob::class);

        // Attendee submits payment; this queues the charge job.
        $this->postJson("/api/v1/orders/{$orderId}/pay")->assertOk();
        Queue::assertPushed(InitiateChargeJob::class, fn (InitiateChargeJob $job): bool => $job->orderId === $orderId);

        return Order::findOrFail($orderId);
    }

    // --- the loop ---------------------------------------------------------------------------------

    public function test_a_successful_charge_drives_the_whole_loop_to_issued_tickets_and_a_settled_ledger(): void
    {
        $this->fakeChargeAccepted();

        $order = $this->checkout(quantity: 2, price: 50_000); // total 100000, commission_rate 0.1000

        // --- charge initiation (real job → real client, payment-service faked) ---
        $this->runChargeJob($order->id);

        Http::assertSent(fn (Request $r): bool => $r->url() === self::PAYMENT_ENDPOINT
            && $r->hasHeader('Authorization', 'Bearer '.self::PAYMENT_TOKEN)
            && $r->hasHeader('Idempotency-Key', "charge:{$order->id}:attempt:1"));

        $payment = Payment::query()->where('order_id', $order->id)->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(self::GATEWAY_REF, $payment->external_ref);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status); // not advanced by initiation

        // --- terminal success arrives via the signed webhook ---
        $this->deliverWebhook($this->resultPayload($order, 'succeeded'))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Order paid, payment succeeded, hold converted.
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(1, TicketHold::query()->where('order_id', $order->id)->where('status', HoldStatus::Converted->value)->count());

        // Exactly N valid tickets with distinct QR codes; quantity_sold moved on payment (not checkout).
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->where('status', TicketStatus::Valid->value)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->whereNotNull('qr_code')->distinct('qr_code')->count('qr_code'));
        $this->assertSame(2, $order->items->first()->ticketType->fresh()->quantity_sold);

        // Signed ledger: +sale (full gross) and −commission (10% of 100000).
        $this->assertSame(100_000, LedgerEntry::query()->where('subject_id', $order->id)->where('entry_type', LedgerEntryType::Sale->value)->sole()->amount);
        $this->assertSame(-10_000, LedgerEntry::query()->where('subject_id', $order->id)->where('entry_type', LedgerEntryType::Commission->value)->sole()->amount);

        // Confirmation enqueued (not sent synchronously) — exactly once.
        Queue::assertPushed(SendOrderConfirmationJob::class, 1);
    }

    public function test_a_failed_charge_issues_nothing_and_the_hold_expires(): void
    {
        $this->fakeChargeAccepted();

        $order = $this->checkout(quantity: 2);
        $this->runChargeJob($order->id);
        $ticketTypeId = $order->items->first()->ticket_type_id;

        // Gateway reports failure via the signed webhook.
        $this->deliverWebhook($this->resultPayload($order, 'failed'))->assertOk();

        // Payment failed; NOTHING issued or settled; order left pending for the hold-expiry safety net.
        $this->assertSame(PaymentStatus::Failed, Payment::query()->where('order_id', $order->id)->sole()->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, TicketType::query()->whereKey($ticketTypeId)->value('quantity_sold'));
        $this->assertSame(0, LedgerEntry::query()->where('subject_id', $order->id)->count());
        Queue::assertNotPushed(SendOrderConfirmationJob::class);

        // The 15-min window lapses → the safety-net job releases the hold and expires the order.
        TicketHold::query()->where('order_id', $order->id)->update(['expires_at' => now()->subMinutes(20)]);
        $this->artisan('holds:release-expired')->assertSuccessful();

        $this->assertSame(HoldStatus::Released, TicketHold::query()->where('order_id', $order->id)->sole()->status);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(0, TicketType::query()->whereKey($ticketTypeId)->value('quantity_sold')); // inventory never consumed
    }

    public function test_the_expiry_cron_never_corrupts_a_settled_order(): void
    {
        // Guards the cron-vs-webhook seam: once a webhook has converted the holds and settled the order,
        // a later expiry sweep (even if the holds are now past expires_at) must NOT flip `converted`
        // back to `released`, expire the paid order, or disturb tickets/quantity_sold.
        $this->fakeChargeAccepted();

        $order = $this->checkout(quantity: 2);
        $this->runChargeJob($order->id);
        $ticketTypeId = $order->items->first()->ticket_type_id;

        $this->deliverWebhook($this->resultPayload($order, 'succeeded'))->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        // Back-date the (now converted) holds and run the safety-net cron.
        TicketHold::query()->where('order_id', $order->id)->update(['expires_at' => now()->subMinutes(20)]);
        $this->artisan('holds:release-expired')->assertSuccessful();

        // The converted hold stays converted; the paid order is untouched; inventory unchanged.
        $this->assertSame(HoldStatus::Converted, TicketHold::query()->where('order_id', $order->id)->sole()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, TicketType::query()->whereKey($ticketTypeId)->value('quantity_sold'));
    }

    public function test_the_loop_is_idempotent_under_webhook_replay_and_charge_redispatch(): void
    {
        $this->fakeChargeAccepted();

        $order = $this->checkout(quantity: 2);
        $this->runChargeJob($order->id);
        $ticketTypeId = $order->items->first()->ticket_type_id;

        $payload = $this->resultPayload($order, 'succeeded');

        // Settle, then replay the identical signed webhook (an at-least-once redelivery).
        $this->deliverWebhook($payload)->assertOk();
        $this->deliverWebhook($payload)->assertOk(); // replay → must be a no-op

        // Re-dispatch the charge after settlement (a late queue retry) → no-op for a paid order.
        $this->runChargeJob($order->id);

        // Exactly one set of side effects, no double anything.
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count()); // no second payment row
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());  // not 4
        $this->assertSame(2, TicketType::query()->whereKey($ticketTypeId)->value('quantity_sold')); // not 4
        $this->assertSame(2, LedgerEntry::query()->where('subject_id', $order->id)->count()); // 1 sale + 1 commission, once
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Queue::assertPushed(SendOrderConfirmationJob::class, 1); // queued once across the replay
    }
}
