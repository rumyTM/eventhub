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

    /** Verified/rejected are terminal admin decisions — they are not re-decided. */
    public function isTerminal(): bool
    {
        return $this === self::Verified || $this === self::Rejected;
    }

    /** Legal admin review transitions: a pending vendor may be verified or rejected; terminal states are final. */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Verified, self::Rejected], true),
            self::Verified, self::Rejected => false,
        };
    }
}
