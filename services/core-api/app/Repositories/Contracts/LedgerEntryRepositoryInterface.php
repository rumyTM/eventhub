<?php

namespace App\Repositories\Contracts;

use App\Models\LedgerEntry;

interface LedgerEntryRepositoryInterface
{
    /**
     * Append one signed entry to the financial ledger (never updated/deleted — ADR-13).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LedgerEntry;
}
