<?php

namespace App\Jobs;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes an order.created webhook to each vendor involved in the order.
 * Dispatched after tickets are issued and the order is marked paid (alongside SendOrderConfirmationJob).
 * Vendors with no registered webhook_url are silently skipped.
 */
class SendVendorOrderWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $orderId,
    ) {}

    public function handle(
        NotificationPublisherContract $publisher,
        OrderRepositoryInterface $orders,
    ): void {
        $order = $orders->find($this->orderId);

        if ($order === null) {
            LogHelper::logEntry(LogHelper::LOG_WARNING, 'SendVendorOrderWebhookJob: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $order->loadMissing('items.ticketType.event.vendor');

        // A single order may span multiple vendors — deliver one webhook per vendor.
        $vendors = $order->items
            ->map(fn ($item) => $item->ticketType?->event?->vendor)
            ->filter()
            ->unique('id');

        foreach ($vendors as $vendor) {
            if (empty($vendor->webhook_url)) {
                continue;
            }

            $publisher->publishWebhook(
                type: 'order.created',
                url: $vendor->webhook_url,
                recipient: ['vendorId' => $vendor->id],
                data: [
                    'orderId' => $order->id,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'ticketCount' => $order->items->sum('quantity'),
                ],
                idempotencyKey: "notif:order.created:{$this->orderId}:{$vendor->id}",
            );
        }
    }
}
