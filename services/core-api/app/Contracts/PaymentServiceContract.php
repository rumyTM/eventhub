<?php

namespace App\Contracts;

use App\Support\Payments\ChargeResult;
use App\Support\Payments\PayoutResult;
use App\Support\Payments\RefundResult;

/**
 * core-api's view of the payment-service (CLAUDE.md §H). The concrete impl uses the Laravel HTTP
 * client and carries the shared-secret bearer + a per-operation `Idempotency-Key` + the trace header
 * on every call; tests bind a fake. core-api NEVER sees card data — it passes order/charge references
 * and an amount in integer minor units, and the gateway holds the instrument.
 *
 * `executePayout` joins in the payout slice.
 */
interface PaymentServiceContract
{
    /**
     * Start a charge for an order. Returns immediately with a `pending` result — the terminal
     * success/failure arrives later via the signed webhook. Throws on a transport/5xx failure so the
     * caller (a queued job) can retry; the order is never moved to paid here.
     */
    public function createCharge(
        string $orderId,
        string $gateway,
        int $amount,
        string $currency,
        string $idempotencyKey,
    ): ChargeResult;

    /**
     * Execute a refund against an original charge. `$paymentRef` is the payment-service's charge
     * reference (core-api's `payments.external_ref`). Returns immediately with a `pending` result —
     * the terminal completed/failed arrives later via the signed refund webhook. The `Idempotency-Key`
     * is deterministic per refund so a retried call de-dupes at the payment-service (ADR-09). Throws on
     * a transport/5xx failure so the caller (a queued job) can retry; the refund is never completed here.
     */
    public function refund(
        string $paymentRef,
        int $amount,
        string $currency,
        ?string $reason,
        string $idempotencyKey,
    ): RefundResult;

    /**
     * Execute a payout to a vendor via the payment-service. `$payoutId` is the core-api Payout ULID
     * (used as `payout_ref` in payment-service for correlation). Returns immediately with a `pending`
     * result — the terminal completed/failed arrives via the signed payout webhook. The `Idempotency-Key`
     * is deterministic per payout (`payout-exec:{payoutId}`) so a retried call de-dupes at the
     * payment-service (ADR-09) and never double-pays. Throws on a transport/5xx failure so the caller
     * (a queued job) can retry; the payout is never marked paid here.
     */
    public function executePayout(
        string $payoutId,
        string $vendorId,
        int $amount,
        string $currency,
        string $idempotencyKey,
    ): PayoutResult;
}
