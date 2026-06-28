<?php

namespace App\Models;

use App\Enums\Gateway;
use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refund executed on core-api's behalf against an original charge. `amount` is the POSITIVE refund
 * amount in integer minor units (poisha); never a float. `gateway_ref` is a clearly-fake simulated
 * reference only — never card data. The signed (negative) money movement is recorded in `transactions`.
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'payment_id',
        'order_id',
        'gateway',
        'status',
        'amount',
        'currency',
        'gateway_ref',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => Gateway::class,
            'status' => RefundStatus::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
