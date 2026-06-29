<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only ledger row recording a resolved gateway operation (CLAUDE.md §G). History is never
 * rewritten — there is no `updated_at` (`UPDATED_AT = null`), so Eloquent manages only `created_at`
 * and a status change appends a new row instead of mutating this one.
 *
 * `amount` is SIGNED integer minor units (poisha): a charge is positive money-in; a failed charge
 * moves nothing and is recorded as 0. `gateway_ref` is a clearly-fake simulated reference only —
 * never card data.
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUlids;

    /** Append-only: manage `created_at`, never an `updated_at`. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'payment_id',  // null for payout-type rows (no associated charge)
        'payout_id',   // set for payout-type rows only
        'type',
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
            'type' => TransactionType::class,
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
