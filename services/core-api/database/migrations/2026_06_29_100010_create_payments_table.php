<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Charge attempts against an order (1:N — retries). At most one reaches `succeeded`; that one
     * drives ticket issuance + the ledger. Each attempt carries its own `idempotency_key` (the key
     * core-api sends to the payment-service), so a retried network call de-dupes at the gateway.
     * `external_ref` holds the gateway reference ([PLACEHOLDER]; never card data). Never deleted.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id');
            $table->string('gateway');                       // stripe_sim|paypal_sim
            $table->string('status')->default('pending');    // PaymentStatus enum
            $table->string('external_ref')->nullable();      // [PLACEHOLDER] gateway ref
            $table->string('idempotency_key')->unique();     // de-dupe a retried charge attempt
            $table->unsignedBigInteger('amount');            // minor units
            $table->string('currency', 3)->default('BDT');
            $table->timestamps();

            $table->index('order_id', 'idx_payments_order_id'); // resolve payment from order / webhook
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
