<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ticket tiers within an event. `price` is integer minor units (poisha). `quantity_sold` is a
     * denormalized counter incremented transactionally on payment success only (availability hot path).
     * A "group bundle" is modelled as attributes (`group_size` + `group_discount`), not a separate SKU.
     */
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id');
            $table->string('kind');                          // TicketKind enum (early_bird|vip|general)
            $table->unsignedBigInteger('price');             // minor units, per unit
            $table->string('currency', 3)->default('BDT');   // ISO 4217
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('group_size')->nullable();        // N units per bundle
            $table->decimal('group_discount', 5, 4)->nullable();      // fraction off N units
            $table->timestamp('sales_start')->nullable();
            $table->timestamp('sales_end')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id', 'idx_ticket_types_event_id');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
