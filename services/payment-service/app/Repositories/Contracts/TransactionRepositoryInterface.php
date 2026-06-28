<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;

interface TransactionRepositoryInterface
{
    public function create(array $attributes): Transaction;
}
