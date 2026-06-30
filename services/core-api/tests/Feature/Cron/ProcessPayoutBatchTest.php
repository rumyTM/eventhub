<?php

namespace Tests\Feature\Cron;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\TicketType;
use App\Models\Vendor;
use App\Services\Payouts\ProcessPayoutBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Focused tests for ProcessPayoutBatchService — no-double-pay on re-run + completed-event gate.
 *
 * Full payout calculation coverage lives in PayoutBuildServiceTest. These tests assert the
 * batch command layer: idempotent runs, mid-batch safety, and eligibility enforcement.
 */
class ProcessPayoutBatchTest extends TestCase
{
    use RefreshDatabase;

    private ProcessPayoutBatchService $service;

    private const BATCH = '2026-06-30';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProcessPayoutBatchService::class);
    }

    // --- helpers ------------------------------------------------------------------

    private function eligibleVendorSetup(int $sale = 100_000, int $commission = -10_000): array
    {
        $vendor = Vendor::factory()->verified()->create();
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create(['price' => $sale]);
        $order = Order::factory()->paid()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $sale,
        ]);

        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Sale->value, 'amount' => $sale, 'currency' => 'BDT',
        ]);

        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Commission->value, 'amount' => $commission, 'currency' => 'BDT',
        ]);

        return compact('vendor', 'event', 'ticketType', 'order');
    }

    // --- no double-pay on re-run --------------------------------------------------

    public function test_running_same_batch_twice_produces_no_duplicate_payouts(): void
    {
        $this->eligibleVendorSetup();

        $first = $this->service->handle(self::BATCH);
        $second = $this->service->handle(self::BATCH);

        // Both runs report success but only one payout row exists.
        $this->assertSame(1, $first['built']);
        $this->assertSame(1, $second['built']);
        $this->assertDatabaseCount('payouts', 1);
        $this->assertDatabaseCount('payout_items', 1);
    }

    public function test_running_different_batch_ids_on_same_day_creates_at_most_one_payout_per_vendor(): void
    {
        // ADR-09: once an order is committed to a pending payout it must not appear in a second batch.
        $this->eligibleVendorSetup();

        $this->service->handle('2026-06-30');
        $this->service->handle('2026-06-30-retry'); // simulated second run with slightly different id

        // The order is already committed to the first pending payout — second batch finds no eligible orders.
        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_batch_builds_payouts_for_multiple_vendors_independently(): void
    {
        $this->eligibleVendorSetup(100_000, -10_000);
        $this->eligibleVendorSetup(200_000, -20_000);

        $result = $this->service->handle(self::BATCH);

        $this->assertSame(2, $result['built']);
        $this->assertDatabaseCount('payouts', 2);
        foreach (Payout::all() as $payout) {
            $this->assertSame(PayoutStatus::Pending, $payout->status);
        }
    }

    public function test_only_settles_completed_event_orders(): void
    {
        // ADR-20: revenue from ongoing/published events must NOT be settled.
        $vendor = Vendor::factory()->verified()->create();
        $ongoingEvent = Event::factory()->ongoing()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($ongoingEvent)->create(['price' => 100_000]);
        $order = Order::factory()->paid()->create();
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ticketType->id,
            'quantity' => 1, 'unit_price' => 100_000,
        ]);
        LedgerEntry::create([
            'vendor_id' => $vendor->id, 'subject_type' => 'order', 'subject_id' => $order->id,
            'entry_type' => LedgerEntryType::Sale->value, 'amount' => 100_000, 'currency' => 'BDT',
        ]);

        $result = $this->service->handle(self::BATCH);

        $this->assertSame(0, $result['built']);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_mid_batch_crash_safety_second_run_completes_remaining_vendors(): void
    {
        // Simulate a partial first run where only vendor A's payout was created.
        ['vendor' => $vendorA] = $this->eligibleVendorSetup(100_000, -10_000);
        $this->eligibleVendorSetup(200_000, -20_000); // vendor B not yet built

        // Manually create vendor A's payout (simulating the first run processing only A).
        Payout::create([
            'vendor_id' => $vendorA->id,
            'gross' => 100_000,
            'commission' => 10_000,
            'net' => 90_000,
            'payable' => 90_000,
            'reserved_refund' => 0,
            'currency' => 'BDT',
            'status' => PayoutStatus::Pending->value,
            'batch_id' => self::BATCH,
            'idempotency_key' => "payout:{$vendorA->id}:".self::BATCH,
        ]);

        // Second run: A is skipped (idempotent), B is newly built.
        $result = $this->service->handle(self::BATCH);

        $this->assertSame(2, $result['built']); // A returned from cache + B newly built
        $this->assertDatabaseCount('payouts', 2);
    }
}
