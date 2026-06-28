<?php

namespace App\Services\Payments;

use App\Contracts\PaymentServiceContract;
use App\Helpers\LogHelper;
use App\Support\Payments\ChargeResult;
use Illuminate\Support\Facades\Http;

/**
 * HTTP implementation of {@see PaymentServiceContract} (CLAUDE.md §H). POSTs to the payment-service
 * over REST carrying:
 *   - `Authorization: Bearer ${PAYMENT_SERVICE_TOKEN}` — the inter-service shared secret;
 *   - `Idempotency-Key` — deterministic per charge attempt, so a retried call de-dupes at the
 *     gateway (ADR-09) and never double-charges;
 *   - the trace header — so one charge is traceable end-to-end across both services.
 *
 * Base URL + token come from config/env (never hard-coded). A non-2xx response or a timeout becomes
 * a thrown exception (`->throw()`), which the queued InitiateChargeJob treats as retryable — the
 * order stays `pending`, never silently paid.
 */
final class PaymentClient implements PaymentServiceContract
{
    public function createCharge(
        string $orderId,
        string $gateway,
        int $amount,
        string $currency,
        string $idempotencyKey,
    ): ChargeResult {
        $response = Http::asJson()
            ->withToken((string) config('services.payment.service_token'))
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
                ...LogHelper::traceHeaders(),
            ])
            ->connectTimeout(5)
            ->timeout(10)
            ->post($this->endpoint('/api/v1/payments'), [
                'order_id' => $orderId,
                'gateway' => $gateway,
                'amount' => $amount,     // integer minor units — never float, never card data
                'currency' => $currency,
            ])
            ->throw(); // 4xx/5xx → RequestException; timeout → ConnectionException (both retried)

        return new ChargeResult(
            ref: $response->json('data.payment.ref'),
            status: (string) $response->json('data.payment.status.value', 'pending'),
        );
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.payment.base_url'), '/').$path;
    }
}
