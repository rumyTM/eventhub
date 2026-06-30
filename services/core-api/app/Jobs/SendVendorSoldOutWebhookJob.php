<?php

namespace App\Jobs;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Publishes an event.sold_out webhook for any ticket type in the order that just hit zero availability.
 * Dispatched after tickets are issued and quantity_sold has been incremented.
 * Idempotency key is per-ticket-type, so concurrent checkouts that all tip a type to sold_out
 * deliver exactly one webhook regardless of how many orders finished around the same moment.
 */
class SendVendorSoldOutWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $orderId,
    ) {}

    public function handle(
        NotificationPublisherContract $publisher,
        OrderRepositoryInterface $orders,
        TicketTypeRepositoryInterface $ticketTypes,
    ): void {
        $order = $orders->find($this->orderId);

        if ($order === null) {
            LogHelper::logEntry(LogHelper::LOG_WARNING, 'SendVendorSoldOutWebhookJob: order not found', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $order->loadMissing('items');

        foreach ($order->items->unique('ticket_type_id') as $item) {
            // Fresh read — quantity_sold was already incremented by the settlement transaction.
            $ticketType = $ticketTypes->find($item->ticket_type_id);

            if ($ticketType === null || $ticketType->quantity_sold < $ticketType->quantity_total) {
                continue;
            }

            $ticketType->loadMissing('event.vendor');
            $vendor = $ticketType->event?->vendor;

            if ($vendor === null || empty($vendor->webhook_url)) {
                continue;
            }

            $publisher->publishWebhook(
                type: 'event.sold_out',
                url: $vendor->webhook_url,
                recipient: ['vendorId' => $vendor->id],
                data: [
                    'ticketTypeId' => $ticketType->id,
                    'eventId' => $ticketType->event_id,
                    'quantityTotal' => $ticketType->quantity_total,
                ],
                idempotencyKey: "notif:event.sold_out:{$item->ticket_type_id}",
            );
        }
    }
}
