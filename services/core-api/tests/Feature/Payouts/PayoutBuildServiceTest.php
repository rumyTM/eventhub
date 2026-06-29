<?php

namespace Tests\Feature\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payouts\PayoutBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for {@see PayoutBuildService} — payout CALCULATION + batch build.
 *
 * No money moves here (that's Chunk E). These tests assert that:
 *   - Pending Payout + PayoutItem rows are created correctly for eligible vendors.
 *   - The operation is idempotent (same vendor + batchId → same payout row, no duplicate).
 *   - Eligibility gates work: completed-event-only, not-already-paid, threshold.
 *   - `buildAll` processes multiple vendors in one call.
 *
 * Each test builds its own DB state via factories + direct creates; no mocks — the full repository
 * stack runs against the test DB (ADR-09: idempotency is a DB guarantee, not a stub).
 */
class PayoutBuildServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayoutBuildService $service;

    private const BATCH = '2026-06-30';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayoutBuildService::class);
    }

    // --- helpers ------------------------------------------------------------------

    /**
     * Create an eligible vendor + completed event + paid order with a sale + commission ledger entry.
     *
     * @return array{vendor: Vendor, event: Event, ticketType: TicketType, order: Order}
     */
    private function eligibleVendorSetup(int $saleAmount = 100_000, int $commissionAmount = -10_000): array
    {
        $vendor = Vendor::factory()->verified()->create();
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => $saleAmount, 'currency' => 'BDT',
        ]);
        $order = Order::factory()->paid()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $saleAmount,
        ]);

        LedgerEntry::create([
            'vendor_id' => $vendor->id,
            'subject_type' => 'order',
            'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Sale->value,
            'amount' => $saleAmount,
            'currency' => 'BDT',
        ]);

        LedgerEntry::create([
            'vendor_id' => $vendor->id,
            'subject_type' => 'order',
            'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Commission->value,
            'amount' => $commissionAmount,
            'currency' => 'BDT',
        ]);

        return compact('vendor', 'event', 'ticketType', 'order');
    }

    // --- happy path ---------------------------------------------------------------

    public function test_creates_payout_and_items_for_eligible_vendor(): void
    {
        ['vendor' => $vendor, 'order' => $order] = $this->eligibleVendorSetup(
            saleAmount: 100_000, commissionAmount: -10_000
        );

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNotNull($payout);
        $this->assertSame(PayoutStatus::Pending, $payout->status);
        $this->assertSame($vendor->id, $payout->vendor_id);
        $this->assertSame(self::BATCH, $payout->batch_id);
        $this->assertSame('BDT', $payout->currency);

        // Gross/commission/net/payable math (ADR-08: integer minor units, no float; H-2: net ≠ payable).
        $this->assertSame(100_000, $payout->gross);
        $this->assertSame(10_000, $payout->commission);
        $this->assertSame(90_000, $payout->net);      // gross − commission
        $this->assertSame(90_000, $payout->payable);  // net + 0 adjustments = same when no refunds

        // One PayoutItem linking the settled order.
        $this->assertDatabaseCount('payout_items', 1);
        $this->assertDatabaseHas('payout_items', [
            'payout_id' => $payout->id,
            'order_id' => $order->id,
        ]);
    }

    // --- idempotency (ADR-09) -----------------------------------------------------

    public function test_is_idempotent_same_batch_id_returns_same_payout(): void
    {
        ['vendor' => $vendor] = $this->eligibleVendorSetup();

        $first = $this->service->buildForVendor($vendor->id, self::BATCH);
        $second = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);

        // Exactly one Payout and one PayoutItem in the DB — no doubles.
        $this->assertDatabaseCount('payouts', 1);
        $this->assertDatabaseCount('payout_items', 1);
    }

    public function test_order_in_pending_payout_is_not_eligible_for_second_pending_payout(): void
    {
        // C-1: a pending payout for this vendor+order must block a second pending payout from the same
        // batch window OR a different one. Double-pending = double-pay the moment both are approved.
        ['vendor' => $vendor] = $this->eligibleVendorSetup();

        $first = $this->service->buildForVendor($vendor->id, '2026-06-30');
        $this->assertNotNull($first);
        $this->assertSame(PayoutStatus::Pending, $first->status);

        // Second call with a DIFFERENT batch_id must return null — the order is already committed to
        // the first pending payout. The eligibility guard now excludes pending/approved/processing payouts.
        $second = $this->service->buildForVendor($vendor->id, '2026-07-31');
        $this->assertNull($second);
        $this->assertDatabaseCount('payouts', 1);
    }

    // --- eligibility gates --------------------------------------------------------

    public function test_returns_null_when_no_eligible_orders_exist(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        // No orders, no events.
        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNull($payout);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_returns_null_when_balance_below_threshold(): void
    {
        ['vendor' => $vendor] = $this->eligibleVendorSetup(
            saleAmount: 5_000, commissionAmount: -500  // payable = 4_500
        );
        // Set threshold higher than the payable balance.
        Setting::create(['key' => 'payout_threshold', 'value' => '10000', 'type' => 'string']);

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNull($payout);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_requires_completed_event_status_for_eligibility(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        // Ongoing event — revenue not yet settled (ADR-20: only completed events).
        $event = Event::factory()->ongoing()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create(['price' => 100_000]);
        $order = Order::factory()->paid()->create();
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ticketType->id,
            'quantity' => 1, 'unit_price' => 100_000,
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Sale->value, 'amount' => 100_000, 'currency' => 'BDT',
        ]);

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNull($payout);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_excludes_orders_already_in_a_paid_payout_for_vendor(): void
    {
        ['vendor' => $vendor, 'order' => $order] = $this->eligibleVendorSetup();

        // Simulate a prior paid payout that already settled this order.
        $priorPayout = Payout::create([
            'vendor_id' => $vendor->id,
            'gross' => 100_000,
            'commission' => 10_000,
            'net' => 90_000,
            'payable' => 90_000,
            'reserved_refund' => 0,
            'currency' => 'BDT',
            'status' => PayoutStatus::Paid->value,
            'batch_id' => '2026-05-31',
            'idempotency_key' => "payout:{$vendor->id}:2026-05-31",
        ]);
        PayoutItem::create([
            'payout_id' => $priorPayout->id,
            'order_id' => $order->id,
            'settled_amount' => 90_000,
        ]);

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        // The order is already settled — nothing left to pay out.
        $this->assertNull($payout);
    }

    public function test_only_includes_paid_and_partially_refunded_orders(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create(['price' => 100_000]);

        // Pending order (checkout not completed) — must NOT appear.
        $pendingOrder = Order::factory()->create(['status' => OrderStatus::Pending->value]);
        OrderItem::create([
            'order_id' => $pendingOrder->id, 'ticket_type_id' => $ticketType->id,
            'quantity' => 1, 'unit_price' => 100_000,
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $pendingOrder->id,
            'entry_type' => LedgerEntryType::Sale->value, 'amount' => 100_000, 'currency' => 'BDT',
        ]);

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNull($payout);
        $this->assertDatabaseCount('payouts', 0);
    }

    // --- batch build --------------------------------------------------------------

    public function test_build_all_creates_payouts_for_multiple_vendors(): void
    {
        $this->eligibleVendorSetup(saleAmount: 100_000, commissionAmount: -10_000);
        $this->eligibleVendorSetup(saleAmount: 200_000, commissionAmount: -20_000);

        $built = $this->service->buildAll(self::BATCH);

        $this->assertCount(2, $built);
        $this->assertDatabaseCount('payouts', 2);
        foreach ($built as $payout) {
            $this->assertSame(PayoutStatus::Pending, $payout->status);
        }
    }

    public function test_build_all_skips_vendors_with_no_eligible_orders(): void
    {
        // One eligible vendor.
        $this->eligibleVendorSetup();

        // One vendor with no orders at all.
        Vendor::factory()->verified()->create();

        $built = $this->service->buildAll(self::BATCH);

        $this->assertCount(1, $built);
        $this->assertDatabaseCount('payouts', 1);
    }

    // --- admin endpoint smoke tests -----------------------------------------------

    public function test_admin_can_list_payouts(): void
    {
        ['vendor' => $vendor] = $this->eligibleVendorSetup();
        $this->service->buildForVendor($vendor->id, self::BATCH);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/payouts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_admin_can_trigger_payout_build_via_endpoint(): void
    {
        ['vendor' => $vendor] = $this->eligibleVendorSetup();

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/payouts/build', [
                'vendor_id' => $vendor->id,
                'batch_id' => self::BATCH,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.batch_id', self::BATCH);

        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_payout_build_endpoint_is_idempotent(): void
    {
        ['vendor' => $vendor] = $this->eligibleVendorSetup();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payouts/build', ['vendor_id' => $vendor->id, 'batch_id' => self::BATCH]);
        $this->actingAs($admin)
            ->postJson('/api/v1/admin/payouts/build', ['vendor_id' => $vendor->id, 'batch_id' => self::BATCH]);

        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_non_admin_cannot_trigger_payout_build(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $this->actingAs($vendor->user)
            ->postJson('/api/v1/admin/payouts/build', ['vendor_id' => $vendor->id])
            ->assertForbidden();
    }

    // --- H-1: commission-reversal ledger entries included in adjustments ----------

    public function test_commission_reversal_included_in_payout_adjustments(): void
    {
        // When a refund completes, ProcessRefundWebhookService writes TWO entries per vendor:
        //   entry_type=refund,      subject_type=refund, amount=-30_000 (sale reversal, negative)
        //   entry_type=commission,  subject_type=refund, amount=+3_000  (commission returned, positive)
        // Both must be included in `adjustments` so the vendor is not shorted their returned commission.
        ['vendor' => $vendor, 'order' => $order] = $this->eligibleVendorSetup(
            saleAmount: 100_000, commissionAmount: -10_000  // gross=100_000, commission=10_000, net=90_000
        );

        // Simulate a completed refund for part of the order (30 000 poisha of the 100 000 gross).
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'status' => RefundStatus::Completed->value,
            'amount' => 30_000,
        ]);

        // Refund reversal ledger entries as written by ProcessRefundWebhookService.
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'refund', 'subject_id' => $refund->id,
            'entry_type' => LedgerEntryType::Refund->value, 'amount' => -30_000, 'currency' => 'BDT',
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'refund', 'subject_id' => $refund->id,
            'entry_type' => LedgerEntryType::Commission->value, 'amount' => 3_000, 'currency' => 'BDT',
        ]);

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNotNull($payout);
        // adjustments = -30_000 + 3_000 = -27_000; payable = 90_000 + (-27_000) = 63_000
        $this->assertSame(90_000, $payout->net);
        $this->assertSame(63_000, $payout->payable);
    }

    // --- M-3: vendor with mixed completed / ongoing event orders ------------------

    public function test_vendor_with_mixed_event_statuses_only_settles_completed_event_orders(): void
    {
        // ADR-20: pre-completion revenue must never be settled. This test has one eligible (completed)
        // and one ineligible (ongoing) order for the same vendor.
        $vendor = Vendor::factory()->verified()->create();

        // Completed event — eligible.
        $completedEvent = Event::factory()->completed()->forVendor($vendor)->create();
        $completedTt = TicketType::factory()->forEvent($completedEvent)->create(['price' => 80_000]);
        $completedOrder = Order::factory()->paid()->create();
        OrderItem::create([
            'order_id' => $completedOrder->id, 'ticket_type_id' => $completedTt->id,
            'quantity' => 1, 'unit_price' => 80_000,
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $completedOrder->id,
            'entry_type' => LedgerEntryType::Sale->value, 'amount' => 80_000, 'currency' => 'BDT',
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $completedOrder->id,
            'entry_type' => LedgerEntryType::Commission->value, 'amount' => -8_000, 'currency' => 'BDT',
        ]);

        // Ongoing event — NOT eligible.
        $ongoingEvent = Event::factory()->ongoing()->forVendor($vendor)->create();
        $ongoingTt = TicketType::factory()->forEvent($ongoingEvent)->create(['price' => 50_000]);
        $ongoingOrder = Order::factory()->paid()->create();
        OrderItem::create([
            'order_id' => $ongoingOrder->id, 'ticket_type_id' => $ongoingTt->id,
            'quantity' => 1, 'unit_price' => 50_000,
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $ongoingOrder->id,
            'entry_type' => LedgerEntryType::Sale->value, 'amount' => 50_000, 'currency' => 'BDT',
        ]);

        $payout = $this->service->buildForVendor($vendor->id, self::BATCH);

        $this->assertNotNull($payout);
        // Only the completed-event order is settled: gross=80_000, commission=8_000, net/payable=72_000
        $this->assertSame(80_000, $payout->gross);
        $this->assertSame(8_000, $payout->commission);
        $this->assertSame(72_000, $payout->payable);

        // Only one PayoutItem — the completed-event order.
        $this->assertDatabaseCount('payout_items', 1);
        $this->assertDatabaseHas('payout_items', ['order_id' => $completedOrder->id]);
        $this->assertDatabaseMissing('payout_items', ['order_id' => $ongoingOrder->id]);
    }
}
