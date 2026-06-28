<?php

namespace App\Models;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A charge attempt processed on core-api's behalf. `amount` is integer minor units (poisha); never
 * a float. `gateway_ref` is a clearly-fake simulated reference only — never card data.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'gateway',
        'status',
        'amount',
        'currency',
        'gateway_ref',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => Gateway::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
        ];
    }
}
