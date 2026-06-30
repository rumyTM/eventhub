<?php

namespace App\Repositories\Eloquent;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;

final class TicketRepository implements TicketRepositoryInterface
{
    public function create(array $attributes): Ticket
    {
        return Ticket::create($attributes);
    }

    public function voidValidForOrder(string $orderId): int
    {
        // Only `valid` tickets are voided — a `checked_in` ticket was used and stays as-is. The status
        // guard in the UPDATE makes a replay a no-op (mirrors the hold-expiry sweep guard, ADR-28).
        return Ticket::query()
            ->where('order_id', $orderId)
            ->where('status', TicketStatus::Valid->value)
            ->update(['status' => TicketStatus::Refunded->value]);
    }

    public function hasCheckedInForOrderItems(array $orderItemIds): bool
    {
        if ($orderItemIds === []) {
            return false;
        }

        return Ticket::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->where('status', TicketStatus::CheckedIn->value)
            ->exists();
    }
}
