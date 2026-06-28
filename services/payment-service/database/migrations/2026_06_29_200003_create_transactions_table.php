<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only financial ledger for this service (CLAUDE.md §G; the C-2 item from the worklog).
     * One row is written when a charge *resolves* — never on the pending creation — mirroring how
     * core-api writes its `ledger_entry` only on a terminal result, not on a hold.
     *
     * Append-only by construction: there is a `created_at` but **no `updated_at`** — a row is the
     * immutable record of what happened, a status change appends a new row, history is never
     * rewritten. `amount` is **signed** integer minor units (poisha) so direction lives in the sign
     * (a charge is positive money-in; a future refund/payout is negative) and SUM(amount) is the
     * service's net position — a failed charge moves no money, so it is recorded as 0.
     *
     * `gateway_ref` holds only a clearly-fake simulated reference ([PLACEHOLDER]) — this service
     * NEVER stores a PAN, CVV, or real card token.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Restrict, not cascade: this is the append-only financial ledger — a payment (a financial
            // record) is never deleted, and its ledger history must never silently vanish with it.
            $table->foreignUlid('payment_id')->constrained()->restrictOnDelete();
            $table->string('type');                            // TransactionType: charge|refund|payout
            $table->bigInteger('amount');                      // SIGNED minor units (poisha) — direction in the sign
            $table->string('currency', 3)->default('BDT');
            $table->string('gateway_ref')->nullable();         // fake gateway ref ([PLACEHOLDER]) — never card data
            $table->timestamp('created_at')->nullable();       // append-only — no updated_at

            $table->index('payment_id', 'idx_transactions_payment_id'); // all ledger rows for a payment
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
