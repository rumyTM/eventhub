<?php

namespace App\Support\Payments;

/**
 * Immutable result of a createCharge call to the payment-service. Carries only the gateway-side
 * payment reference and the (pending) status it reported — never card data. The real terminal
 * outcome arrives asynchronously via the webhook (Chunk D).
 */
final readonly class ChargeResult
{
    public function __construct(
        public ?string $ref,
        public string $status,
    ) {}
}
