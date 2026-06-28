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

        // Re-assert status=active in the UPDATE itself: between the snapshot above and here a concurrent
        // payment webhook (order locked FOR UPDATE) may have flipped some of these holds to `converted`.
        // Filtering by key alone would clobber that committed conversion back to `released` and corrupt
        // the hold lifecycle. The status guard makes the write a no-op for any hold already converted.
        TicketHold::query()
            ->whereKey($due->pluck('id')->all())
            ->where('status', HoldStatus::Active)
            ->update(['status' => HoldStatus::Released]);

        return $due->pluck('order_id')->unique()->values()->all();
    }

    public function convertActiveForOrder(string $orderId): int
    {
        // Only NON-EXPIRED holds convert. Expiry is enforced at read time (CLAUDE.md §F.2), so a hold
        // past expires_at has already freed its seats for other buyers even before ReleaseExpiredHolds
        // sweeps it. Converting it here would issue tickets against inventory the system treats as
        // available — an oversell. The count tells the caller whether the reservation still held.
        return TicketHold::query()
            ->where('order_id', $orderId)
            ->where('status', HoldStatus::Active)
            ->where('expires_at', '>', now())
            ->update(['status' => HoldStatus::Converted]);
    }
}
