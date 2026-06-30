<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use App\Services\Payments\ChargeOrderService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Initiates the charge for a pending order against the payment-service, off the request path
 * (CLAUDE.md §F.3, §H). Dispatched once the checkout has committed a `pending` order.
 *
 * Retryable with backoff: a payment-service outage (5xx/timeout) makes `charge()` throw, which fails
 * the attempt and schedules a backed-off retry — the order **stays pending**, never silently paid.
 * If payment never completes, the 15-minute hold-expiry job is the safety net. Idempotent by design:
 * the deterministic per-attempt `Idempotency-Key` means a retry reuses the same `payments` row and
 * de-dupes at the gateway (ADR-09). `ShouldBeUnique` keeps one in-flight job per (order, attempt).
 */
class InitiateChargeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Bounded retries; the order stays pending until the webhook resolves it, so this never loses money. */
    public int $tries = 5;

    /** Hold the uniqueness lock across the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $orderId,
        public readonly int $attempt = 1,
    ) {}

    /** One in-flight charge job per (order, attempt). */
    public function uniqueId(): string
    {
        return "{$this->orderId}:{$this->attempt}";
    }

    /** Exponential backoff (seconds): 4 gaps across 5 attempts. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(ChargeOrderService $charges): void
    {
        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:1] InitiateChargeJob — dispatched from queue, starting charge', [
            'order_id' => $this->orderId,
            'attempt' => $this->attempt,
        ]);

        try {
            $charges->charge($this->orderId, $this->attempt);

            LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:1] InitiateChargeJob — charge initiated, payment-service accepted', [
                'order_id' => $this->orderId,
            ]);
        } catch (RequestException $e) {
            // A 4xx is a permanent client-side failure (bad request, auth, or key conflict) — retrying
            // the identical call cannot succeed, so stop now rather than burn the retry budget. The
            // order stays pending and the 15-min hold-expiry job reclaims inventory; never marked paid.
            if ($e->response->clientError()) {
                LogHelper::logEntry(LogHelper::LOG_ERROR, 'Charge rejected by payment-service (non-retryable); order stays pending', [
                    'order_id' => $this->orderId,
                    'attempt' => $this->attempt,
                    'status' => $e->response->status(),
                ]);
                $this->fail($e);

                return;
            }

            throw $e; // 5xx — transient; let the queue retry with backoff
        }
    }

    /** Result is never lost: the order stays pending and the hold-expiry job reclaims inventory. */
    public function failed(?Throwable $e): void
    {
        LogHelper::logEntry(LogHelper::LOG_ERROR, 'Charge initiation exhausted retries; order stays pending', [
            'order_id' => $this->orderId,
            'attempt' => $this->attempt,
            'reason' => $e?->getMessage(),
        ]);
    }
}
