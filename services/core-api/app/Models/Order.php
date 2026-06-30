<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'attendee_id',
        'status',
        'total',
        'currency',
        'commission_rate',
        'idempotency_key',
    ];

    /**
     * `total` is integer minor units. `commission_rate` is the rate snapshot fixed at sale time so
     * historical payout math stays reproducible.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'integer',
            'commission_rate' => 'decimal:4',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(TicketHold::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function latestOpenRefund(): HasOneThrough
    {
        return $this->hasOneThrough(
            Refund::class,
            Payment::class,
            'order_id',   // FK on payments → orders
            'payment_id', // FK on refunds → payments
            'id',
            'id',
        )->whereIn('refunds.status', [RefundStatus::Requested->value, RefundStatus::Pending->value]);
    }

    public function latestRefund(): HasOneThrough
    {
        return $this->hasOneThrough(
            Refund::class,
            Payment::class,
            'order_id',
            'payment_id',
            'id',
            'id',
        )->latest('refunds.created_at');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
