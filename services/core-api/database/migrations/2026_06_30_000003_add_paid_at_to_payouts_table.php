<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a write-once `paid_at` timestamp to payouts. This replaces `updated_at` as the anchor
     * for the clawback window — `updated_at` is mutable (a processing→processing retry updates it)
     * and can push the window boundary past legitimate clawback entries, silently dropping them.
     * `paid_at` is set only when the payout transitions to `paid`, never touched again.
     */
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
