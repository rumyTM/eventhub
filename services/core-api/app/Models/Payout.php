<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'vendor_id',
        'gross',
        'commission',
        'net',
        'payable',
        'reserved_refund',
        'currency',
        'status',
        'batch_id',
        'idempotency_key',
    ];

    /**
     * All money columns are integer minor units. `net = gross − commission`; `payable = net + adjustments`
     * (floored at 0) — the amount actually disbursed to the vendor. `idempotency_key` + `batch_id` guard
     * against double-pay.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'gross' => 'integer',
            'commission' => 'integer',
            'net' => 'integer',
            'payable' => 'integer',
            'reserved_refund' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
