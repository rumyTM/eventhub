<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders placed by an attendee. NEVER soft/hard-deleted — lifecycle is expressed via `status`.
     * `total` is integer minor units; `commission_rate` is the rate snapshot fixed at sale time so
     * historical payout math stays reproducible. `idempotency_key` makes re-checkout return the same order.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attendee_id');
            $table->string('status')->default('pending');           // OrderStatus enum
            $table->unsignedBigInteger('total')->default(0);        // minor units
            $table->string('currency', 3)->default('BDT');
            $table->decimal('commission_rate', 5, 4)->nullable();   // snapshot at sale time
            $table->string('idempotency_key')->unique();           // idempotent re-checkout
            $table->timestamps();

            $table->index('attendee_id', 'idx_orders_attendee_id'); // attendee order history
            $table->foreign('attendee_id')->references('id')->on('attendees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
