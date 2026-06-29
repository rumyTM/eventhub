<?php

namespace App\Jobs;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes a payout.completed notification to the notification-service via Redis.
 * Dispatched after a payout's webhook is processed and the ledger entry is written.
 * Never called synchronously on the webhook request path.
 */
class SendPayoutNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $payoutId,
        public readonly string $status,
    ) {}

    public function handle(
        NotificationPublisherContract $publisher,
        PayoutRepositoryInterface $payouts,
    ): void {
        $payout = $payouts->find($this->payoutId);

        if ($payout === null) {
            LogHelper::logEntry(LogHelper::LOG_WARNING, 'SendPayoutNotificationJob: payout not found', [
                'payout_id' => $this->payoutId,
            ]);
            return;
        }

        $payout->loadMissing('vendor.user');

        $vendor = $payout->vendor;
        $user = $vendor?->user;

        $publisher->publishEmail(
            type: 'payout.completed',
            recipient: [
                'email' => $user?->email ?? '',
                'name' => $user?->name ?? '',
                'vendorId' => $vendor?->id ?? '',
            ],
            data: [
                'payoutId' => $payout->id,
                'payable' => $payout->payable,
                'currency' => $payout->currency,
                'status' => $this->status,
            ],
            idempotencyKey: "notif:payout.completed:{$this->payoutId}",
        );
    }
}
