<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only, polymorphic financial ledger — the single source of truth for money. Every financial
     * state change writes a new SIGNED row; rows are NEVER updated or deleted (corrections are offsetting
     * entries, e.g. negative `clawback`). Vendor balance is DERIVED: SUM(amount) WHERE vendor_id = ?.
     * `subject_type`/`subject_id` point at the order/payment/refund/payout that caused the entry.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vendor_id')->nullable();   // attribution for balance math
            $table->string('subject_type');                 // order|payment|refund|payout
            $table->ulid('subject_id');                     // polymorphic (no FK)
            $table->string('entry_type');                   // sale|commission|payout|refund|clawback
            $table->bigInteger('amount');                   // minor units, SIGNED (+/-)
            $table->string('currency', 3)->default('BDT');
            $table->timestamp('created_at')->useCurrent();  // append-only; no updated_at

            // vendor balance aggregate + next-payout window
            $table->index(['vendor_id', 'created_at'], 'idx_ledger_vendor_created');
            // trace every entry caused by one order/payment/payout
            $table->index(['subject_type', 'subject_id'], 'idx_ledger_subject');
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
