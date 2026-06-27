<?php

namespace App\Enums;

enum TicketKind: string
{
    case EarlyBird = 'early_bird';
    case Vip = 'vip';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::EarlyBird => 'Early bird',
            self::Vip => 'VIP',
            self::General => 'General admission',
        };
    }
}
