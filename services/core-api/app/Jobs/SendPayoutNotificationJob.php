<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Enqueues the payout-result notification once a payout has resolved and the ledger is written
 * (mirrors {@see SendRefundConfirmationJob} for the refund path). Queued — never sent synchronously
 * on the webhook request path. Publish-only: the notification-service owns actual delivery.
 */
class SendPayoutNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $payoutId,
        public readonly string $status,
    ) {}

    public function handle(): void
    {
        LogHelper::logEntry(LogHelper::LOG_INFO, 'Payout notification queued', [
            'payout_id' => $this->payoutId,
            'status' => $this->status,
        ]);
    }
}
