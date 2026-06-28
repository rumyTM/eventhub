<?php

namespace App\Enums;

/**
 * The kind of money operation a `transactions` ledger row records. Only `Charge` is written today
 * (Chunk B); `Refund` and `Payout` join when those flows land, so the ledger speaks one vocabulary
 * across every gateway operation. The row's signed `amount` carries direction — never this enum.
 */
enum TransactionType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
    case Payout = 'payout';

    public function label(): string
    {
        return match ($this) {
            self::Charge => 'Charge',
            self::Refund => 'Refund',
            self::Payout => 'Payout',
        };
    }
}
