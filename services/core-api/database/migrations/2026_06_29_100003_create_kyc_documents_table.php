<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uploaded KYC evidence (trade license / NID / bank statement). The document bytes live in object
     * storage; only an encrypted `storage_path` is kept, served exclusively via short-lived signed URLs.
     */
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vendor_id');
            $table->string('type');                       // trade_license|nid|bank_statement
            $table->text('storage_path');                 // encrypted; signed-URL access only
            $table->string('status')->default('pending'); // KycStatus enum
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id', 'idx_kyc_documents_vendor_id');
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
