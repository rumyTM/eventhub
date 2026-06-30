<?php

namespace App\Repositories\Eloquent;

use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Repositories\Contracts\DisputeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DisputeRepository implements DisputeRepositoryInterface
{
    public function create(array $attributes): Dispute
    {
        return Dispute::create($attributes);
    }

    public function findOpenForOrder(string $orderId): ?Dispute
    {
        return Dispute::query()
            ->where('order_id', $orderId)
            ->where('status', DisputeStatus::Open->value)
            ->latest()
            ->first();
    }

    public function hasRejectedForOrder(string $orderId): bool
    {
        return Dispute::query()
            ->where('order_id', $orderId)
            ->where('status', DisputeStatus::Rejected->value)
            ->exists();
    }

    public function listOpen(int $perPage = 15): LengthAwarePaginator
    {
        return Dispute::query()
            ->with([
                'order.attendee.user',
                'order.items.ticketType' => fn ($q) => $q->withTrashed(),
                'order.items.ticketType.event' => fn ($q) => $q->withTrashed(),
            ])
            ->where('status', DisputeStatus::Open->value)
            ->latest()
            ->paginate($perPage);
    }

    public function update(Dispute $dispute, array $attributes): Dispute
    {
        $dispute->fill($attributes)->save();

        return $dispute;
    }
}
