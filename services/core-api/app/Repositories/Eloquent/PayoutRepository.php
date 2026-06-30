<?php

namespace App\Repositories\Eloquent;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class PayoutRepository implements PayoutRepositoryInterface
{
    public function orderSettledPaidForVendor(string $orderId, string $vendorId): bool
    {
        // lockForUpdate() participates in the refund webhook's DB::transaction, serialising the
        // clawback-vs-refund classification read against any concurrent payout webhook that would
        // flip status → paid in the same window (H-2 reviewer fix).
        return PayoutItem::query()
            ->where('order_id', $orderId)
            ->whereHas('payout', fn ($q) => $q
                ->where('vendor_id', $vendorId)
                ->where('status', PayoutStatus::Paid->value))
            ->lockForUpdate()
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
            ->with('vendor:id,business_name')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId))
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?Payout
    {
        return Payout::query()->find($id);
    }

    public function findForUpdate(string $id): ?Payout
    {
        return Payout::query()->lockForUpdate()->find($id);
    }

    public function markProcessing(Payout $payout): Payout
    {
        $payout->forceFill(['status' => PayoutStatus::Processing->value])->save();

        return $payout;
    }

    public function markPaid(Payout $payout): Payout
    {
        // paid_at is write-once: set here and never touched again (used as a stable clawback window
        // anchor instead of updated_at, which a processing→processing retry would push forward).
        $payout->forceFill([
            'status' => PayoutStatus::Paid->value,
            'paid_at' => now(),
        ])->save();

        return $payout;
    }

    public function markFailed(Payout $payout): Payout
    {
        $payout->forceFill(['status' => PayoutStatus::Failed->value])->save();

        return $payout;
    }

    public function markItemsSettled(string $payoutId): void
    {
        // Set settled_at on each item so per-item settlement is queryable independently of the payout status.
        PayoutItem::query()
            ->where('payout_id', $payoutId)
            ->whereNull('settled_at')
            ->update(['settled_at' => Carbon::now()]);
    }
}
