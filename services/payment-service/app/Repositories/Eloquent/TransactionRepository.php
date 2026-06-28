<?php

namespace App\Repositories\Eloquent;

use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;

final class TransactionRepository implements TransactionRepositoryInterface
{
    public function create(array $attributes): Transaction
    {
        return Transaction::create($attributes);
    }
}
