<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotent reminder marker: one row per (event_id, type) records that the SendEventReminders batch
     * fired for that window, so re-running the cron never double-dispatches. Per-recipient delivery is
     * tracked in the notification-service, not here. Never deleted.
     */
    public function up(): void
    {
        Schema::create('event_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id');
            $table->string('type');                  // 24h|1h
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'type']);    // one send per window
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
    }
};
