<?php

namespace App\Repositories\Contracts;

use App\Models\Payout;
use App\Models\PayoutItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayoutRepositoryInterface
{
    /**
     * Whether a vendor's revenue for this order has ALREADY been paid out — i.e. there is a
     * `payout_item` linking the order to a `paid` payout for that vendor. Drives the refund ledger
     * choice (ADR-20): not-yet-paid → ordinary refund reversal of unsettled revenue; already-paid →
     * a `clawback` to recover disbursed funds (the rare fallback).
     */
    public function orderSettledPaidForVendor(string $orderId, string $vendorId): bool;

    /**
     * Find an existing payout by its deterministic idempotency key (no lock). Used as a fast-path
     * guard before entering the locked transaction in the batch builder.
     */
    public function findByIdempotencyKey(string $key): ?Payout;

    /**
     * Row-lock (SELECT … FOR UPDATE) a payout by idempotency key. Inside the batch builder's
     * transaction; prevents concurrent workers from double-building the same vendor/batch payout.
     */
    public function lockByIdempotencyKey(string $key): ?Payout;

    /**
     * Persist a new payout row. The unique `idempotency_key` constraint is the DB-level double-pay
     * guard; callers must catch a `UniqueConstraintViolationException` and treat it as a replay.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createPayout(array $attributes): Payout;

    /**
     * Persist one payout_item row (order settled in a payout).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createPayoutItem(array $attributes): PayoutItem;

    /**
     * Paginated list of payouts, newest first. Optionally filtered by status and/or vendor_id.
     *
     * @return LengthAwarePaginator<Payout>
     */
    public function list(?string $status, ?string $vendorId, int $perPage = 20): LengthAwarePaginator;
}
