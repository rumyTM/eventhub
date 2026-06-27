<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, polymorphic financial ledger — the single source of truth for money. Rows are NEVER
 * updated or deleted; corrections are new offsetting entries. `amount` is a SIGNED integer (minor units).
 *
 * `subject_type`/`subject_id` reference the order/payment/refund/payout that caused the entry. They use
 * domain-name strings (order|payment|refund|payout), not class names, so a morphTo relationship should be
 * wired via an enforced morph map before use (deferred to the financial-logic task).
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory, HasUlids;

    /** Append-only: created_at is set on insert, there is no updated_at column. */
    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'vendor_id',
        'subject_type',
        'subject_id',
        'entry_type',
        'amount',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
