<?php

namespace App\Models;

use Database\Factories\PayoutItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    /** @use HasFactory<PayoutItemFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'payout_id',
        'order_id',
        'settled_amount',
    ];

    /**
     * `settled_amount` is integer minor units — the exact amount this payout settled for the order.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settled_amount' => 'integer',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
