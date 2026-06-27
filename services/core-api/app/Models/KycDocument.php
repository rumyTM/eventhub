<?php

namespace App\Models;

use App\Enums\KycStatus;
use Database\Factories\KycDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KycDocument extends Model
{
    /** @use HasFactory<KycDocumentFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'vendor_id',
        'type',
        'storage_path',
        'status',
        'uploaded_at',
    ];

    /**
     * `storage_path` is encrypted; documents are served only via short-lived signed URLs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KycStatus::class,
            'storage_path' => 'encrypted',
            'uploaded_at' => 'datetime',
        ];
    }

    /** @var list<string> */
    protected $hidden = [
        'storage_path',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
