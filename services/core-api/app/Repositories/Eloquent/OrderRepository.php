<?php

namespace App\Repositories\Eloquent;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $attributes): Order
    {
        return Order::create($attributes);
    }

    public function addItem(Order $order, array $attributes): OrderItem
    {
        return $order->items()->create($attributes);
    }

    public function findWithLines(string $id): Order
    {
        return Order::query()->with(['items', 'holds'])->findOrFail($id);
    }

    public function findForRefund(string $id): ?Order
    {
        return Order::query()
            ->with([
                // ticket_types and events are soft-deletable; pull them withTrashed so a refund on a
                // historical/cancelled event can still resolve its start time for the policy window.
                'items.ticketType' => fn ($q) => $q->withTrashed(),
                'items.ticketType.event' => fn ($q) => $q->withTrashed(),
            ])
            ->find($id);
    }

    public function find(string $id): ?Order
    {
        return Order::query()->find($id);
    }

    public function lockForUpdate(string $id): ?Order
    {
        return Order::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function markPaid(Order $order): void
    {
        $order->update(['status' => OrderStatus::Paid->value]);
    }

    public function markRefunded(Order $order): void
    {
        $order->update(['status' => OrderStatus::Refunded->value]);
    }

    public function markPartiallyRefunded(Order $order): void
    {
        $order->update(['status' => OrderStatus::PartiallyRefunded->value]);
    }

    public function markPendingExpired(array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        return Order::query()
            ->whereKey($orderIds)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Expired]);
    }

    public function eligibleOrderIdsForVendorPayout(string $vendorId): array
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::PartiallyRefunded->value])
            // At least one item from a completed event owned by this vendor.
            ->whereHas('items', function ($q) use ($vendorId): void {
                $q->whereHas('ticketType', function ($q) use ($vendorId): void {
                    $q->withTrashed()->whereHas('event', function ($q) use ($vendorId): void {
                        $q->withTrashed()
                            ->where('vendor_id', $vendorId)
                            ->where('status', EventStatus::Completed->value);
                    });
                });
            })
            // Exclude orders already covered by any non-failed payout for this vendor (C-1: pending,
            // approved, processing, or paid payouts all mean the order is already committed). Only
            // `failed` payouts release the order back into the eligible pool for a retry batch.
            ->whereDoesntHave('payoutItems', function ($q) use ($vendorId): void {
                $q->whereHas('payout', fn ($p) => $p
                    ->where('vendor_id', $vendorId)
                    ->whereNotIn('status', [PayoutStatus::Failed->value]));
            })
            ->pluck('id')
            ->all();
    }

    public function eligibleVendorIdsForPayout(): array
    {
        // Fast pre-filter: vendor_ids with at least one paid/partially_refunded order on a completed event.
        // `buildForVendor` performs the exact per-vendor eligibility check (including not-yet-settled guard);
        // this query just enumerates which vendors to iterate over.
        return Order::query()
            ->select('events.vendor_id')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::PartiallyRefunded->value])
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('ticket_types', 'order_items.ticket_type_id', '=', 'ticket_types.id')
            // withTrashed for soft-deleted ticket_types/events (event completed before cancellation)
            // Raw JOIN bypasses the soft-delete global scope — includes deleted events intentionally.
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->where('events.status', EventStatus::Completed->value)
            ->distinct()
            ->pluck('events.vendor_id')
            ->all();
    }

    public function paginateForAttendee(string $attendeeId, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->where('attendee_id', $attendeeId)
            ->with('holds')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function paginateAll(int $perPage, ?string $status = null): LengthAwarePaginator
    {
        $query = Order::query()->orderBy('created_at', 'desc');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }
}
