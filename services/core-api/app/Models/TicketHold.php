<?php

namespace App\Models;

use App\Enums\HoldStatus;
use Database\Factories\TicketHoldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHold extends Model
{
    /** @use HasFactory<TicketHoldFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'ticket_type_id',
        'quantity',
        'status',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HoldStatus::class,
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Active = consuming inventory: status active AND not yet expired. Expiry is enforced at read time,
     * so a hold stops consuming stock the moment it expires regardless of the cron's cadence.
     *
     * @param  Builder<TicketHold>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', HoldStatus::Active)
            ->where('expires_at', '>', now());
    }
}
