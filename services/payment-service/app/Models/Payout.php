<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'payout_ref',
        'vendor_id',
        'amount',
        'currency',
        'status',
        'gateway_ref',
        'idempotency_key',
    ];

    /**
     * `amount` is the positive disbursable amount in integer minor units. The signed movement
     * (negative on success) lives in the append-only `transactions` ledger — never here.
     * No card field ever appears on this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'integer',
        ];
    }
}
