<?php

namespace App\Support;

use App\Enums\PaymentStatus;

/**
 * Immutable outcome of a gateway operation (charge/refund/payout). Carries only a clearly-fake
 * simulated reference — never card data. `succeeded` maps to a terminal PaymentStatus.
 */
final readonly class GatewayResult
{
    public function __construct(
        public bool $succeeded,
        public string $reference,
        public string $gateway,
    ) {}

    public function status(): PaymentStatus
    {
        return $this->succeeded ? PaymentStatus::Succeeded : PaymentStatus::Failed;
    }
}
