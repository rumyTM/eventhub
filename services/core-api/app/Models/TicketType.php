<?php

namespace App\Models;

use App\Enums\TicketKind;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'kind',
        'price',
        'currency',
        'quantity_total',
        'quantity_sold',
        'group_size',
        'group_discount',
        'sales_start',
        'sales_end',
    ];

    /**
     * `price` is integer minor units (poisha). `quantity_sold` is a denormalized counter incremented
     * transactionally on payment success only.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TicketKind::class,
            'price' => 'integer',
            'quantity_total' => 'integer',
            'quantity_sold' => 'integer',
            'group_size' => 'integer',
            'group_discount' => 'decimal:4',
            'sales_start' => 'datetime',
            'sales_end' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(TicketHold::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }
}
