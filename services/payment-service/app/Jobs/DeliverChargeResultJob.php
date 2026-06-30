<?php

namespace App\Jobs;

use App\Helpers\LogHelper;
use App\Models\Payment;
use App\Services\Payments\ChargeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves a pending charge and reports the terminal result back to core-api via the signed webhook
 * (CLAUDE.md §E; ADR-10). Queued so the inbound HTTP request is never blocked on the gateway's
 * processing delay.
 *
 * Order of operations is deliberate — **the result is persisted first** (ChargeService::resolve runs
 * idempotently inside its own transaction), *then* the callback is attempted. So if core-api is
 * unreachable the charge result is never lost: the job retries with backoff, and because resolve()
 * short-circuits an already-terminal payment, a retry re-sends the webhook without re-charging.
 *
 * The body is signed `X-Signature: hmac_sha256(raw_body, webhook_secret)`; core-api recomputes the
 * HMAC over the raw bytes and rejects on mismatch (replay/tamper-safe). The bearer token is a
 * SEPARATE secret from the HMAC key, so an intercepted `Authorization` header cannot be used to
 * forge a signature. The same `Log-Trace-ID` is forwarded so one charge is traceable end-to-end
 * across both services. core-api's receiver lands in Chunk D — until then a failed delivery is
 * logged gracefully, not leaked.
 *
 * `ShouldBeUnique` (keyed by payment) collapses duplicate dispatches — a replay that arrives while
 * the charge is still pending, or an at-least-once redelivery — so we don't queue redundant work.
 * Correctness never depends on it: ChargeService::resolve() is itself idempotent under the row lock.
 */
class DeliverChargeResultJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Bounded retries; the charge result is already persisted, so giving up never loses money. */
    public int $tries = 5;

    /** Hold the uniqueness lock long enough to cover the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $paymentId,
    ) {}

    /** One in-flight delivery per payment (queue-level dedupe). */
    public function uniqueId(): string
    {
        return $this->paymentId;
    }

    /** Exponential backoff (seconds): 4 gaps across 5 attempts. */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(ChargeService $charges): void
    {
        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:3] DeliverChargeResultJob — picked up from queue, resolving charge', [
            'payment_id' => $this->paymentId,
        ]);

        // Persist the outcome first (idempotent) — a retry re-enters here as a no-op resolve.
        $payment = $charges->resolve($this->paymentId);

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:3] DeliverChargeResultJob — charge resolved by gateway', [
            'payment_id' => $this->paymentId,
            'status' => $payment->status->value,
            'gateway_ref' => $payment->gateway_ref,
        ]);

        $payload = $this->payload($payment);
        $body = (string) json_encode($payload);

        // Distinct secrets: HMAC key signs the body, bearer token authenticates the caller.
        $signature = hash_hmac('sha256', $body, (string) config('services.core_api.webhook_secret'));
        $bearer = (string) config('services.core_api.bearer_token');

        $callbackUrl = (string) config('services.core_api.callback_url');

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:3] DeliverChargeResultJob — posting signed webhook to core-api', [
            'callback_url' => $callbackUrl,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
            'bearer_set' => $bearer !== '',
            'secret_set' => config('services.core_api.webhook_secret') !== '',
        ]);

        // Send the exact signed bytes; ->throw() turns a non-2xx into a retryable failure.
        $response = Http::withBody($body, 'application/json')
            ->withToken($bearer)
            ->withHeaders([
                'X-Signature' => $signature,
                ...LogHelper::traceHeaders(),
            ])
            ->post($callbackUrl)
            ->throw();

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:3] DeliverChargeResultJob — webhook delivered successfully', [
            'payment_id' => $this->paymentId,
            'http_status' => $response->status(),
        ]);
    }

    /**
     * The webhook contract (system-architecture.md §API Contracts). Carries only the order/payment
     * references, the terminal status, and the amount — never any card data.
     *
     * @return array<string, mixed>
     */
    private function payload(Payment $payment): array
    {
        return [
            'event' => 'payment.'.$payment->status->value,
            'payment_ref' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => [
                'value' => $payment->status->value,
                'label' => $payment->status->label(),
            ],
            'amount' => (int) $payment->amount,
            'currency' => $payment->currency,
            // When the charge actually resolved — not delivery time, so a retried webhook keeps the
            // true event timestamp instead of drifting forward on each attempt.
            'occurred_at' => $payment->updated_at?->toIso8601String(),
        ];
    }

    /** Last-resort handler: the result is already persisted; log without leaking the secret/body. */
    public function failed(?Throwable $e): void
    {
        LogHelper::logEntry(LogHelper::LOG_ERROR, 'Charge webhook delivery exhausted retries', [
            'payment_id' => $this->paymentId,
            'reason' => $e?->getMessage(),
        ]);
    }
}
