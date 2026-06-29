<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payout execution record — one per core-api payout that this service processes on behalf of a
     * vendor. Mirrors the `refunds` lifecycle: created `pending`, resolved to `completed|failed` by
     * the gateway, terminal result delivered via the signed payout webhook to core-api.
     *
     * `payout_ref` is core-api's Payout ULID (the correlation key returned in the webhook). `vendor_id`
     * is core-api's vendor ID, denormalized so the webhook can correlate without a cross-service lookup.
     * `amount` is the positive disbursable amount in integer minor units (poisha) — never float, never
     * a PAN or card token. The signed (negative) movement lives in the append-only `transactions` ledger.
     * Idempotency is enforced via the `idempotency_key` unique column (ADR-09).
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('payout_ref');                      // core-api Payout ID (correlation, no cross-db FK)
            $table->string('vendor_id');                       // core-api vendor ID (no cross-db FK)
            $table->unsignedBigInteger('amount');              // positive disbursable amount, minor units — never float
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('pending');      // PayoutStatus: pending|completed|failed
            $table->string('gateway_ref')->nullable();         // fake gateway ref ([PLACEHOLDER]) — never card data
            $table->string('idempotency_key')->unique();       // ADR-09: one execution per key
            $table->timestamps();

            $table->index('payout_ref', 'idx_payouts_payout_ref'); // correlate by core-api payout ID
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
