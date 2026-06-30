<?php

namespace App\Repositories\Contracts;

use App\Models\KycDocument;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VendorRepositoryInterface
{
    /** Create the vendor profile owned by the given user. */
    public function createForUser(User $user, array $attributes): Vendor;

    /** Admin KYC review queue: vendors awaiting a decision (uses idx_vendors_kyc_status). */
    public function paginatePending(int $perPage): LengthAwarePaginator;

    /** Find a vendor by primary key, or null if not found. */
    public function find(string $id): ?Vendor;

    /** Re-read a vendor under a row lock (FOR UPDATE) — call inside a transaction. */
    public function lockForUpdate(string $id): Vendor;

    public function update(Vendor $vendor, array $attributes): Vendor;

    /** Attach a KYC evidence document (storage_path is a reference only — never raw bytes). */
    public function addDocument(Vendor $vendor, array $attributes): KycDocument;
}
