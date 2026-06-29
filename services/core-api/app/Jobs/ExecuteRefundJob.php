<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use App\Services\Refunds\RefundExecutionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Executes an approved refund against the payment-service, off the request path (CLAUDE.md §F/§H).
 * Dispatched ONLY for a newly-created, policy-approved refund (see RefundController) — never for a
 * duplicate/ineligible request. Mirrors {@see InitiateChargeJob} for the charge path.
 *
 * The work lives in {@see RefundExecutionService}: flip `requested` → `pending`, then POST to the
 * payment-service with a deterministic per-refund Idempotency-Key. The refund is NEVER marked
 * `completed` here — that arrives via the signed refund webhook. Retryable with backoff; idempotent by
 * the per-refund key, so a retry never double-refunds. `ShouldBeUnique` keeps one in-flight job per refund.
 */
class ExecuteRefundJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Bounded retries; the refund stays pending until the webhook resolves it, so this never loses money. */
    public int $tries = 5;

    /** Hold the uniqueness lock across the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    /** Set when handle() permanently fails the refund via 4xx; prevents failed() from calling markExecutionFailed twice. */
    private bool $permanentlyFailed = false;

    public function __construct(
        public readonly string $refundId,
    ) {}

    /** One in-flight execution per refund. */
    public function uniqueId(): string
    {
        return $this->refundId;
    }

    /** Exponential backoff (seconds): 4 gaps across 5 attempts. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(RefundExecutionService $refunds): void
    {
        try {
            $refunds->execute($this->refundId);
        } catch (RequestException $e) {
            // A 4xx is a permanent client-side rejection (bad request, unrefundable charge, key conflict)
            // — retrying the identical call cannot succeed, and no webhook will arrive, so resolve the
            // refund as failed now (no money moved, no ledger) and stop. A 5xx/timeout is transient: let
            // the queue retry with backoff; the refund stays pending and is never double-executed.
            if ($e->response->clientError()) {
                LogHelper::logEntry(LogHelper::LOG_ERROR, 'Refund rejected by payment-service (non-retryable); marking failed', [
                    'refund_id' => $this->refundId,
                    'status' => $e->response->status(),
                ]);
                $this->permanentlyFailed = true;
                $refunds->markExecutionFailed($this->refundId);
                $this->fail($e);

                return;
            }

            throw $e; // 5xx/timeout — transient; let the queue retry with backoff
        }
    }

    /**
     * All retries exhausted (5xx/timeout loop). The deterministic idempotency key guarantees the
     * payment-service never durably created the refund — a retry would replay a 2xx — so it is safe
     * to mark the refund failed here, releasing the one-open guard on the order.
     */
    public function failed(?Throwable $e): void
    {
        LogHelper::logEntry(LogHelper::LOG_ERROR, 'Refund execution exhausted retries', [
            'refund_id' => $this->refundId,
            'reason' => $e?->getMessage(),
        ]);

        // Skip if handle() already marked it failed via 4xx fast-fail (permanentlyFailed=true) to
        // avoid the double-call path; mirrors the pattern in ExecutePayoutJob (H-1 reviewer fix).
        if (! $this->permanentlyFailed) {
            app(RefundExecutionService::class)->markExecutionFailed($this->refundId);
        }
    }
}
