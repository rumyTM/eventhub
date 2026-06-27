<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Vendor = 'vendor';
    case Attendee = 'attendee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Vendor => 'Vendor',
            self::Attendee => 'Attendee',
        };
    }
}
