<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Enqueues the order-confirmation notification once an order is paid and its tickets are issued
 * (CLAUDE.md §F.4). Queued — never sent synchronously on the webhook request path.
 *
 * Actual delivery (email + the attendee's tickets) is the notification-service's job; this core-api
 * job is the publish point. Wiring it onto the Redis queue the Node service consumes lands with the
 * notification slice — until then it records the intent under the shared trace id (which auto-
 * propagates into the queued job via Context), so the end-to-end journey stays correlated.
 */
class SendOrderConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $orderId,
    ) {}

    public function handle(): void
    {
        LogHelper::logEntry(LogHelper::LOG_INFO, 'Order confirmation queued', [
            'order_id' => $this->orderId,
        ]);
    }
}
