<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `original_price` to order_items to surface discount info on receipts.
     * NOT NULL: equals unit_price when no group discount applies, pre-discount price otherwise.
     * DB is reseeded on deploy so no backfill is required.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Per-unit price BEFORE any group discount (minor units). Always set at hold creation.
            $table->unsignedBigInteger('original_price')->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('original_price');
        });
    }
};
