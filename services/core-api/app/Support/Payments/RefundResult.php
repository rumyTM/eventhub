<?php

namespace App\Support\Payments;

/**
 * Immutable result of a refund call to the payment-service. Carries only the gateway-side refund
 * reference and the (pending) status it reported — never card data. The real terminal outcome
 * (completed/failed) arrives asynchronously via the signed refund webhook.
 */
final readonly class RefundResult
{
    public function __construct(
        public ?string $ref,
        public string $status,
    ) {}
}
