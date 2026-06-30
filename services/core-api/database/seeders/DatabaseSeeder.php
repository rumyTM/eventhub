<?php

namespace Database\Seeders;

use App\Enums\DisputeStatus;
use App\Enums\HoldStatus;
use App\Enums\KycStatus;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\TicketKind;
use App\Models\Attendee;
use App\Models\Dispute;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\TicketHold;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data seed — everything needed for the full video walkthrough.
 *
 * All accounts use password "password". No real PII, card data, NID, TIN, or bank details.
 *
 * Data layout:
 *   · 2 vendors (1 verified, 1 pending KYC for admin approval demo)
 *   · 2 attendees (Alice, Bob)
 *   · 6 events in different lifecycle states
 *   · Paid orders on TWO completed events:
 *       – Bangladesh Open Source Day  → PENDING payout pre-built (admin can Execute immediately)
 *       – Fintech Innovation Summit   → historical PAID payout (shows payout history)
 *   · Paid orders on upcoming events  → for live refund demo
 *   · Completed refund, requested refund, and open dispute → admin queue content
 *   · Pending order with active hold  → shows hold countdown in UI
 */
class DatabaseSeeder extends Seeder
{
    private const COMMISSION_RATE = '0.1000'; // 10 % platform commission

    public function run(): void
    {
        // ── 0. Platform settings ───────────────────────────────────────────────
        // Keep in sync with requirement-analysis.md §3 — 10% commission, 5 000 BDT payout minimum.
        \App\Models\Setting::create(['key' => 'commission_rate',   'value' => '0.1000', 'type' => 'string']);
        \App\Models\Setting::create(['key' => 'payout_threshold', 'value' => '500000', 'type' => 'integer']);

        // ── 1. Admin ─────────────────────────────────────────────────────────────
        $admin = User::factory()->admin()->create([
            'name'     => 'Platform Admin',
            'email'    => 'admin@eventhub.test',
            'password' => Hash::make('password'),
        ]);

        // ── 2. Verified vendor (Acme Events Ltd) ─────────────────────────────────
        $vendorUser = User::factory()->vendor()->create([
            'name'     => 'Acme Events Ltd',
            'email'    => 'vendor@eventhub.test',
            'password' => Hash::make('password'),
        ]);

        $vendor = Vendor::factory()->verified()->create([
            'user_id'        => $vendorUser->id,
            'business_name'  => 'Acme Events Ltd',
            'legal_name'     => 'Acme Events Limited',
            'address'        => 'Motijheel, Dhaka, Bangladesh',
            'commission_rate' => self::COMMISSION_RATE,
            'reviewed_by'    => $admin->id,
        ]);

        // ── 3. Pending-KYC vendor (for admin approval demo) ───────────────────────
        $vendor2User = User::factory()->vendor()->create([
            'name'     => 'BDTech Conferences',
            'email'    => 'vendor2@eventhub.test',
            'password' => Hash::make('password'),
        ]);

        Vendor::factory()->create([
            'user_id'       => $vendor2User->id,
            'business_name' => 'BDTech Conferences',
            'legal_name'    => 'BDTech Conferences Ltd',
            'address'       => 'Gulshan, Dhaka, Bangladesh',
            'kyc_status'    => KycStatus::Pending,
        ]);

        // ── 4. Attendees ──────────────────────────────────────────────────────────
        $attendeeUser = User::factory()->attendee()->create([
            'name'     => 'Alice Attendee',
            'email'    => 'attendee@eventhub.test',
            'password' => Hash::make('password'),
        ]);
        $attendee = Attendee::create([
            'user_id' => $attendeeUser->id,
            'phone'   => '+880100[PLACEHOLDER]',
        ]);

        $attendee2User = User::factory()->attendee()->create([
            'name'     => 'Bob Buyer',
            'email'    => 'attendee2@eventhub.test',
            'password' => Hash::make('password'),
        ]);
        $attendee2 = Attendee::create([
            'user_id' => $attendee2User->id,
            'phone'   => '+880199[PLACEHOLDER]',
        ]);

        // ══════════════════════════════════════════════════════════════════════════
        // EVENTS
        // ══════════════════════════════════════════════════════════════════════════

        // ── 5. [A] DhakaTech Summit 2026 — published, 30 days away ───────────────
        //    This is the event used for the live purchase + refund demo.
        $summit = Event::factory()->published()->forVendor($vendor)->create([
            'title'       => 'DhakaTech Summit 2026',
            'description' => 'The premier technology conference in Bangladesh — keynotes on AI, fintech, '
                .'and software craftsmanship. Network with 500+ engineers.',
            'starts_at'   => now()->addDays(30),
            'ends_at'     => now()->addDays(30)->addHours(8),
            'capacity'    => 500,
            'timezone'    => 'Asia/Dhaka',
        ]);

        TicketType::factory()->forEvent($summit)->create([
            'kind'           => TicketKind::General,
            'price'          => 50000,   // 500 BDT
            'quantity_total' => 350,
            'quantity_sold'  => 200,
            'sales_start'    => now(),
            'sales_end'      => now()->addDays(29),
        ]);

        TicketType::factory()->forEvent($summit)->vip()->create([
            'price'          => 150000,  // 1500 BDT
            'quantity_total' => 50,
            'quantity_sold'  => 15,
            'sales_start'    => now(),
            'sales_end'      => now()->addDays(29),
        ]);

        // Early bird is sold out — useful for showing 409 Tickets Unavailable
        TicketType::factory()->forEvent($summit)->earlyBird()->create([
            'price'          => 20000,   // 200 BDT
            'quantity_total' => 100,
            'quantity_sold'  => 100,
            'sales_start'    => now()->subDays(14),
            'sales_end'      => now()->subDays(1),
        ]);

        // ── 6. [B] Dhaka Startup Pitch Night — ongoing (started 1 h ago) ─────────
        $pitchNight = Event::factory()->ongoing()->forVendor($vendor)->create([
            'title'       => 'Dhaka Startup Pitch Night',
            'description' => 'Watch 20 early-stage startups pitch live to a panel of investors and win prizes.',
            'starts_at'   => now()->subHours(1),
            'ends_at'     => now()->addHours(4),
            'capacity'    => 300,
            'timezone'    => 'Asia/Dhaka',
        ]);

        TicketType::factory()->forEvent($pitchNight)->create([
            'kind'           => TicketKind::General,
            'price'          => 30000,   // 300 BDT
            'quantity_total' => 200,
            'quantity_sold'  => 45,
            'sales_start'    => now()->subDays(7),
            'sales_end'      => now()->addHours(3),
        ]);

        TicketType::factory()->forEvent($pitchNight)->groupBundle()->create([
            'kind'           => TicketKind::General,
            'price'          => 30000,   // 300 BDT, 15 % off for groups of 4+
            'quantity_total' => 80,
            'quantity_sold'  => 16,
            'sales_start'    => now()->subDays(7),
            'sales_end'      => now()->addHours(3),
        ]);

        // ── 7. [C] Laravel & Vue Workshop — published, 14 days away ──────────────
        $workshop = Event::factory()->published()->forVendor($vendor)->create([
            'title'       => 'Laravel & Vue Workshop',
            'description' => 'A hands-on full-day workshop covering Laravel 11 APIs and Vue 3 SPA integration.',
            'starts_at'   => now()->addDays(14),
            'ends_at'     => now()->addDays(14)->addHours(8),
            'capacity'    => 80,
            'timezone'    => 'Asia/Dhaka',
        ]);

        TicketType::factory()->forEvent($workshop)->earlyBird()->create([
            'price'          => 60000,   // 600 BDT
            'quantity_total' => 20,
            'quantity_sold'  => 5,
            'sales_start'    => now()->subDays(3),
            'sales_end'      => now()->addDays(7),
        ]);

        TicketType::factory()->forEvent($workshop)->create([
            'kind'           => TicketKind::General,
            'price'          => 80000,   // 800 BDT
            'quantity_total' => 60,
            'quantity_sold'  => 0,
            'sales_start'    => now()->addDays(7),
            'sales_end'      => now()->addDays(13),
        ]);

        // ── 8. [D] Bangladesh Open Source Day — COMPLETED 10 days ago ────────────
        //    NO payout yet → `payouts:process-batch` will create one during the demo.
        $openSourceDay = Event::factory()->completed()->forVendor($vendor)->create([
            'title'       => 'Bangladesh Open Source Day',
            'description' => 'Celebrating open-source contributions across Bangladesh.',
            'starts_at'   => now()->subDays(10),
            'ends_at'     => now()->subDays(10)->addHours(6),
            'capacity'    => 200,
            'timezone'    => 'Asia/Dhaka',
        ]);

        $osGeneral = TicketType::factory()->forEvent($openSourceDay)->create([
            'kind'           => TicketKind::General,
            'price'          => 25000,   // 250 BDT
            'quantity_total' => 200,
            'quantity_sold'  => 10,      // 10 sold = 5 orders × 2 tickets each
            'sales_start'    => now()->subDays(30),
            'sales_end'      => now()->subDays(11),
        ]);

        // ── 9. [E] Fintech Innovation Summit — COMPLETED 25 days ago ─────────────
        //    Revenue already settled: historical PAID payout exists.
        $fintech = Event::factory()->completed()->forVendor($vendor)->create([
            'title'       => 'Fintech Innovation Summit',
            'description' => 'Bangladesh\'s leading fintech and digital-payments conference.',
            'starts_at'   => now()->subDays(25),
            'ends_at'     => now()->subDays(25)->addHours(7),
            'capacity'    => 300,
            'timezone'    => 'Asia/Dhaka',
        ]);

        $fintechGeneral = TicketType::factory()->forEvent($fintech)->create([
            'kind'           => TicketKind::General,
            'price'          => 30000,   // 300 BDT
            'quantity_total' => 300,
            'quantity_sold'  => 5,       // 2 orders (2+3 tickets)
            'sales_start'    => now()->subDays(40),
            'sales_end'      => now()->subDays(26),
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // PAID ORDERS + TICKETS + PAYMENTS + LEDGER ENTRIES
        // for event [D] Bangladesh Open Source Day  (pending payout pre-built → admin executes)
        // ════════════════════════════════════════════════════════════════════════

        $osdOrders = [];

        $osdBatches = [
            ['attendee' => $attendee,  'qty' => 2, 'unit' => 25000],
            ['attendee' => $attendee2, 'qty' => 2, 'unit' => 25000],
            ['attendee' => $attendee,  'qty' => 2, 'unit' => 25000],
            ['attendee' => $attendee2, 'qty' => 2, 'unit' => 25000],
            ['attendee' => $attendee,  'qty' => 2, 'unit' => 25000],
        ];

        foreach ($osdBatches as $i => $b) {
            $total = $b['qty'] * $b['unit'];
            $order = Order::create([
                'attendee_id'     => $b['attendee']->id,
                'status'          => OrderStatus::Paid,
                'total'           => $total,
                'currency'        => 'BDT',
                'commission_rate' => self::COMMISSION_RATE,
                'idempotency_key' => 'demo-osd-'.($i + 1).':'.Str::uuid(),
            ]);

            $item = OrderItem::create([
                'order_id'       => $order->id,
                'ticket_type_id' => $osGeneral->id,
                'quantity'       => $b['qty'],
                'unit_price'     => $b['unit'],
                'original_price' => $b['unit'],
            ]);

            for ($t = 1; $t <= $b['qty']; $t++) {
                Ticket::create([
                    'order_id'      => $order->id,
                    'order_item_id' => $item->id,
                    'ticket_type_id'=> $osGeneral->id,
                    'qr_code'       => 'OSD-QR-'.strtoupper(Str::random(8)).'-'.$t,
                    'status'        => 'valid',
                ]);
            }

            Payment::create([
                'order_id'        => $order->id,
                'gateway'         => 'stripe_sim',
                'status'          => PaymentStatus::Succeeded,
                'external_ref'    => sprintf('SEEDOSD%019d', $i + 1),
                'idempotency_key' => 'pay:osd-'.($i + 1).':'.Str::uuid(),
                'amount'          => $total,
                'currency'        => 'BDT',
            ]);

            // Ledger: sale + commission for each order
            $commissionAmount = (int) ($total * 0.10);

            LedgerEntry::create([
                'vendor_id'    => $vendor->id,
                'subject_type' => 'order',
                'subject_id'   => $order->id,
                'entry_type'   => LedgerEntryType::Sale,
                'amount'       => $total,          // positive
                'currency'     => 'BDT',
            ]);

            LedgerEntry::create([
                'vendor_id'    => $vendor->id,
                'subject_type' => 'order',
                'subject_id'   => $order->id,
                'entry_type'   => LedgerEntryType::Commission,
                'amount'       => -$commissionAmount,  // negative — deducted from vendor
                'currency'     => 'BDT',
            ]);

            $osdOrders[] = ['model' => $order, 'net' => $total - $commissionAmount];
        }

        // Pre-build a PENDING payout for the OSD orders so the admin panel shows it immediately.
        // batch_id = yesterday (realistic: "built overnight, not yet disbursed").
        // idempotency_key uses the service's canonical format payout:{vendorId}:{batchId}.
        $osdBatchId      = now()->subDay()->toDateString();
        $osdGross        = 250000;  // 5 orders × 50 000 poisha each
        $osdCommission   = (int) ($osdGross * 0.10);
        $osdNet          = $osdGross - $osdCommission;

        $pendingPayout = Payout::create([
            'vendor_id'       => $vendor->id,
            'gross'           => $osdGross,
            'commission'      => $osdCommission,
            'net'             => $osdNet,
            'payable'         => $osdNet,
            'reserved_refund' => 0,
            'currency'        => 'BDT',
            'status'          => PayoutStatus::Pending,
            'batch_id'        => $osdBatchId,
            'idempotency_key' => 'payout:'.$vendor->id.':'.$osdBatchId,
        ]);

        foreach ($osdOrders as $oo) {
            PayoutItem::create([
                'payout_id'      => $pendingPayout->id,
                'order_id'       => $oo['model']->id,
                'settled_amount' => $oo['net'],
                'settled_at'     => null,   // not yet disbursed
            ]);
        }

        // ════════════════════════════════════════════════════════════════════════
        // PAID ORDERS for event [E] Fintech Innovation Summit
        // + HISTORICAL PAID PAYOUT (already settled — shows in payout history)
        // ════════════════════════════════════════════════════════════════════════

        $fintechOrderData = [
            ['attendee' => $attendee,  'qty' => 2, 'unit' => 30000],
            ['attendee' => $attendee2, 'qty' => 3, 'unit' => 30000],
        ];

        $fintechOrders = [];
        foreach ($fintechOrderData as $i => $b) {
            $total = $b['qty'] * $b['unit'];
            $order = Order::create([
                'attendee_id'     => $b['attendee']->id,
                'status'          => OrderStatus::Paid,
                'total'           => $total,
                'currency'        => 'BDT',
                'commission_rate' => self::COMMISSION_RATE,
                'idempotency_key' => 'demo-fintech-'.($i + 1).':'.Str::uuid(),
            ]);

            $item = OrderItem::create([
                'order_id'       => $order->id,
                'ticket_type_id' => $fintechGeneral->id,
                'quantity'       => $b['qty'],
                'unit_price'     => $b['unit'],
                'original_price' => $b['unit'],
            ]);

            for ($t = 1; $t <= $b['qty']; $t++) {
                Ticket::create([
                    'order_id'      => $order->id,
                    'order_item_id' => $item->id,
                    'ticket_type_id'=> $fintechGeneral->id,
                    'qr_code'       => 'FIN-QR-'.strtoupper(Str::random(8)).'-'.$t,
                    'status'        => 'valid',
                ]);
            }

            Payment::create([
                'order_id'        => $order->id,
                'gateway'         => 'paypal_sim',
                'status'          => PaymentStatus::Succeeded,
                'external_ref'    => sprintf('SEEDFIN%019d', $i + 1),
                'idempotency_key' => 'pay:fintech-'.($i + 1).':'.Str::uuid(),
                'amount'          => $total,
                'currency'        => 'BDT',
            ]);

            $commissionAmount = (int) ($total * 0.10);

            LedgerEntry::create([
                'vendor_id'    => $vendor->id,
                'subject_type' => 'order',
                'subject_id'   => $order->id,
                'entry_type'   => LedgerEntryType::Sale,
                'amount'       => $total,
                'currency'     => 'BDT',
            ]);

            LedgerEntry::create([
                'vendor_id'    => $vendor->id,
                'subject_type' => 'order',
                'subject_id'   => $order->id,
                'entry_type'   => LedgerEntryType::Commission,
                'amount'       => -$commissionAmount,
                'currency'     => 'BDT',
            ]);

            $fintechOrders[] = ['model' => $order, 'net' => $total - $commissionAmount];
        }

        // Historical PAID payout for the Fintech orders (already disbursed 20 days ago)
        $fintechGross      = 60000 + 90000;   // 150 000 BDT
        $fintechCommission = (int) ($fintechGross * 0.10);
        $fintechNet        = $fintechGross - $fintechCommission;
        $historicalBatchId = now()->subDays(20)->toDateString();

        $historicalPayout = Payout::create([
            'vendor_id'       => $vendor->id,
            'gross'           => $fintechGross,
            'commission'      => $fintechCommission,
            'net'             => $fintechNet,
            'payable'         => $fintechNet,
            'reserved_refund' => 0,
            'currency'        => 'BDT',
            'status'          => PayoutStatus::Paid,
            'batch_id'        => $historicalBatchId,
            'idempotency_key' => 'payout:'.$vendor->id.':batch:'.$historicalBatchId,
            'created_at'      => now()->subDays(20),
            'updated_at'      => now()->subDays(19),
        ]);

        foreach ($fintechOrders as $fo) {
            PayoutItem::create([
                'payout_id'      => $historicalPayout->id,
                'order_id'       => $fo['model']->id,
                'settled_amount' => $fo['net'],
                'settled_at'     => now()->subDays(19),
            ]);
        }

        // Ledger entry for the historical payout itself
        LedgerEntry::create([
            'vendor_id'    => $vendor->id,
            'subject_type' => 'payout',
            'subject_id'   => $historicalPayout->id,
            'entry_type'   => LedgerEntryType::Payout,
            'amount'       => -$fintechNet,  // negative: money leaving the platform to vendor
            'currency'     => 'BDT',
            'created_at'   => now()->subDays(19),
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // PAID ORDERS on upcoming events — for live refund demo + UI content
        // ════════════════════════════════════════════════════════════════════════

        // Order for summit (DhakaTech Summit, 30 days away) — attendee already bought
        // Shows in "My Orders"; can be refunded live (>48h → 100%)
        $summitGeneral = TicketType::where('event_id', $summit->id)
            ->where('kind', TicketKind::General->value)
            ->first();

        $summitOrder = Order::create([
            'attendee_id'     => $attendee->id,
            'status'          => OrderStatus::Paid,
            'total'           => 100000,   // 2 × 500 BDT = 1 000 BDT
            'currency'        => 'BDT',
            'commission_rate' => self::COMMISSION_RATE,
            'idempotency_key' => 'demo-summit-alice:'.Str::uuid(),
        ]);

        $summitItem = OrderItem::create([
            'order_id'       => $summitOrder->id,
            'ticket_type_id' => $summitGeneral->id,
            'quantity'       => 2,
            'unit_price'     => 50000,
            'original_price' => 50000,
        ]);

        for ($t = 1; $t <= 2; $t++) {
            Ticket::create([
                'order_id'      => $summitOrder->id,
                'order_item_id' => $summitItem->id,
                'ticket_type_id'=> $summitGeneral->id,
                'qr_code'       => 'SUM-QR-ALICE-'.strtoupper(Str::random(6)).'-'.$t,
                'status'        => 'valid',
            ]);
        }

        $summitPayment = Payment::create([
            'order_id'        => $summitOrder->id,
            'gateway'         => 'stripe_sim',
            'status'          => PaymentStatus::Succeeded,
            'external_ref'    => 'SEEDSUMALICE00000000000001',
            'idempotency_key' => 'pay:summit-alice:'.Str::uuid(),
            'amount'          => 100000,
            'currency'        => 'BDT',
        ]);

        // Bob also has a summit order — gives the vendor more sales to show
        $summitOrderBob = Order::create([
            'attendee_id'     => $attendee2->id,
            'status'          => OrderStatus::Paid,
            'total'           => 50000,   // 1 × 500 BDT
            'currency'        => 'BDT',
            'commission_rate' => self::COMMISSION_RATE,
            'idempotency_key' => 'demo-summit-bob:'.Str::uuid(),
        ]);

        $summitItemBob = OrderItem::create([
            'order_id'       => $summitOrderBob->id,
            'ticket_type_id' => $summitGeneral->id,
            'quantity'       => 1,
            'unit_price'     => 50000,
            'original_price' => 50000,
        ]);

        Ticket::create([
            'order_id'      => $summitOrderBob->id,
            'order_item_id' => $summitItemBob->id,
            'ticket_type_id'=> $summitGeneral->id,
            'qr_code'       => 'SUM-QR-BOB-'.strtoupper(Str::random(8)),
            'status'        => 'valid',
        ]);

        Payment::create([
            'order_id'        => $summitOrderBob->id,
            'gateway'         => 'stripe_sim',
            'status'          => PaymentStatus::Succeeded,
            'external_ref'    => 'SEEDSUMBOB0000000000000001',
            'idempotency_key' => 'pay:summit-bob:'.Str::uuid(),
            'amount'          => 50000,
            'currency'        => 'BDT',
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // COMPLETED REFUND — Alice refunded 1 Early Bird ticket (workshop, >48 h)
        // Shows in attendee order history and admin refund list
        // ════════════════════════════════════════════════════════════════════════

        $workshopEarlyBird = TicketType::where('event_id', $workshop->id)
            ->where('kind', TicketKind::EarlyBird->value)
            ->first();

        $workshopOrder = Order::create([
            'attendee_id'     => $attendee->id,
            'status'          => OrderStatus::Refunded,   // fully refunded
            'total'           => 60000,   // 1 × 600 BDT
            'currency'        => 'BDT',
            'commission_rate' => self::COMMISSION_RATE,
            'idempotency_key' => 'demo-workshop-refunded:'.Str::uuid(),
        ]);

        $workshopItem = OrderItem::create([
            'order_id'       => $workshopOrder->id,
            'ticket_type_id' => $workshopEarlyBird->id,
            'quantity'       => 1,
            'unit_price'     => 60000,
            'original_price' => 60000,
        ]);

        $workshopPayment = Payment::create([
            'order_id'        => $workshopOrder->id,
            'gateway'         => 'stripe_sim',
            'status'          => PaymentStatus::Succeeded,
            'external_ref'    => 'SEEDWSHPRF0000000000000001',
            'idempotency_key' => 'pay:workshop-refund:'.Str::uuid(),
            'amount'          => 60000,
            'currency'        => 'BDT',
        ]);

        Refund::create([
            'payment_id'      => $workshopPayment->id,
            'amount'          => 60000,    // 100 % — event > 48 h away
            'policy_applied'  => '100',
            'status'          => RefundStatus::Completed,
            'reason'          => RefundReason::AttendeeRequested,
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // REQUESTED REFUND — Bob's workshop order, in admin refund queue
        // ════════════════════════════════════════════════════════════════════════

        $workshopOrderBob = Order::create([
            'attendee_id'     => $attendee2->id,
            'status'          => OrderStatus::Paid,   // still paid, refund requested
            'total'           => 60000,   // 1 × 600 BDT
            'currency'        => 'BDT',
            'commission_rate' => self::COMMISSION_RATE,
            'idempotency_key' => 'demo-workshop-bob:'.Str::uuid(),
        ]);

        $workshopItemBob = OrderItem::create([
            'order_id'       => $workshopOrderBob->id,
            'ticket_type_id' => $workshopEarlyBird->id,
            'quantity'       => 1,
            'unit_price'     => 60000,
            'original_price' => 60000,
        ]);

        Ticket::create([
            'order_id'      => $workshopOrderBob->id,
            'order_item_id' => $workshopItemBob->id,
            'ticket_type_id'=> $workshopEarlyBird->id,
            'qr_code'       => 'WSHP-QR-BOB-'.strtoupper(Str::random(8)),
            'status'        => 'valid',
        ]);

        $workshopPaymentBob = Payment::create([
            'order_id'        => $workshopOrderBob->id,
            'gateway'         => 'stripe_sim',
            'status'          => PaymentStatus::Succeeded,
            'external_ref'    => 'SEEDWSHPBOB000000000000001',
            'idempotency_key' => 'pay:workshop-bob:'.Str::uuid(),
            'amount'          => 60000,
            'currency'        => 'BDT',
        ]);

        // Bob requested a refund — sits in the admin queue as "requested"
        Refund::create([
            'payment_id'     => $workshopPaymentBob->id,
            'amount'         => 60000,
            'policy_applied' => '100',
            'status'         => RefundStatus::Requested,
            'reason'         => RefundReason::AttendeeRequested,
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // OPEN DISPUTE — Pitch Night order (event is ongoing / <24h), 0% policy
        // Alice requested refund after policy window closed → admin dispute queue
        // ════════════════════════════════════════════════════════════════════════

        $pitchNightGeneral = TicketType::where('event_id', $pitchNight->id)
            ->where('kind', TicketKind::General->value)
            ->first();

        $pitchOrder = Order::create([
            'attendee_id'     => $attendee->id,
            'status'          => OrderStatus::Paid,
            'total'           => 30000,   // 1 × 300 BDT
            'currency'        => 'BDT',
            'commission_rate' => self::COMMISSION_RATE,
            'idempotency_key' => 'demo-pitch-dispute:'.Str::uuid(),
        ]);

        $pitchItem = OrderItem::create([
            'order_id'       => $pitchOrder->id,
            'ticket_type_id' => $pitchNightGeneral->id,
            'quantity'       => 1,
            'unit_price'     => 30000,
            'original_price' => 30000,
        ]);

        Ticket::create([
            'order_id'      => $pitchOrder->id,
            'order_item_id' => $pitchItem->id,
            'ticket_type_id'=> $pitchNightGeneral->id,
            'qr_code'       => 'PITCH-QR-'.strtoupper(Str::random(8)),
            'status'        => 'valid',
        ]);

        $pitchPayment = Payment::create([
            'order_id'        => $pitchOrder->id,
            'gateway'         => 'stripe_sim',
            'status'          => PaymentStatus::Succeeded,
            'external_ref'    => 'SEEDPITCH00000000000000001',
            'idempotency_key' => 'pay:pitch-dispute:'.Str::uuid(),
            'amount'          => 30000,
            'currency'        => 'BDT',
        ]);

        // 0 % refund → system rejected; dispute raised
        $pitchRefund = Refund::create([
            'payment_id'     => $pitchPayment->id,
            'amount'         => 0,
            'policy_applied' => '0',
            'status'         => RefundStatus::Failed,
            'reason'         => RefundReason::AttendeeRequested,
        ]);

        Dispute::create([
            'order_id'  => $pitchOrder->id,
            'refund_id' => $pitchRefund->id,
            'reason'    => 'Cannot attend due to emergency; event starts today.',
            'status'    => DisputeStatus::Open,
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // PENDING ORDER WITH ACTIVE HOLD — shows hold countdown in UI
        // (simulates an in-progress checkout by another attendee)
        // ════════════════════════════════════════════════════════════════════════

        $holdOrder = Order::create([
            'attendee_id'     => $attendee2->id,
            'status'          => OrderStatus::Pending,
            'total'           => 150000,  // 1 × 1500 BDT VIP
            'currency'        => 'BDT',
            'commission_rate' => self::COMMISSION_RATE,
            'idempotency_key' => 'demo-hold-pending:'.Str::uuid(),
        ]);

        $summitVip = TicketType::where('event_id', $summit->id)
            ->where('kind', TicketKind::Vip->value)
            ->first();

        OrderItem::create([
            'order_id'       => $holdOrder->id,
            'ticket_type_id' => $summitVip->id,
            'quantity'       => 1,
            'unit_price'     => 150000,
            'original_price' => 150000,
        ]);

        TicketHold::create([
            'order_id'       => $holdOrder->id,
            'ticket_type_id' => $summitVip->id,
            'quantity'       => 1,
            'status'         => HoldStatus::Active,
            'expires_at'     => now()->addMinutes(12), // ~12 min left on the hold
        ]);

        // ════════════════════════════════════════════════════════════════════════
        // [F] Dhaka DevFest 2025 — COMPLETED 3 days ago, NO payout yet
        //     → vendor can click "Request Payout" and see live preview + confirm
        // ════════════════════════════════════════════════════════════════════════

        $devFest = Event::factory()->completed()->forVendor($vendor)->create([
            'title'       => 'Dhaka DevFest 2025',
            'description' => 'Annual developer festival featuring tracks on mobile, web, cloud, and AI.',
            'starts_at'   => now()->subDays(3),
            'ends_at'     => now()->subDays(3)->addHours(8),
            'capacity'    => 400,
            'timezone'    => 'Asia/Dhaka',
        ]);

        $devFestGeneral = TicketType::factory()->forEvent($devFest)->create([
            'kind'           => TicketKind::General,
            'price'          => 200000,  // 2 000 BDT — revenue must exceed 5 000 BDT net threshold
            'quantity_total' => 400,
            'quantity_sold'  => 5,       // 3 orders (2+2+1 tickets)
            'sales_start'    => now()->subDays(30),
            'sales_end'      => now()->subDays(4),
        ]);

        $devFestBatches = [
            ['attendee' => $attendee,  'qty' => 2, 'unit' => 200000],  // 4 000 BDT
            ['attendee' => $attendee2, 'qty' => 2, 'unit' => 200000],  // 4 000 BDT
            ['attendee' => $attendee,  'qty' => 1, 'unit' => 200000],  // 2 000 BDT
        ];

        foreach ($devFestBatches as $i => $b) {
            $total = $b['qty'] * $b['unit'];
            $order = Order::create([
                'attendee_id'     => $b['attendee']->id,
                'status'          => OrderStatus::Paid,
                'total'           => $total,
                'currency'        => 'BDT',
                'commission_rate' => self::COMMISSION_RATE,
                'idempotency_key' => 'demo-devfest-'.($i + 1).':'.Str::uuid(),
            ]);

            $item = OrderItem::create([
                'order_id'       => $order->id,
                'ticket_type_id' => $devFestGeneral->id,
                'quantity'       => $b['qty'],
                'unit_price'     => $b['unit'],
                'original_price' => $b['unit'],
            ]);

            for ($t = 1; $t <= $b['qty']; $t++) {
                Ticket::create([
                    'order_id'      => $order->id,
                    'order_item_id' => $item->id,
                    'ticket_type_id'=> $devFestGeneral->id,
                    'qr_code'       => 'DEV-QR-'.strtoupper(Str::random(8)).'-'.$t,
                    'status'        => 'valid',
                ]);
            }

            Payment::create([
                'order_id'        => $order->id,
                'gateway'         => 'stripe_sim',
                'status'          => PaymentStatus::Succeeded,
                'external_ref'    => sprintf('SEEDDEV%019d', $i + 1),
                'idempotency_key' => 'pay:devfest-'.($i + 1).':'.Str::uuid(),
                'amount'          => $total,
                'currency'        => 'BDT',
            ]);

            $commissionAmount = (int) ($total * 0.10);

            LedgerEntry::create([
                'vendor_id'    => $vendor->id,
                'subject_type' => 'order',
                'subject_id'   => $order->id,
                'entry_type'   => LedgerEntryType::Sale,
                'amount'       => $total,
                'currency'     => 'BDT',
            ]);

            LedgerEntry::create([
                'vendor_id'    => $vendor->id,
                'subject_type' => 'order',
                'subject_id'   => $order->id,
                'entry_type'   => LedgerEntryType::Commission,
                'amount'       => -$commissionAmount,
                'currency'     => 'BDT',
            ]);
        }
        // No Payout or PayoutItem created for DevFest — vendor requests it live in the demo.

        // ── Print credentials ───────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('═══════════════ Demo credentials (test only — not real) ════════════════');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',        'admin@eventhub.test',    'password'],
                ['Vendor (KYC approved)', 'vendor@eventhub.test', 'password'],
                ['Vendor (pending KYC)',  'vendor2@eventhub.test', 'password'],
                ['Attendee 1',   'attendee@eventhub.test',  'password'],
                ['Attendee 2',   'attendee2@eventhub.test', 'password'],
            ]
        );
        $this->command->info('');
        $this->command->info('Seeded events:');
        $this->command->info('  [A] Published  → "DhakaTech Summit 2026"          (+30 days, early-bird sold out)');
        $this->command->info('  [B] Ongoing    → "Dhaka Startup Pitch Night"       (in progress, group bundle)');
        $this->command->info('  [C] Published  → "Laravel & Vue Workshop"          (+14 days)');
        $this->command->info('  [D] Completed  → "Bangladesh Open Source Day"      (10 days ago, PENDING payout pre-built)');
        $this->command->info('  [E] Completed  → "Fintech Innovation Summit"       (25 days ago, PAID payout)');
        $this->command->info('  [F] Completed  → "Dhaka DevFest 2025"              (3 days ago, NO payout → vendor can request live)');
        $this->command->info('');
        $this->command->info('Payout demo (admin panel):');
        $this->command->info('  1. Log in as admin → Payouts → 1 PENDING payout already seeded (Acme Events Ltd)');
        $this->command->info('  → Gross: 2500 BDT | Commission 10% | Net/Payable: 2250 BDT');
        $this->command->info('  2. Click "Execute" → payout status becomes Paid');
        $this->command->info('  3. Log in as vendor → Payouts → payout shows as Paid');
        $this->command->info('');
        $this->command->info('Refunds/Disputes in admin queue:');
        $this->command->info('  · Completed refund   → Alice refunded Laravel Workshop early-bird (100%)');
        $this->command->info('  · Requested refund   → Bob pending workshop refund (in admin queue)');
        $this->command->info('  · Open dispute       → Alice dispute on Pitch Night (0% policy, emergency)');
        $this->command->info('════════════════════════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
