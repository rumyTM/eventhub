<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\TicketKind;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed realistic demo data for manual API testing.
     *
     * Credentials printed at the end — all accounts use the password "password".
     * No real PII, card data, NID, TIN, or bank details are stored.
     */
    public function run(): void
    {
        // ── 1. Admin ────────────────────────────────────────────────────────────
        $admin = User::factory()->admin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@eventhub.test',
            'password' => Hash::make('password'),
        ]);

        // ── 2. Verified vendor ──────────────────────────────────────────────────
        $vendorUser = User::factory()->vendor()->create([
            'name' => 'Acme Events Ltd',
            'email' => 'vendor@eventhub.test',
            'password' => Hash::make('password'),
        ]);

        $vendor = Vendor::factory()->verified()->create([
            'user_id' => $vendorUser->id,
            'business_name' => 'Acme Events Ltd',
            'legal_name' => 'Acme Events Limited',
            'address' => 'Motijheel, Dhaka, Bangladesh',
            'reviewed_by' => $admin->id,
        ]);

        // ── 3. Attendee ─────────────────────────────────────────────────────────
        $attendeeUser = User::factory()->attendee()->create([
            'name' => 'Alice Attendee',
            'email' => 'attendee@eventhub.test',
            'password' => Hash::make('password'),
        ]);

        $attendee = Attendee::create([
            'user_id' => $attendeeUser->id,
            'phone' => '+8801[PLACEHOLDER]',
        ]);

        // ── 4. Upcoming published event + 3 ticket types ────────────────────────
        $upcoming = Event::factory()->published()->forVendor($vendor)->create([
            'title' => 'DhakaTech Summit 2026',
            'description' => 'The premier technology conference in Bangladesh — keynotes on AI, '
                .'fintech, and software craftsmanship. Network with 500+ engineers.',
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(30)->addHours(8),
            'capacity' => 500,
            'timezone' => 'Asia/Dhaka',
        ]);

        TicketType::factory()->forEvent($upcoming)->create([
            'kind' => TicketKind::General,
            'price' => 50000,      // 500.00 BDT
            'quantity_total' => 350,
            'quantity_sold' => 12,
            'sales_start' => now(),
            'sales_end' => now()->addDays(29),
        ]);

        TicketType::factory()->forEvent($upcoming)->vip()->create([
            'quantity_total' => 50,
            'quantity_sold' => 3,
            'sales_start' => now(),
            'sales_end' => now()->addDays(29),
        ]);

        TicketType::factory()->forEvent($upcoming)->earlyBird()->create([
            'quantity_total' => 100,
            'quantity_sold' => 100,    // sold out — useful for testing 409 unavailable
            'sales_start' => now()->subDays(14),
            'sales_end' => now()->subDays(1),
        ]);

        // ── 5. Completed event (revenue eligible for payout) ───────────────────
        $completed = Event::factory()->completed()->forVendor($vendor)->create([
            'title' => 'Bangladesh Open Source Day',
            'description' => 'Celebrating open-source contributions across Bangladesh.',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(10)->addHours(6),
            'capacity' => 200,
            'timezone' => 'Asia/Dhaka',
        ]);

        $soldTicketType = TicketType::factory()->forEvent($completed)->create([
            'kind' => TicketKind::General,
            'price' => 25000,      // 250.00 BDT
            'quantity_total' => 200,
            'quantity_sold' => 3,
            'sales_start' => now()->subDays(30),
            'sales_end' => now()->subDays(11),
        ]);

        // ── 6. Paid order: attendee bought 3 tickets to the completed event ─────
        $order = Order::create([
            'attendee_id' => $attendee->id,
            'status' => OrderStatus::Paid,
            'total' => 75000,     // 3 × 25 000 = 750.00 BDT
            'currency' => 'BDT',
            'commission_rate' => '0.1000',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $soldTicketType->id,
            'quantity' => 3,
            'unit_price' => 25000,
        ]);

        // Issue one ticket artifact per seat.
        for ($i = 1; $i <= 3; $i++) {
            Ticket::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'ticket_type_id' => $soldTicketType->id,
                'qr_code' => 'DEMO-QR-'.strtoupper(Str::random(8)).'-'.$i,
                'status' => 'valid',
            ]);
        }

        // ── 7. Payment record for the order ────────────────────────────────────
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'stripe_sim',
            'status' => PaymentStatus::Succeeded->value,
            'external_ref' => '[PLACEHOLDER-GATEWAY-REF]',
            'idempotency_key' => (string) Str::uuid(),
            'amount' => 75000,
            'currency' => 'BDT',
        ]);

        // ── 8. Pending payout for the vendor (75 000 gross, 10 % commission) ───
        $gross = 75000;
        $commission = (int) ($gross * 0.10);
        $net = $gross - $commission;

        Payout::create([
            'vendor_id' => $vendor->id,
            'gross' => $gross,
            'commission' => $commission,
            'net' => $net,
            'payable' => $net,
            'reserved_refund' => 0,
            'currency' => 'BDT',
            'status' => PayoutStatus::Pending,
            'batch_id' => now()->toDateString(),
            'idempotency_key' => 'payout:'.$vendor->id.':batch:'.now()->toDateString(),
        ]);

        // ── Print credentials ────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('═══════════════ Demo credentials (test only — not real) ════════════════');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',    'admin@eventhub.test',    'password'],
                ['Vendor',   'vendor@eventhub.test',   'password'],
                ['Attendee', 'attendee@eventhub.test', 'password'],
            ]
        );
        $this->command->info('Seeded:');
        $this->command->info('  · Published event  → "DhakaTech Summit 2026" (starts in 30 days)');
        $this->command->info('  · Completed event  → "Bangladesh Open Source Day" (10 days ago)');
        $this->command->info('  · Paid order       → 3 × 250 BDT = 750 BDT (attendee@eventhub.test)');
        $this->command->info('  · Pending payout   → 675 BDT net (vendor@eventhub.test, 10 % commission)');
        $this->command->info('════════════════════════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
