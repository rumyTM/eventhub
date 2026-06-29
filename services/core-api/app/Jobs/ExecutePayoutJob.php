<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use App\Services\Payouts\PayoutExecutionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Executes an approved payout against the payment-service, off the request path (CLAUDE.md §F/§H).
 * Dispatched when an admin triggers execution of a built payout. Mirrors {@see ExecuteRefundJob}.
 *
 * The work lives in {@see PayoutExecutionService}: flip `pending` → `processing`, then POST to the
 * payment-service with a deterministic per-payout Idempotency-Key. The payout is NEVER marked `paid`
 * here — that arrives via the signed payout webhook. Retryable with backoff; idempotent by the
 * per-payout key, so a retry never double-pays. `ShouldBeUnique` keeps one in-flight job per payout.
 */
class ExecutePayoutJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Bounded retries; the payout stays processing until the webhook resolves it, so this never loses money. */
    public int $tries = 5;

    /** Hold the uniqueness lock across the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    /** Set when handle() permanently fails the payout via 4xx; prevents failed() from calling markExecutionFailed twice. */
    private bool $permanentlyFailed = false;

    public function __construct(
        public readonly string $payoutId,
    ) {}

    /** One in-flight execution per payout. */
    public function uniqueId(): string
    {
        return $this->payoutId;
    }

    /** Exponential backoff (seconds): 4 gaps across 5 attempts. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(PayoutExecutionService $payouts): void
    {
        try {
            $payouts->execute($this->payoutId);
        } catch (RequestException $e) {
            // A 4xx is a permanent client-side rejection — retrying the identical call cannot succeed,
            // and no webhook will arrive, so resolve the payout as failed now (no money moved, no
            // ledger) and stop. A 5xx/timeout is transient: let the queue retry with backoff; the
            // payout stays processing and is never double-executed.
            if ($e->response->clientError()) {
                LogHelper::logEntry(LogHelper::LOG_ERROR, 'Payout rejected by payment-service (non-retryable); marking failed', [
                    'payout_id' => $this->payoutId,
                    'status' => $e->response->status(),
                ]);
                $this->permanentlyFailed = true;
                $payouts->markExecutionFailed($this->payoutId);
                $this->fail($e);

                return;
            }

            throw $e; // 5xx/timeout — transient; let the queue retry with backoff
        }
    }

    /**
     * All retries exhausted (5xx/timeout loop). The deterministic idempotency key guarantees the
     * payment-service never durably created the payout — a retry would replay a 2xx — so it is safe
     * to mark the payout failed here.
     */
    public function failed(?Throwable $e): void
    {
        LogHelper::logEntry(LogHelper::LOG_ERROR, 'Payout execution exhausted retries', [
            'payout_id' => $this->payoutId,
            'reason' => $e?->getMessage(),
        ]);

        // Skip if handle() already marked it failed via 4xx fast-fail (permanentlyFailed=true) to
        // avoid the double-call path documented in H-1 of the financial-logic review.
        if (! $this->permanentlyFailed) {
            app(PayoutExecutionService::class)->markExecutionFailed($this->payoutId);
        }
    }
}
