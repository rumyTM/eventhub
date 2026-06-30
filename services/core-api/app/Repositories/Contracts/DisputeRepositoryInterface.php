<?php

namespace App\Repositories\Contracts;

use App\Models\Dispute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DisputeRepositoryInterface
{
    public function create(array $attributes): Dispute;

    public function findOpenForOrder(string $orderId): ?Dispute;

    /** True when the order has a rejected (final) dispute — blocks further refund requests. */
    public function hasRejectedForOrder(string $orderId): bool;

    /** Paginated list of open disputes with their orders eager-loaded (admin queue). */
    public function listOpen(int $perPage = 15): LengthAwarePaginator;

    public function update(Dispute $dispute, array $attributes): Dispute;
}
