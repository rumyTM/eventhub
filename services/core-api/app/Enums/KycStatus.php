<?php

namespace App\Enums;

enum KycStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /** Only verified vendors may publish events or receive payouts. */
    public function canTransact(): bool
    {
        return $this === self::Verified;
    }
}
