<?php

namespace App\Enums;

/**
 * Lifecycle of a refund this service executes on core-api's behalf. A refund is created `Pending` and
 * resolves to exactly one terminal state (`Completed`/`Failed`) when the gateway reports back. The
 * vocabulary (`completed`, not `succeeded`) mirrors core-api's RefundStatus and the webhook contract in
 * system-architecture.md §3.5, so the terminal value maps straight onto core-api's refund row.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
