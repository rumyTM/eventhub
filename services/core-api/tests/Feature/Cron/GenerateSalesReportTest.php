<?php

namespace Tests\Feature\Cron;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesReport;
use App\Models\TicketType;
use App\Models\Vendor;
use App\Services\Reports\GenerateSalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GenerateSalesReportService — daily sales report generation.
 *
 * Asserts:
 *   - Vendor rows + platform-wide row are created correctly.
 *   - Re-running the same date is idempotent (updateOrCreate; no duplicate rows).
 *   - Platform-wide null-vendor row is always present after a run.
 *   - Zero-activity day still writes the platform-wide row.
 */
class GenerateSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private GenerateSalesReportService $service;

    private const DATE = '2026-06-29';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GenerateSalesReportService::class);
    }

    // --- helpers ------------------------------------------------------------------

    private function createSaleLedgerEntry(string $vendorId, string $orderId, int $amount): void
    {
        // forceCreate bypasses $fillable so we can set created_at explicitly (append-only model).
        LedgerEntry::forceCreate([
            'vendor_id' => $vendorId,
            'subject_type' => 'order',
            'subject_id' => $orderId,
            'entry_type' => LedgerEntryType::Sale->value,
            'amount' => $amount,
            'currency' => 'BDT',
            'created_at' => self::DATE.' 10:00:00',
        ]);
    }

    private function createCommissionLedgerEntry(string $vendorId, string $orderId, int $amount): void
    {
        LedgerEntry::forceCreate([
            'vendor_id' => $vendorId,
            'subject_type' => 'order',
            'subject_id' => $orderId,
            'entry_type' => LedgerEntryType::Commission->value,
            'amount' => $amount,
            'currency' => 'BDT',
            'created_at' => self::DATE.' 10:00:00',
        ]);
    }

    // --- happy path ---------------------------------------------------------------

    public function test_creates_vendor_row_and_platform_wide_row(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $order = Order::factory()->paid()->create();
        $ticketType = TicketType::factory()->create(['price' => 50_000]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50_000,
            'original_price' => 50_000,
        ]);

        $this->createSaleLedgerEntry($vendor->id, $order->id, 100_000);
        $this->createCommissionLedgerEntry($vendor->id, $order->id, -10_000);

        $result = $this->service->handle(self::DATE);

        $this->assertSame(self::DATE, $result['report_date']);
        $this->assertSame(1, $result['vendors']);

        // Vendor row — load via Eloquent so date casting works across SQLite/MySQL.
        $vendorRow = SalesReport::whereDate('report_date', self::DATE)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($vendorRow);
        $this->assertSame(100_000, $vendorRow->gross);
        $this->assertSame(10_000, $vendorRow->commission);
        $this->assertSame(90_000, $vendorRow->net);
        $this->assertSame(2, $vendorRow->tickets_sold);

        // Platform-wide row (vendor_id IS NULL)
        $platformRow = SalesReport::whereDate('report_date', self::DATE)->whereNull('vendor_id')->first();
        $this->assertNotNull($platformRow);
        $this->assertSame(100_000, $platformRow->gross);
        $this->assertSame(10_000, $platformRow->commission);
        $this->assertSame(90_000, $platformRow->net);
    }

    // --- idempotency (same date re-run) -------------------------------------------

    public function test_re_running_same_date_does_not_create_duplicate_rows(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $order = Order::factory()->paid()->create();
        TicketType::factory()->create(); // for order_items join

        $this->createSaleLedgerEntry($vendor->id, $order->id, 80_000);
        $this->createCommissionLedgerEntry($vendor->id, $order->id, -8_000);

        $this->service->handle(self::DATE);
        $this->service->handle(self::DATE); // second run

        // Exactly 1 vendor row + 1 platform row — no duplicates.
        $this->assertSame(1, SalesReport::whereDate('report_date', self::DATE)->whereNotNull('vendor_id')->count());
        $this->assertSame(1, SalesReport::whereDate('report_date', self::DATE)->whereNull('vendor_id')->count());
    }

    public function test_re_run_updates_values_when_ledger_data_changes(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $order = Order::factory()->paid()->create();

        $this->createSaleLedgerEntry($vendor->id, $order->id, 60_000);
        $this->service->handle(self::DATE);

        // A backdated corrective entry arrives; re-run should update the row.
        $this->createSaleLedgerEntry($vendor->id, $order->id.'-extra', 40_000);
        $this->service->handle(self::DATE);

        $vendorRow = SalesReport::whereDate('report_date', self::DATE)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($vendorRow);
        $this->assertSame(100_000, $vendorRow->gross);

        $platformRow = SalesReport::whereDate('report_date', self::DATE)->whereNull('vendor_id')->first();
        $this->assertSame(100_000, $platformRow->gross);
    }

    // --- zero-activity day --------------------------------------------------------

    public function test_writes_platform_row_even_when_no_sales_exist(): void
    {
        $result = $this->service->handle(self::DATE);

        $this->assertSame(0, $result['vendors']);

        $platformRow = SalesReport::whereDate('report_date', self::DATE)->whereNull('vendor_id')->first();
        $this->assertNotNull($platformRow);
        $this->assertSame(0, $platformRow->gross);
        $this->assertSame(0, $platformRow->net);
    }

    // --- group-discount total_discount -------------------------------------------

    public function test_total_discount_summed_from_discounted_group_order_items(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $order = Order::factory()->paid()->create();
        $ticketType = TicketType::factory()->create(['price' => 1000]);

        // Two discounted lines: original_price=1000, unit_price=750, qty=4 → saving = 250*4 = 1000
        // One undiscounted line: original_price=500, unit_price=500, qty=2 → saving = 0
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ticketType->id,
            'quantity' => 4, 'unit_price' => 750, 'original_price' => 1000,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ticketType->id,
            'quantity' => 2, 'unit_price' => 500, 'original_price' => 500,
        ]);

        $this->createSaleLedgerEntry($vendor->id, $order->id, 5_000);
        $this->createCommissionLedgerEntry($vendor->id, $order->id, -500);

        $this->service->handle(self::DATE);

        $vendorRow = SalesReport::whereDate('report_date', self::DATE)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($vendorRow);
        // (1000 - 750) * 4 = 1000; (500 - 500) * 2 = 0  →  total = 1000
        $this->assertSame(1_000, $vendorRow->total_discount);

        $platformRow = SalesReport::whereDate('report_date', self::DATE)->whereNull('vendor_id')->first();
        $this->assertSame(1_000, $platformRow->total_discount);
    }

    public function test_total_discount_is_zero_when_no_discounted_items(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $order = Order::factory()->paid()->create();
        $ticketType = TicketType::factory()->create(['price' => 50_000]);

        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $ticketType->id,
            'quantity' => 2, 'unit_price' => 50_000, 'original_price' => 50_000,
        ]);

        $this->createSaleLedgerEntry($vendor->id, $order->id, 100_000);
        $this->createCommissionLedgerEntry($vendor->id, $order->id, -10_000);

        $this->service->handle(self::DATE);

        $vendorRow = SalesReport::whereDate('report_date', self::DATE)->where('vendor_id', $vendor->id)->first();
        $this->assertSame(0, $vendorRow->total_discount);
    }

    // --- multi-vendor aggregation -------------------------------------------------

    public function test_aggregates_multiple_vendors_separately(): void
    {
        $vendorA = Vendor::factory()->verified()->create();
        $vendorB = Vendor::factory()->verified()->create();
        $orderA = Order::factory()->paid()->create();
        $orderB = Order::factory()->paid()->create();

        $this->createSaleLedgerEntry($vendorA->id, $orderA->id, 100_000);
        $this->createCommissionLedgerEntry($vendorA->id, $orderA->id, -10_000);
        $this->createSaleLedgerEntry($vendorB->id, $orderB->id, 200_000);
        $this->createCommissionLedgerEntry($vendorB->id, $orderB->id, -20_000);

        $result = $this->service->handle(self::DATE);

        $this->assertSame(2, $result['vendors']);

        $platformRow = SalesReport::whereDate('report_date', self::DATE)->whereNull('vendor_id')->first();
        $this->assertSame(300_000, $platformRow->gross);
        $this->assertSame(30_000, $platformRow->commission);
        $this->assertSame(270_000, $platformRow->net);
    }
}
