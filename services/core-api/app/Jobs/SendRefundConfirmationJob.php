<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Enqueues the refund-confirmation notification once a refund has completed and the reversal ledger is
 * written (mirrors {@see SendOrderConfirmationJob} for the charge path). Queued — never sent
 * synchronously on the webhook request path.
 *
 * Actual delivery (email to the attendee) is the notification-service's job; this core-api job is the
 * publish point. Wiring it onto the Redis queue the Node service consumes lands with the notification
 * slice — until then it records the intent under the shared trace id (which auto-propagates into the
 * queued job via Context), so the end-to-end journey stays correlated.
 */
class SendRefundConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $refundId,
    ) {}

    public function handle(): void
    {
        LogHelper::logEntry(LogHelper::LOG_INFO, 'Refund confirmation queued', [
            'refund_id' => $this->refundId,
        ]);
    }
}
