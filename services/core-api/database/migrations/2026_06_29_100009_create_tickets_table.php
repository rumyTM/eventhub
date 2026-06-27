<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issued, QR-coded ticket artifacts — created ONLY after payment succeeds. Never deleted.
     * Each ticket hangs off an `order_item` (a group bundle of N units issues N tickets); `order_id`
     * is denormalized for direct listing. A ticket's `status` tracks its own lifecycle independently.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_item_id');
            $table->foreignUlid('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->string('qr_code')->unique();              // QR check-in lookup
            $table->string('status')->default('valid');       // TicketStatus enum
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignUlid('checked_in_by')->nullable()->constrained('users')->nullOnDelete(); // staff/admin

            $table->index('order_item_id', 'idx_tickets_order_item_id'); // N tickets for a line / bundle
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
