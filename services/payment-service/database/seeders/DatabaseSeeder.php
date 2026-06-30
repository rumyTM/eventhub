<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the payment-service database with succeeded payment records that correspond to the
     * seeded orders in core-api. The `id` of each record here must match the `external_ref` stored
     * on core-api's `payments` table — that is the stable handle core-api uses when routing refunds.
     *
     * Amounts must be >= any refund that will be requested (the service guards against over-refund).
     */
    public function run(): void
    {
        $now = now()->toDateTimeString();

        $payments = [
            // ── Bangladesh Open Source Day (5 orders × 50 000 BDT each) ──
            ['id' => sprintf('SEEDOSD%019d', 1), 'gateway' => 'stripe_sim', 'amount' => 50000],
            ['id' => sprintf('SEEDOSD%019d', 2), 'gateway' => 'stripe_sim', 'amount' => 50000],
            ['id' => sprintf('SEEDOSD%019d', 3), 'gateway' => 'stripe_sim', 'amount' => 50000],
            ['id' => sprintf('SEEDOSD%019d', 4), 'gateway' => 'stripe_sim', 'amount' => 50000],
            ['id' => sprintf('SEEDOSD%019d', 5), 'gateway' => 'stripe_sim', 'amount' => 50000],
            // ── Fintech Innovation Summit (2 orders) ──
            ['id' => sprintf('SEEDFIN%019d', 1), 'gateway' => 'paypal_sim', 'amount' =>  60000],
            ['id' => sprintf('SEEDFIN%019d', 2), 'gateway' => 'paypal_sim', 'amount' =>  90000],
            // ── DhakaTech Summit — Alice (2 × 500 BDT = 1 000 BDT, live refund demo) ──
            ['id' => 'SEEDSUMALICE00000000000001', 'gateway' => 'stripe_sim', 'amount' => 100000],
            // ── DhakaTech Summit — Bob (1 × 500 BDT) ──
            ['id' => 'SEEDSUMBOB0000000000000001', 'gateway' => 'stripe_sim', 'amount' =>  50000],
            // ── Laravel & Vue Workshop — Alice refunded order ──
            ['id' => 'SEEDWSHPRF0000000000000001', 'gateway' => 'stripe_sim', 'amount' =>  60000],
            // ── Laravel & Vue Workshop — Bob requested-refund order ──
            ['id' => 'SEEDWSHPBOB000000000000001', 'gateway' => 'stripe_sim', 'amount' =>  60000],
            // ── Dhaka Startup Pitch Night — Alice dispute order ──
            ['id' => 'SEEDPITCH00000000000000001', 'gateway' => 'stripe_sim', 'amount' =>  30000],
            // ── Dhaka DevFest 2025 — 3 orders, NO payout yet (vendor requests live) ──
            ['id' => sprintf('SEEDDEV%019d', 1), 'gateway' => 'stripe_sim', 'amount' => 400000],
            ['id' => sprintf('SEEDDEV%019d', 2), 'gateway' => 'stripe_sim', 'amount' => 400000],
            ['id' => sprintf('SEEDDEV%019d', 3), 'gateway' => 'stripe_sim', 'amount' => 200000],
        ];

        foreach ($payments as $p) {
            DB::table('payments')->insertOrIgnore([
                'id'          => $p['id'],
                'order_id'    => $p['id'],   // mirror id — core-api order IDs are not known here
                'gateway'     => $p['gateway'],
                'status'      => 'succeeded',
                'amount'      => $p['amount'],
                'currency'    => 'BDT',
                'gateway_ref' => '[DEMO-GATEWAY-REF-' . $p['id'] . ']',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
