<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A charge attempt this service processes on core-api's behalf. `order_id` is core-api's order
     * reference (orders live in core-api's DB — no cross-service FK here). Money is integer minor
     * units (poisha) + currency, never float. `gateway_ref` holds only a clearly-fake gateway
     * reference ([PLACEHOLDER]) — this service NEVER stores a PAN, CVV, or real card token.
     * Idempotency is enforced via the `idempotency_keys` table, not a column here (ADR-09).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('order_id');                          // core-api order reference (no cross-db FK)
            $table->string('gateway');                         // Gateway enum: stripe_sim|paypal_sim
            $table->string('status')->default('pending');      // PaymentStatus: pending|succeeded|failed
            $table->unsignedBigInteger('amount');              // integer minor units (poisha) — never float
            $table->string('currency', 3)->default('BDT');
            $table->string('gateway_ref')->nullable();         // fake gateway ref ([PLACEHOLDER]) — never card data
            $table->timestamps();

            $table->index('order_id', 'idx_payments_order_id'); // resolve a payment from its order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
