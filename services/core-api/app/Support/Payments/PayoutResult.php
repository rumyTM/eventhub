<?php

namespace App\Support\Payments;

/**
 * Value object returned by PaymentServiceContract::executePayout(). Carries only the pending
 * acknowledgement from payment-service — the real terminal result arrives via the signed webhook.
 * No card data ever appears here.
 */
final readonly class PayoutResult
{
    public function __construct(
        public string $ref,
        public string $status,
    ) {}
}
