<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Thrown during webhook settlement when an order item can no longer be attributed to a vendor — its
 * `ticket_type` or `event` was soft-deleted between checkout and settlement, so `vendor_id` resolves to
 * null. Issuing tickets / moving `quantity_sold` while silently dropping that vendor's sale/commission
 * ledger rows would move money with no ledger record, breaking the audit invariant this slice protects.
 *
 * It is a server-state integrity violation (not a client error), so it deliberately bubbles to the
 * global handler as a generic 500: thrown INSIDE the settlement transaction, it rolls the whole thing
 * back (nothing issued, no ledger, no `quantity_sold`, order stays `pending`), and is logged loudly for
 * manual reconciliation/refund. The gateway's authoritative success still lives in the payment-service;
 * a delivery retry re-aborts idempotently until the data issue is resolved or the hold expires.
 */
final class OrderSettlementIntegrityException extends RuntimeException
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $orderItemId,
    ) {
        parent::__construct(
            "Cannot settle order {$orderId}: order item {$orderItemId} has no resolvable vendor "
            .'(ticket type or event missing — likely soft-deleted between checkout and settlement).'
        );
    }
}
