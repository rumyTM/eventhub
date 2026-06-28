<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-backed idempotency guard for every money operation (charge, refund, payout). Mirrors
     * core-api's table: stores key -> result; a duplicate request with the same body returns the
     * stored `response_payload` without re-running the side effect, and the same key with a
     * different body is a 409 conflict. DB-backed so the guarantee survives a Redis outage (ADR-09).
     * The key is also the natural dedupe for retried calls from core-api.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();             // duplicate money-operation guard
            $table->string('request_hash');              // detect key reuse with a different body
            $table->json('response_payload')->nullable();
            $table->string('status');                    // processing|completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
