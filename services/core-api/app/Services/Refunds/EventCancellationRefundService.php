<?php

namespace App\Services\Refunds;

use App\Enums\RefundReason;
use App\Exceptions\Refunds\RefundNotAllowedException;
use App\Helpers\LogHelper;
use App\Http\Controllers\Api\V1\RefundController;
use App\Jobs\ExecuteRefundJob;
use App\Models\Refund;
use App\Repositories\Contracts\OrderRepositoryInterface;

/**
 * Bulk-refunds every attendee of a cancelled event (CLAUDE.md §F, ADR-23): cancelling a published/ongoing
 * event must refund every paid/partially-refunded order at a flat 100%, funded by a vendor clawback with
 * the platform also reversing its commission. Reuses {@see RefundService::request()} per order so the
 * existing one-open-refund idempotency guard, 100%-flat policy override, and reversal-ledger writes apply
 * unchanged — this only adds the enumeration + per-order dispatch that were previously a manual,
 * one-order-at-a-time admin action ({@see RefundController::initiate()}).
 */
final class EventCancellationRefundService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly RefundService $refunds,
    ) {}

    public function refundAll(string $eventId): void
    {
        foreach ($this->orders->paidOrderIdsForEvent($eventId) as $orderId) {
            $this->refundOne($orderId);
        }
    }

    /**
     * One order's failure (e.g. a concurrent state change) must never abort the batch — every other
     * attendee of the same event still needs their refund.
     */
    private function refundOne(string $orderId): void
    {
        $order = $this->orders->findForRefund($orderId);
        if ($order === null) {
            return;
        }

        try {
            $result = $this->refunds->request($order, RefundReason::EventCancelled);
        } catch (RefundNotAllowedException $e) {
            LogHelper::logEntry(LogHelper::LOG_ERROR, 'Event-cancellation refund skipped for order', [
                'order_id' => $orderId,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        if ($result instanceof Refund && $result->wasRecentlyCreated) {
            ExecuteRefundJob::dispatch($result->id);
        }
    }
}
