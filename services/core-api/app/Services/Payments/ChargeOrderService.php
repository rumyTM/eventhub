<?php

namespace App\Services\Payments;

use App\Contracts\PaymentServiceContract;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;

/**
 * Orchestrates initiating a charge for a pending order against the payment-service (CLAUDE.md §F.3,
 * §H). Runs from the queued InitiateChargeJob, so it is written to be safe to re-run:
 *
 *   - **No-op unless pending.** If the order has already moved on (paid via webhook, expired by the
 *     hold safety net, failed/cancelled), there is nothing to charge — return without a call.
 *   - **One payment row per attempt.** The `Idempotency-Key` is deterministic per (order, attempt),
 *     and the `payments` row is `firstOrCreate`d on that key (unique), so a job retry reuses the same
 *     row and the payment-service de-dupes the same key — never a second charge (ADR-09/17).
 *   - **Failure leaves the order pending.** If the call throws (5xx/timeout), the exception bubbles
 *     to the job for a backed-off retry; the order is never marked paid here. The webhook is the only
 *     thing that advances it, and the hold-expiry job is the safety net if payment never completes.
 *
 * Gateway selection at checkout is a later concern; for now the configured default gateway is used.
 */
final class ChargeOrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentServiceContract $paymentService,
    ) {}

    public function charge(string $orderId, int $attempt = 1): void
    {
        $order = $this->orders->find($orderId);

        // Order vanished or already resolved — idempotent no-op (never re-charge a settled order).
        if ($order === null || $order->status !== OrderStatus::Pending) {
            LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:2] ChargeOrderService — order not pending, skipping charge', [
                'order_id' => $orderId,
                'status' => $order?->status->value ?? 'not_found',
            ]);

            return;
        }

        $idempotencyKey = $this->idempotencyKey($order->id, $attempt);

        $payment = $this->payments->firstOrCreateForAttempt($idempotencyKey, [
            'order_id' => $order->id,
            'gateway' => (string) config('services.payment.default_gateway'),
            'status' => PaymentStatus::Pending->value,
            'amount' => $order->total,      // integer minor units — never card data
            'currency' => $order->currency,
        ]);

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:2] ChargeOrderService — calling payment-service', [
            'order_id' => $orderId,
            'payment_row_id' => $payment->id,
            'gateway' => $payment->gateway,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'idempotency_key' => $payment->idempotency_key,
            'payment_service_url' => config('services.payment.base_url'),
        ]);

        $result = $this->paymentService->createCharge(
            orderId: $order->id,
            gateway: $payment->gateway,
            amount: $payment->amount,
            currency: $payment->currency,
            idempotencyKey: $payment->idempotency_key,
        );

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:2] ChargeOrderService — payment-service responded', [
            'order_id' => $orderId,
            'payment_ref' => $result->ref,
            'status' => $result->status,
        ]);

        if ($result->ref !== null) {
            $this->payments->recordExternalRef($payment, $result->ref);
        }
    }

    /** Deterministic per (order, attempt): a retry reuses the same key so the gateway de-dupes. */
    private function idempotencyKey(string $orderId, int $attempt): string
    {
        return "charge:{$orderId}:attempt:{$attempt}";
    }
}
