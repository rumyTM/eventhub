<?php

namespace App\Enums;

/**
 * Lifecycle of a charge attempt. A charge is created `Pending` and resolves to exactly one terminal
 * state (`Succeeded`/`Failed`) when the gateway reports back. Mirrors core-api's PaymentStatus.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
