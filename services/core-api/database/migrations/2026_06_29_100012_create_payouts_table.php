<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor settlements. `net = gross − commission` (accounting net; may be negative in edge cases).
     * `payable = net + adjustments` (refunds/clawbacks), floored at 0 — the actual disbursable amount.
     * `reserved_refund` is held against not-yet-settled orders (in-window refunds). `idempotency_key` +
     * `batch_id` guarantee no double-pay on retry/mid-batch crash. Revenue is settled only after an
     * order's event is `completed` (ADR-20). Never deleted.
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vendor_id');
            $table->unsignedBigInteger('gross');                   // minor units — SUM of sale entries
            $table->unsignedBigInteger('commission');              // absolute value of commission entries
            $table->bigInteger('net');                             // gross − commission (signed; H-2 fix)
            $table->bigInteger('payable');                         // net + adjustments, floored at 0; sent to payment-service
            $table->unsignedBigInteger('reserved_refund')->default(0); // held for in-window refunds
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('pending');          // PayoutStatus enum
            $table->string('batch_id');                            // M-1: always required for idempotency
            $table->string('idempotency_key')->unique();           // no double-pay on retry
            $table->timestamps();

            $table->index(['vendor_id', 'status'], 'idx_payouts_vendor_status'); // payout history / pending
            $table->index('batch_id', 'idx_payouts_batch_id');                   // reconcile a batch run
            // restrictOnDelete: vendor hard-delete must never silently destroy the financial audit trail (C-2)
            $table->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
