<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A refund this service executes against an original charge, on core-api's behalf. core-api decides
     * the *policy* and the amount (100/50/0% by time-to-event); this service executes the exact amount it
     * is told and records it. Mirrors the `payments` lifecycle: created `pending`, resolved to
     * `completed|failed` by the gateway, with the terminal result delivered via the signed webhook.
     *
     * `payment_id` is the original charge being refunded (a real `payments` row). `order_id` is
     * core-api's order reference, denormalized from the charge so the webhook can correlate without a
     * cross-service lookup (orders live in core-api's DB — no cross-db FK). Money is integer minor units
     * (poisha) + currency, never float. `amount` is the POSITIVE refund amount; the signed (negative)
     * money movement lives in the append-only `transactions` ledger. `gateway_ref` holds only a
     * clearly-fake simulated reference ([PLACEHOLDER]) — never a PAN, CVV, or real card token.
     * Idempotency is enforced via the shared `idempotency_keys` table, not a column here (ADR-09).
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_id');                 // the original charge being refunded
            $table->ulid('order_id');                          // core-api order reference (no cross-db FK)
            $table->string('gateway');                         // Gateway enum: stripe_sim|paypal_sim (from the charge)
            $table->string('status')->default('pending');      // RefundStatus: pending|completed|failed
            $table->unsignedBigInteger('amount');              // positive refund amount, minor units — never float
            $table->string('currency', 3)->default('BDT');
            $table->string('gateway_ref')->nullable();         // fake gateway ref ([PLACEHOLDER]) — never card data
            $table->string('reason')->nullable();              // why core-api refunded (free text, not card data)
            $table->timestamps();

            $table->index('payment_id', 'idx_refunds_payment_id'); // all refunds for a charge
            $table->index('order_id', 'idx_refunds_order_id');     // resolve a refund from its order
            // Restrict, not cascade: a charge is a financial record that is never deleted; a refund must
            // never silently vanish with it (mirrors core-api ADR-15 / the refunds FK there).
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
