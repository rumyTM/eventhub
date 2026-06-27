<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-backed idempotency guard for money operations (checkout, charge, refund, payout). Stores
     * key -> result; a duplicate request returns the original `response_payload` without re-running the
     * side effect. DB-backed so it survives a Redis outage (ADR-09). May be pruned by a retention job.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();             // duplicate money-operation guard
            $table->string('request_hash');              // detect key reuse with a different body
            $table->json('response_payload')->nullable();
            $table->string('status');                    // e.g. processing|completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
