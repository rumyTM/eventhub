<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the `transactions` ledger to support payout-type rows. Payout transactions have no
     * associated charge (`payment_id` is null for them), so:
     *   - `payment_id` becomes nullable (payout rows set it to null);
     *   - `payout_id` is added (nullable; set only for payout-type rows, referencing the `payouts` table).
     *
     * For MySQL: the FK constraint on `payment_id` is dropped and re-added after the nullable change,
     * since MySQL requires the FK to be dropped before modifying a constrained column. For SQLite
     * (tests), Laravel handles the column change via a table-rebuild and FK enforcement is not active.
     */
    public function up(): void
    {
        // Add payout_id first (new column, always safe).
        Schema::table('transactions', function (Blueprint $table): void {
            $table->ulid('payout_id')->nullable()->after('payment_id');
        });

        // Make payment_id nullable. On MySQL, drop the FK first; re-add after the change.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropForeign('transactions_payment_id_foreign');
            });
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignUlid('payment_id')->nullable()->change();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('payout_id');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropForeign('transactions_payment_id_foreign');
            });
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignUlid('payment_id')->nullable(false)->change();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            });
        }
    }
};
