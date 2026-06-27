<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Claimed = 'claimed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Offered => 'Offered',
            self::Claimed => 'Claimed',
            self::Expired => 'Expired',
        };
    }
}
