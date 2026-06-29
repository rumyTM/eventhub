<?php

namespace App\Repositories\Eloquent;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PayoutRepository implements PayoutRepositoryInterface
{
    public function orderSettledPaidForVendor(string $orderId, string $vendorId): bool
    {
        return PayoutItem::query()
            ->where('order_id', $orderId)
            ->whereHas('payout', fn ($q) => $q
                ->where('vendor_id', $vendorId)
                ->where('status', PayoutStatus::Paid->value))
            ->exists();
    }

    public function findByIdempotencyKey(string $key): ?Payout
    {
        return Payout::query()->where('idempotency_key', $key)->first();
    }

    public function lockByIdempotencyKey(string $key): ?Payout
    {
        return Payout::query()->where('idempotency_key', $key)->lockForUpdate()->first();
    }

    public function createPayout(array $attributes): Payout
    {
        return Payout::create($attributes);
    }

    public function createPayoutItem(array $attributes): PayoutItem
    {
        return PayoutItem::create($attributes);
    }

    public function list(?string $status, ?string $vendorId, int $perPage = 20): LengthAwarePaginator
    {
        return Payout::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId))
            ->latest()
            ->paginate($perPage);
    }
}
