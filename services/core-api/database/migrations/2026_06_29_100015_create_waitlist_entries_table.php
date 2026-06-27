<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waitlist for a sold-out ticket type. When a ticket frees, the next `position` is offered:
     * `offered_at` stamped, `claim_expires_at = offered_at + 30 min`. A cron expires unclaimed offers.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_type_id');
            $table->foreignUlid('attendee_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('status')->default('waiting');     // WaitlistStatus enum
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable(); // +30 min
            $table->timestamps();

            // offer a freed ticket to the next person in line
            $table->index(['ticket_type_id', 'status', 'position'], 'idx_waitlist_type_status_pos');
            // cron expiring unclaimed 30-min offers
            $table->index(['status', 'claim_expires_at'], 'idx_waitlist_claim_expires');
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
