<?php

namespace App\Repositories\Contracts;

use App\Models\IdempotencyKey;

interface IdempotencyKeyRepositoryInterface
{
    public function findByKey(string $key): ?IdempotencyKey;

    public function create(array $attributes): IdempotencyKey;
}
