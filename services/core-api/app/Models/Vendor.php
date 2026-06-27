<?php

namespace App\Models;

use App\Enums\KycStatus;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'business_name',
        'legal_name',
        'trade_license_no',
        'tin_bin',
        'representative_nid',
        'contact_phone',
        'address',
        'kyc_status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'payout_account',
        'webhook_url',
        'webhook_secret',
        'commission_rate',
    ];

    /**
     * Sensitive identifiers are encrypted at rest (application-level cast) — the DB stores ciphertext.
     * Never log or return these raw; redaction + signed-URL rules apply (see CLAUDE.md §J, ERD PII section).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kyc_status' => KycStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'tin_bin' => 'encrypted',
            'representative_nid' => 'encrypted',
            'payout_account' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'commission_rate' => 'decimal:4',
        ];
    }

    /** @var list<string> */
    protected $hidden = [
        'tin_bin',
        'representative_nid',
        'payout_account',
        'webhook_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin user who reviewed this vendor's KYC (distinct from the vendor's own user). */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(SalesReport::class);
    }

    /**
     * Vendor balance is DERIVED from the append-only ledger, never stored — SUM(signed amount).
     * Can go negative via a refund-after-payout clawback (ADR-20).
     */
    public function balance(): int
    {
        return (int) $this->ledgerEntries()->sum('amount');
    }
}
