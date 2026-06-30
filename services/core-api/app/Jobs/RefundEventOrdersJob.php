<?php

namespace App\Jobs;

use App\Services\Events\EventService;
use App\Services\Refunds\EventCancellationRefundService;
use App\Services\Refunds\RefundService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fires once per event cancellation (dispatched from {@see EventService::update()}
 * on the published/ongoing → cancelled transition, CLAUDE.md §F). Refunds every paid/partially-refunded
 * order for the event at the policy-overridden 100% (ADR-23) — the batch equivalent of the admin's
 * one-order-at-a-time cancellation refund.
 *
 * `ShouldBeUnique` on the event id: a retried/duplicate dispatch can never run the batch twice
 * concurrently for the same event. Per-order idempotency (one open refund per order) still comes from
 * {@see RefundService::request()} beneath it, so a retry after partial failure
 * never double-refunds an order already handled.
 */
class RefundEventOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $eventId,
    ) {}

    public function uniqueId(): string
    {
        return $this->eventId;
    }

    public function handle(EventCancellationRefundService $refunds): void
    {
        $refunds->refundAll($this->eventId);
    }
}
