<?php

namespace App\Repositories\Eloquent;

use App\Enums\HoldStatus;
use App\Models\TicketHold;
use App\Repositories\Contracts\TicketHoldRepositoryInterface;

final class TicketHoldRepository implements TicketHoldRepositoryInterface
{
    public function sumActiveQuantityForTicketType(string $ticketTypeId): int
    {
        return (int) TicketHold::query()
            ->where('ticket_type_id', $ticketTypeId)
            ->where('status', HoldStatus::Active)
            ->where('expires_at', '>', now())
            ->sum('quantity');
    }

    public function create(array $attributes): TicketHold
    {
        return TicketHold::create($attributes);
    }

    public function releaseDueActiveHolds(): array
    {
        // Snapshot the affected order ids, then release exactly those holds. Re-running finds nothing
        // (already released), so the operation is idempotent.
        $due = TicketHold::query()
            ->where('status', HoldStatus::Active)
            ->where('expires_at', '<=', now())
            ->get(['id', 'order_id']);

        if ($due->isEmpty()) {
            return [];
        }

        TicketHold::query()
            ->whereKey($due->pluck('id')->all())
            ->update(['status' => HoldStatus::Released]);

        return $due->pluck('order_id')->unique()->values()->all();
    }
}
