<?php

namespace App\Jobs;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes a payout.sent webhook to the vendor when a payout completes successfully.
 * Dispatched after the payout webhook from payment-service is processed and the ledger entry written.
 * Vendors with no registered webhook_url are silently skipped.
 */
class SendVendorPayoutWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $payoutId,
    ) {}

    public function handle(
        NotificationPublisherContract $publisher,
        PayoutRepositoryInterface $payouts,
    ): void {
        $payout = $payouts->find($this->payoutId);

        if ($payout === null) {
            LogHelper::logEntry(LogHelper::LOG_WARNING, 'SendVendorPayoutWebhookJob: payout not found', [
                'payout_id' => $this->payoutId,
            ]);

            return;
        }

        $payout->loadMissing('vendor');
        $vendor = $payout->vendor;

        if ($vendor === null || empty($vendor->webhook_url)) {
            return;
        }

        $publisher->publishWebhook(
            type: 'payout.sent',
            url: $vendor->webhook_url,
            recipient: ['vendorId' => $vendor->id],
            data: [
                'payoutId' => $payout->id,
                'payable' => $payout->payable,
                'currency' => $payout->currency,
            ],
            idempotencyKey: "notif:payout.sent:{$this->payoutId}",
        );
    }
}
