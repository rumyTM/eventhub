<?php

namespace App\Repositories\Eloquent;

use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class VendorRepository implements VendorRepositoryInterface
{
    public function createForUser(User $user, array $attributes): Vendor
    {
        return $user->vendor()->create($attributes);
    }

    public function paginatePending(int $perPage): LengthAwarePaginator
    {
        return Vendor::query()
            ->where('kyc_status', KycStatus::Pending)
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at')
            ->paginate($perPage);
    }

    public function lockForUpdate(string $id): Vendor
    {
        return Vendor::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function update(Vendor $vendor, array $attributes): Vendor
    {
        $vendor->fill($attributes)->save();

        return $vendor->refresh();
    }

    public function addDocument(Vendor $vendor, array $attributes): KycDocument
    {
        return $vendor->kycDocuments()->create($attributes);
    }
}
