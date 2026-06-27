<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily derived read-model aggregating `ledger_entries` per (report_date, vendor_id); `vendor_id`
     * NULL means platform-wide. `unique(report_date, vendor_id)` makes vendor-scoped regeneration an
     * idempotent upsert. CAVEAT: MySQL treats each NULL as distinct, so the platform-wide (NULL) row is
     * deduped at the application layer (updateOrCreate). Can be recomputed from the ledger at any time.
     */
    public function up(): void
    {
        Schema::create('sales_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('report_date');
            $table->foreignUlid('vendor_id')->nullable()->constrained()->nullOnDelete(); // null = platform-wide
            $table->unsignedInteger('tickets_sold')->default(0);
            $table->unsignedBigInteger('gross')->default(0);      // minor units
            $table->unsignedBigInteger('commission')->default(0); // minor units
            $table->unsignedBigInteger('net')->default(0);        // minor units
            $table->string('currency', 3)->default('BDT');
            $table->timestamps();

            $table->unique(['report_date', 'vendor_id']); // idempotent daily regeneration (vendor-scoped)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_reports');
    }
};
