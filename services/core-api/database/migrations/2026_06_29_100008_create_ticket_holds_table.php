<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transient count reservations during checkout (15-min expiry). Availability counts only
     * non-expired active holds (status='active' AND expires_at > now()) — expiry is enforced at
     * READ time, not by the cron. A hold converts to issued tickets on payment success.
     */
    public function up(): void
    {
        Schema::create('ticket_holds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_type_id');
            $table->unsignedInteger('quantity');
            $table->string('status')->default('active'); // HoldStatus enum (active|released|converted)
            $table->timestamp('expires_at');
            $table->timestamps();

            // SUM(quantity) of active holds for the availability check (under lock)
            $table->index(['ticket_type_id', 'status'], 'idx_holds_type_status');
            // ReleaseExpiredHolds cron sweeping active+expired holds
            $table->index(['status', 'expires_at'], 'idx_holds_status_expires_at');
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_holds');
    }
};
