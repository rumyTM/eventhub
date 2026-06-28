<?php

namespace App\Contracts;

use App\Support\Payments\ChargeResult;

/**
 * core-api's view of the payment-service (CLAUDE.md §H). The concrete impl uses the Laravel HTTP
 * client and carries the shared-secret bearer + a per-attempt `Idempotency-Key` + the trace header
 * on every call; tests bind a fake. core-api NEVER sees card data — it passes order references and
 * an amount in integer minor units, and the gateway holds the instrument.
 *
 * Only `createCharge` exists for now (Chunk C); `refund` and `executePayout` join in their slices.
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
}
