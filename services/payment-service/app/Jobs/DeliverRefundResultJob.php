<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use App\Models\Refund;
use App\Services\Payments\RefundService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves a pending refund and reports the terminal result back to core-api via the signed webhook
 * (CLAUDE.md §E; ADR-10) — the exact mirror of DeliverChargeResultJob.
 *
 * Order of operations is deliberate — **the result is persisted first** (RefundService::resolve runs
 * idempotently inside its own transaction), *then* the callback is attempted. So if core-api is
 * unreachable the refund result is never lost: the job retries with backoff, and because resolve()
 * short-circuits an already-terminal refund, a retry re-sends the webhook WITHOUT re-refunding.
 *
 * The body is signed `X-Signature: hmac_sha256(raw_body, webhook_secret)`; core-api recomputes the HMAC
 * over the raw bytes and rejects on mismatch (replay/tamper-safe). The bearer token is a SEPARATE secret
 * from the HMAC key, so an intercepted `Authorization` header cannot forge a signature. The same
 * `Log-Trace-ID` is forwarded so a refund is traceable end-to-end across both services.
 *
 * `ShouldBeUnique` (keyed by refund) collapses duplicate dispatches; correctness never depends on it —
 * RefundService::resolve() is itself idempotent under the row lock.
 */
class DeliverRefundResultJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Bounded retries; the refund result is already persisted, so giving up never loses money. */
    public int $tries = 5;

    /** Hold the uniqueness lock long enough to cover the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $refundId,
    ) {}

    /** One in-flight delivery per refund (queue-level dedupe). */
    public function uniqueId(): string
    {
        return $this->refundId;
    }

    /** Exponential backoff (seconds): 4 gaps across 5 attempts. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(RefundService $refunds): void
    {
        // Persist the outcome first (idempotent) — a retry re-enters here as a no-op resolve.
        $refund = $refunds->resolve($this->refundId);

        $payload = $this->payload($refund);
        $body = (string) json_encode($payload);

        // Distinct secrets: HMAC key signs the body, bearer token authenticates the caller.
        $signature = hash_hmac('sha256', $body, (string) config('services.core_api.webhook_secret'));
        $bearer = (string) config('services.core_api.bearer_token');

        // Refund-specific callback endpoint — a fixed, trusted config value, never from the request (SSRF guard).
        $callbackUrl = (string) config('services.core_api.refund_callback_url');

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
     * The refund webhook contract (system-architecture.md §3.5). Carries only the order/payment/refund
     * references, the terminal status, and the amount — never any card data.
     *
     * @return array<string, mixed>
     */
    private function payload(Refund $refund): array
    {
        return [
            'event' => 'refund.'.$refund->status->value,
            'refund_ref' => $refund->id,
            'payment_ref' => $refund->payment_id,   // the original charge
            'order_id' => $refund->order_id,
            'status' => [
                'value' => $refund->status->value,
                'label' => $refund->status->label(),
            ],
            'amount' => (int) $refund->amount,
            'currency' => $refund->currency,
            // When the refund actually resolved — not delivery time, so a retried webhook keeps the true
            // event timestamp instead of drifting forward on each attempt.
            'occurred_at' => $refund->updated_at?->toIso8601String(),
        ];
    }

    /** Last-resort handler: the result is already persisted; log without leaking the secret/body. */
    public function failed(?Throwable $e): void
    {
        LogHelper::logEntry(LogHelper::LOG_ERROR, 'Refund webhook delivery exhausted retries', [
            'refund_id' => $this->refundId,
            'reason' => $e?->getMessage(),
        ]);
    }
}
