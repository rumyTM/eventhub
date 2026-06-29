<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use App\Models\Payout;
use App\Services\Payments\PayoutService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves a pending payout and reports the terminal result back to core-api via the signed webhook
 * (CLAUDE.md §E; ADR-10) — the exact mirror of DeliverRefundResultJob.
 *
 * Order of operations is deliberate — **the result is persisted first** (PayoutService::resolve runs
 * idempotently inside its own transaction), *then* the callback is attempted. So if core-api is
 * unreachable the payout result is never lost: the job retries with backoff, and because resolve()
 * short-circuits an already-terminal payout, a retry re-sends the webhook WITHOUT re-executing.
 *
 * The body is signed `X-Signature: hmac_sha256(raw_body, webhook_secret)`; core-api recomputes the
 * HMAC over the raw bytes and rejects on mismatch (replay/tamper-safe). The bearer token is a SEPARATE
 * secret from the HMAC key. The same `Log-Trace-ID` is forwarded so a payout is traceable end-to-end.
 *
 * `ShouldBeUnique` (keyed by payout) collapses duplicate dispatches; correctness never depends on it —
 * PayoutService::resolve() is itself idempotent under the row lock.
 */
class DeliverPayoutResultJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Bounded retries; the payout result is already persisted, so giving up never loses money. */
    public int $tries = 5;

    /** Hold the uniqueness lock long enough to cover the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $payoutId,
    ) {}

    /** One in-flight delivery per payout (queue-level dedupe). */
    public function uniqueId(): string
    {
        return $this->payoutId;
    }

    /** Exponential backoff (seconds): 4 gaps across 5 attempts. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(PayoutService $payouts): void
    {
        // Persist the outcome first (idempotent) — a retry re-enters here as a no-op resolve.
        $payout = $payouts->resolve($this->payoutId);

        $payload = $this->payload($payout);
        $body = (string) json_encode($payload);

        // Distinct secrets: HMAC key signs the body, bearer token authenticates the caller.
        $signature = hash_hmac('sha256', $body, (string) config('services.core_api.webhook_secret'));
        $bearer = (string) config('services.core_api.bearer_token');

        // Payout-specific callback endpoint — a fixed, trusted config value, never from request (SSRF guard).
        $callbackUrl = (string) config('services.core_api.payout_callback_url');

        // Send the exact signed bytes; ->throw() turns a non-2xx into a retryable failure.
        Http::withBody($body, 'application/json')
            ->withToken($bearer)
            ->withHeaders([
                'X-Signature' => $signature,
                ...LogHelper::traceHeaders(),
            ])
            ->post($callbackUrl)
            ->throw();
    }

    /**
     * The payout webhook contract. Carries only the payout/vendor references, the terminal status,
     * and the amount — never any card data or PII beyond what core-api originally sent.
     *
     * @return array<string, mixed>
     */
    private function payload(Payout $payout): array
    {
        return [
            'event' => 'payout.'.$payout->status->value,
            'payout_ref' => $payout->payout_ref,  // core-api's Payout ID — the correlation key
            'vendor_id' => $payout->vendor_id,
            'status' => [
                'value' => $payout->status->value,
                'label' => $payout->status->label(),
            ],
            'amount' => (int) $payout->amount,
            'currency' => $payout->currency,
            // When the payout actually resolved — not delivery time, so a retried webhook keeps the
            // true event timestamp instead of drifting forward on each attempt.
            'occurred_at' => $payout->updated_at?->toIso8601String(),
        ];
    }

    /** Last-resort handler: the result is already persisted; log without leaking the secret/body. */
    public function failed(?Throwable $e): void
    {
        LogHelper::logEntry(LogHelper::LOG_ERROR, 'Payout webhook delivery exhausted retries', [
            'payout_id' => $this->payoutId,
            'reason' => $e?->getMessage(),
        ]);
    }
}
