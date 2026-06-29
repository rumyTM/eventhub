<?php

namespace App\Jobs;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes an order.confirmation notification to the notification-service via Redis.
 * Dispatched after tickets are issued and the order is marked paid (CLAUDE.md §F.4).
 * Never called synchronously on the webhook request path.
 */
class SendOrderConfirmationJob implements ShouldQueue
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
            LogHelper::logEntry(LogHelper::LOG_WARNING, 'SendOrderConfirmationJob: order not found', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        $order->loadMissing('attendee.user', 'items.ticketType.event');

        $attendee = $order->attendee;
        $user = $attendee?->user;
        $firstEvent = $order->items->first()?->ticketType?->event;

        $publisher->publishEmail(
            type: 'order.confirmation',
            recipient: [
                'email' => $user?->email ?? '',
                'name' => $user?->name ?? '',
            ],
            data: [
                'orderId' => $order->id,
                'total' => $order->total,
                'currency' => $order->currency,
                'eventName' => $firstEvent?->name ?? '',
                'ticketCount' => $order->items->sum('quantity'),
            ],
            idempotencyKey: "notif:order.confirmation:{$this->orderId}",
        );
    }
}
