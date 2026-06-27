<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Out-of-policy refund contests an admin mediates. Belongs to an order; resolved by an admin
     * (`resolved_by`); optionally references the `refund` that settled it (nullable). Never deleted.
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('refund_id')->nullable()->constrained()->nullOnDelete(); // resolution refund
            $table->string('reason');
            $table->string('status')->default('open');     // DisputeStatus enum
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete(); // admin
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_disputes_status'); // admin dispute queue
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
