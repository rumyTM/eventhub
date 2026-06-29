<?php

namespace App\Repositories\Contracts;

use App\Models\Refund;

interface RefundRepositoryInterface
{
    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Refund;

    /** Find a refund by id, or null (used by the async execution job). */
    public function find(string $id): ?Refund;

    /** Row-lock a refund (SELECT … FOR UPDATE) for the execution flip; null if it no longer exists. */
    public function findForUpdate(string $id): ?Refund;

    /** The OPEN refund (requested|pending) for an order, row-locked for the webhook resolution; null if none. */
    public function lockOpenForOrder(string $orderId): ?Refund;

    /** Mark a refund `pending` (execution in flight). Caller holds the row lock + asserted it was requested. */
    public function markPending(Refund $refund): void;

    /** Mark a refund `completed` (the gateway confirmed). Caller holds the lock + asserted it was open. */
    public function markCompleted(Refund $refund): void;

    /** Mark a refund `failed` (no money moved; no ledger). Caller holds the lock + asserted it was non-terminal. */
    public function markFailed(Refund $refund): void;

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

    /**
     * Sum of COMPLETED-only refund amounts (minor units) — the unconditionally correct figure for
     * the full-vs-partial threshold and order-status/ticket-void decision (M-2: completed-only
     * is correct regardless of whether the one-open invariant holds).
     */
    public function completedRefundedTotalForOrder(string $orderId): int;
}
