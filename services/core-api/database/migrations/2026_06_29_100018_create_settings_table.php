<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configurable platform values (commission rate, minimum payout threshold). Read at sale time
     * and snapshotted onto orders.commission_rate, so editing a setting only affects future sales.
     * Updated in place (config, not history) — no soft-delete.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();           // single-row lookup of a platform setting
            $table->string('value')->nullable();
            $table->string('type')->default('string'); // int|decimal|string|bool
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
