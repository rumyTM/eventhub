<?php

namespace App\Repositories\Eloquent;

use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use App\Repositories\Contracts\WaitlistRepositoryInterface;

final class WaitlistRepository implements WaitlistRepositoryInterface
{
    public function ticketTypeIdsWithWaiting(): array
    {
        return WaitlistEntry::query()
            ->where('status', WaitlistStatus::Waiting)
            ->distinct()
            ->pluck('ticket_type_id')
            ->all();
    }

    public function nextWaitingForTicketType(string $ticketTypeId): ?WaitlistEntry
    {
        return WaitlistEntry::query()
            ->where('ticket_type_id', $ticketTypeId)
            ->where('status', WaitlistStatus::Waiting)
            ->orderBy('position')
            ->first();
    }

    public function markOffered(WaitlistEntry $entry): WaitlistEntry
    {
        $now = now();
        $entry->update([
            'status' => WaitlistStatus::Offered,
            'offered_at' => $now,
            'claim_expires_at' => $now->copy()->addMinutes(30),
        ]);

        return $entry->refresh();
    }

    public function expireUnclaimed(): int
    {
        return WaitlistEntry::query()
            ->where('status', WaitlistStatus::Offered)
            ->where('claim_expires_at', '<=', now())
            ->update(['status' => WaitlistStatus::Expired]);
    }
}
