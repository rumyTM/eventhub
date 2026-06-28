<?php

namespace App\Enums;

/**
 * Refund lifecycle. A newly-requested refund that the policy approved is `requested` — the execution job
 * is queued but NO money has moved and NO reversal ledger row exists yet (that is Chunk B/C). `pending`
 * marks execution in flight at the payment-service; `completed`/`failed` are terminal.
 */
enum RefundStatus: string
{
    case Requested = 'requested';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /** An open refund is one still in flight — it blocks a second open refund for the same order. */
    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Pending;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
