<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settlement traceability: records the exact (payout_id, order_id, settled_amount) for each order a
     * payout covered — essential for reconciliation and the clawback story (ADR-20). Never deleted.
     */
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payout_id');
            $table->foreignUlid('order_id');
            $table->unsignedBigInteger('settled_amount'); // minor units
            $table->timestamps();

            $table->unique(['payout_id', 'order_id'], 'uq_payout_items_payout_order'); // C-2: DB guard, no dup items
            $table->index('payout_id', 'idx_payout_items_payout_id'); // orders a payout settled
            $table->index('order_id', 'idx_payout_items_order_id');   // has an order been settled?
            $table->foreign('payout_id')->references('id')->on('payouts')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
