<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'refund_id',
        'reason',
        'status',
        'resolved_by',
        'resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The refund that resolved this dispute, if any (nullable). */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /** The admin user who resolved this dispute. */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
