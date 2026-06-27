<?php

namespace App\Services\Orders;

use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TicketHoldRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping safety net for expired holds. Flips active+due holds to `released` and marks their
 * still-`pending` orders `expired`.
 *
 * Correctness does NOT depend on this running (or on its cadence): availability already excludes expired
 * holds at read time (status=active AND expires_at > now), so inventory frees exactly at the 15-minute
 * mark for new buyers regardless. This job just tidies stale rows and lets order status reflect reality
 * (and will trigger waitlist processing in a later slice). It is idempotent and safe to re-run, and it
 * never touches converted (paid) holds or non-pending orders.
 *
 * @return array{released_orders: int, expired_orders: int}
 */
final class ReleaseExpiredHoldsService
{
    public function __construct(
        private readonly TicketHoldRepositoryInterface $holds,
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /**
     * @return array{released_orders: int, expired_orders: int}
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $orderIds = $this->holds->releaseDueActiveHolds();
            $expired = $this->orders->markPendingExpired($orderIds);

            return [
                'released_orders' => count($orderIds),
                'expired_orders' => $expired,
            ];
        });
    }
}
