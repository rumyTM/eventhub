<?php

namespace App\Repositories\Contracts;

use App\Models\Refund;

interface RefundRepositoryInterface
{
    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Refund;

    /** Find a refund by id, or null (used by the async execution job). */
    public function find(string $id): ?Refund;

    /**
     * The single OPEN refund (status requested|pending) for an order, or null. Resolved through the
     * order's payments — a refund belongs to a payment, a payment to the order. This is the idempotency
     * guard: one open refund per order, so a duplicate request returns the existing one.
     */
    public function findOpenForOrder(string $orderId): ?Refund;

    /**
     * Sum of all NON-failed refund amounts (minor units) raised against an order's payments — the
     * cumulative figure the policy caps against so total refunds never exceed the charge.
     */
    public function refundedTotalForOrder(string $orderId): int;
}
