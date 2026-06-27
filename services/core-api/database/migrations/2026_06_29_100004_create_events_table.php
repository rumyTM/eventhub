<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events owned by a vendor. `capacity` is a hard ceiling: SUM(ticket_types.quantity_total) <= capacity
     * is enforced at ticket-type create/edit. Datetimes are stored in UTC; `timezone` is the IANA zone for display.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vendor_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('timezone')->default('Asia/Dhaka'); // IANA
            $table->timestamp('starts_at');                    // UTC
            $table->timestamp('ends_at');                      // UTC
            $table->unsignedInteger('capacity');
            $table->string('status')->default('draft');        // EventStatus enum
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id', 'idx_events_vendor_id');                  // a vendor's own events
            $table->index(['status', 'starts_at'], 'idx_events_status_starts_at'); // public listing + reminder cron
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
