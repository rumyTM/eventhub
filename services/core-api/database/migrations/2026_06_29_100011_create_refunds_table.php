<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refunds against a succeeded payment (1:N — partial/dispute refunds over time). `amount` is the
     * policy-derived figure (policy% x selected line totals); the attendee never specifies an amount.
     * Cumulative refunded is validated against the ledger so it never exceeds the charge. Never deleted.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_id');
            $table->unsignedBigInteger('amount');            // minor units, auto-derived (policy% x base)
            $table->string('policy_applied');                // 100|50|0
            $table->string('status')->default('requested');  // RefundStatus: requested→pending→completed|failed
            $table->string('reason')->nullable();             // RefundReason category (attendee_requested|event_cancelled)
            $table->timestamps();

            $table->index('payment_id', 'idx_refunds_payment_id'); // cumulative-refund validation
            // Restrict, not cascade: payments are financial records that are never deleted (ADR-15); a
            // refund must never silently vanish with its charge, which would destroy the audit trail.
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
