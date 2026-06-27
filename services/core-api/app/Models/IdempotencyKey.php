<?php

namespace App\Models;

use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * DB-backed idempotency guard for money operations. Stores key -> result so a duplicate request returns
 * the original payload without re-running the side effect (ADR-09).
 */
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'request_hash',
        'response_payload',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
        ];
    }
}
