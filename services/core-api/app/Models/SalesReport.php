<?php

namespace App\Models;

use Database\Factories\SalesReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily derived read-model aggregating ledger_entries per (report_date, vendor_id); null vendor_id =
 * platform-wide. Idempotent via updateOrCreate; can be recomputed from the ledger at any time.
 */
class SalesReport extends Model
{
    /** @use HasFactory<SalesReportFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'report_date',
        'vendor_id',
        'tickets_sold',
        'gross',
        'commission',
        'net',
        'currency',
    ];

    /**
     * All money columns are integer minor units.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'tickets_sold' => 'integer',
            'gross' => 'integer',
            'commission' => 'integer',
            'net' => 'integer',
        ];
    }

    /** Null vendor means a platform-wide rollup. */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
