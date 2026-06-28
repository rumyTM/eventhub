<?php

namespace App\Repositories\Eloquent;

use App\Models\LedgerEntry;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;

final class LedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    public function create(array $attributes): LedgerEntry
    {
        return LedgerEntry::create($attributes);
    }
}
