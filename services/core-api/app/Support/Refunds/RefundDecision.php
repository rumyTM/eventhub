<?php

namespace App\Support\Refunds;

use App\Enums\RefundReason;

/**
 * The immutable outcome of {@see RefundPolicy::decide()}. Carries the policy band applied, the
 * auto-derived refundable amount (integer minor units — the attendee never names an amount), the reason
 * category, and — when ineligible — a machine code the service maps to a human message.
 *
 * `denialReason` codes: `out_of_window` (attendee request inside the <24h 0% band) and `already_refunded`
 * (the charge has already been fully refunded, so nothing remains).
 */
final readonly class RefundDecision
{
    public function __construct(
        public bool $eligible,
        public string $policyApplied,   // '100' | '50' | '0'
        public int $amountMinor,        // auto-derived, integer minor units (poisha)
        public RefundReason $reason,
        public ?string $denialReason = null,
    ) {}

    public static function eligible(string $policyApplied, int $amountMinor, RefundReason $reason): self
    {
        return new self(true, $policyApplied, $amountMinor, $reason);
    }

    public static function ineligible(string $policyApplied, RefundReason $reason, string $denialReason): self
    {
        return new self(false, $policyApplied, 0, $reason, $denialReason);
    }
}
