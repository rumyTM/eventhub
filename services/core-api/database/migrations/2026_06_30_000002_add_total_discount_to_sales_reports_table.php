<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `total_discount` to sales_reports — the sum of (original_price − unit_price) × quantity
     * across all paid order_items for the report period, representing gross discount given to attendees.
     */
    public function up(): void
    {
        Schema::table('sales_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('total_discount')->default(0)->after('net');
        });
    }

    public function down(): void
    {
        Schema::table('sales_reports', function (Blueprint $table) {
            $table->dropColumn('total_discount');
        });
    }
};
