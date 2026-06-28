<?php

namespace App\Repositories\Eloquent;

use App\Models\IdempotencyKey;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;

final class IdempotencyKeyRepository implements IdempotencyKeyRepositoryInterface
{
    public function findByKey(string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()->where('key', $key)->first();
    }

    public function create(array $attributes): IdempotencyKey
    {
        return IdempotencyKey::create($attributes);
    }
}
