<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Valid = 'valid';
    case CheckedIn = 'checked_in';
    case Transferred = 'transferred';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::CheckedIn => 'Checked in',
            self::Transferred => 'Transferred',
            self::Refunded => 'Refunded',
        };
    }
}
