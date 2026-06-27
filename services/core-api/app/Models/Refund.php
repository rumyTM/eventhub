<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'payment_id',
        'amount',
        'policy_applied',
        'status',
        'reason',
    ];

    /**
     * `amount` is integer minor units, auto-derived as policy% x selected line totals (never user-set).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** A dispute that this refund resolved (nullable inverse). */
    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }
}
