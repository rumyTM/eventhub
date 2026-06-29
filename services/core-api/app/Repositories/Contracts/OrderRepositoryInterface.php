<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\OrderItem;

interface OrderRepositoryInterface
{
    public function create(array $attributes): Order;

    public function addItem(Order $order, array $attributes): OrderItem;

    /** Load an order with its items and holds (for the response / replay). */
    public function findWithLines(string $id): Order;

    /**
     * Load an order with the relations the refund flow needs: its items and each item's (possibly
     * soft-deleted) ticket type + event, so the refund window can be measured against the event start
     * even for historical/cancelled events. Null if the order no longer exists.
     */
    public function findForRefund(string $id): ?Order;

    /** Find an order by id, or null if it no longer exists (used by the async charge job). */
    public function find(string $id): ?Order;

    /** Row-lock an order (SELECT … FOR UPDATE) for the webhook settlement; null if it no longer exists. */
    public function lockForUpdate(string $id): ?Order;

    /** Mark an order paid. Caller holds the row lock + has asserted it was pending. */
    public function markPaid(Order $order): void;

    /** Mark an order fully `refunded` (cumulative refunds reached its total). Caller holds the row lock. */
    public function markRefunded(Order $order): void;

    /** Mark an order `partially_refunded` (a subset/partial refund). Caller holds the row lock. */
    public function markPartiallyRefunded(Order $order): void;

    /**
     * Mark the given orders `expired` only if still `pending`. Returns the number updated.
     * Never touches paid/refunded/cancelled orders.
     *
     * @param  list<string>  $orderIds
     */
    public function markPendingExpired(array $orderIds): int;

    /**
     * IDs of orders eligible for a vendor payout (ADR-20):
     *   - status = `paid` OR `partially_refunded`
     *   - at least one item from a completed event owned by this vendor
     *   - no `payout_item` pointing to a `paid` payout for this vendor (not yet settled)
     *
     * ticket_types and events are queried withTrashed so a cancelled/soft-deleted event whose
     * status was set to `completed` before deletion is still settleable.
     *
     * @return list<string>
     */
    public function eligibleOrderIdsForVendorPayout(string $vendorId): array;

    /**
     * Distinct vendor IDs that have at least one order eligible for payout. Used by `buildAll`
     * to enumerate vendors without loading every order into memory.
     *
     * @return list<string>
     */
    public function eligibleVendorIdsForPayout(): array;
}
