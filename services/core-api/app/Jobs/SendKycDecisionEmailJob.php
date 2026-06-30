<?php

namespace App\Jobs;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes a vendor.kyc_decision notification to the notification-service via Redis.
 * Dispatched after an admin verifies or rejects a vendor's KYC (CLAUDE.md §F, VendorService::decide).
 * Never called synchronously on the request path.
 */
class SendKycDecisionEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $vendorId,
        public readonly string $decision,
        public readonly ?string $rejectionReason = null,
    ) {}

    public function handle(
        NotificationPublisherContract $publisher,
        VendorRepositoryInterface $vendors,
    ): void {
        $vendor = $vendors->find($this->vendorId);

        if ($vendor === null) {
            LogHelper::logEntry(LogHelper::LOG_WARNING, 'SendKycDecisionEmailJob: vendor not found', [
                'vendor_id' => $this->vendorId,
            ]);

            return;
        }

        $vendor->loadMissing('user');
        $user = $vendor->user;

        $publisher->publishEmail(
            type: 'vendor.kyc_decision',
            recipient: [
                'email' => $user?->email ?? '',
                'name' => $user?->name ?? '',
                'vendorId' => $vendor->id,
            ],
            data: [
                'vendorId' => $vendor->id,
                'decision' => $this->decision,
                'rejectionReason' => $this->rejectionReason,
            ],
            idempotencyKey: "notif:vendor.kyc_decision:{$this->vendorId}:{$this->decision}",
        );
    }
}
