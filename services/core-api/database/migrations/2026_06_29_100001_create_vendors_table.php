<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor (organizer) profile — 1:1 with a `vendor`-role user. Holds KYC identity + payout config.
     * Sensitive identifiers (tin_bin, representative_nid, payout_account, webhook_secret) are encrypted
     * at the application layer (see Vendor model casts) — the columns store ciphertext, hence `text`.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->ulid('id')->primary();                       // ULID PK (ADR-19)
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete(); // 1:1 with users

            $table->string('business_name');
            $table->string('legal_name')->nullable();
            $table->string('trade_license_no')->nullable();
            $table->text('tin_bin')->nullable();                 // encrypted
            $table->text('representative_nid')->nullable();       // encrypted
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();

            $table->string('kyc_status')->default('pending');     // KycStatus enum
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // admin reviewer
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->text('payout_account')->nullable();           // encrypted:array ([PLACEHOLDER])
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();           // encrypted per-vendor HMAC secret
            $table->decimal('commission_rate', 5, 4)->nullable(); // per-vendor override (fraction)

            $table->timestamps();
            $table->softDeletes();

            $table->index('kyc_status', 'idx_vendors_kyc_status'); // admin KYC review queue
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
