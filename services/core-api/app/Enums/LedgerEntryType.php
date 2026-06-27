<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Sale = 'sale';
    case Commission = 'commission';
    case Payout = 'payout';
    case Refund = 'refund';
    case Clawback = 'clawback';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::Commission => 'Commission',
            self::Payout => 'Payout',
            self::Refund => 'Refund',
            self::Clawback => 'Clawback',
        };
    }
}
