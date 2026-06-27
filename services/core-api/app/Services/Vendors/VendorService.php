<?php

namespace App\Services\Vendors;

use App\Enums\KycStatus;
use App\Exceptions\Vendors\InvalidKycTransitionException;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Vendor KYC orchestration. All decisions run inside a transaction under the vendor row lock so two
 * concurrent admin reviews can't both flip a terminal status. PII is never logged or returned here.
 */
final class VendorService
{
    public function __construct(
        private readonly VendorRepositoryInterface $vendors,
    ) {}

    public function listPending(int $perPage): LengthAwarePaginator
    {
        return $this->vendors->paginatePending($perPage);
    }

    /**
     * Vendor submits (or re-submits after rejection) for review: stamps submitted_at, keeps/returns
     * kyc_status = pending, and attaches the supplied KYC evidence documents. A verified profile cannot
     * be re-submitted.
     *
     * @param  list<array{type: string, storage_path: string}>  $documents
     */
    public function submitForReview(Vendor $vendor, array $documents): Vendor
    {
        return DB::transaction(function () use ($vendor, $documents): Vendor {
            $locked = $this->vendors->lockForUpdate($vendor->id);

            if ($locked->kyc_status === KycStatus::Verified) {
                throw new InvalidKycTransitionException;
            }

            $this->vendors->update($locked, [
                'kyc_status' => KycStatus::Pending,
                'submitted_at' => now(),
                // A re-submission clears the prior rejection note.
                'rejection_reason' => null,
            ]);

            foreach ($documents as $document) {
                $this->vendors->addDocument($locked, [
                    'type' => $document['type'],
                    'storage_path' => $document['storage_path'], // reference only — never raw bytes
                    'status' => KycStatus::Pending,
                    'uploaded_at' => now(),
                ]);
            }

            return $locked->refresh()->load('kycDocuments');
        });
    }

    /** Admin: pending → verified, stamping the reviewer. */
    public function verify(Vendor $vendor, User $admin): Vendor
    {
        return $this->decide($vendor, KycStatus::Verified, $admin, rejectionReason: null);
    }

    /** Admin: pending → rejected, with a reason, stamping the reviewer. */
    public function reject(Vendor $vendor, User $admin, string $reason): Vendor
    {
        return $this->decide($vendor, KycStatus::Rejected, $admin, rejectionReason: $reason);
    }

    private function decide(Vendor $vendor, KycStatus $target, User $admin, ?string $rejectionReason): Vendor
    {
        return DB::transaction(function () use ($vendor, $target, $admin, $rejectionReason): Vendor {
            $locked = $this->vendors->lockForUpdate($vendor->id);

            if (! $locked->kyc_status->canTransitionTo($target)) {
                throw new InvalidKycTransitionException;
            }

            return $this->vendors->update($locked, [
                'kyc_status' => $target,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);
        });
    }
}
