<?php

namespace App\Repositories\Contracts;

use App\Models\Dispute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DisputeRepositoryInterface
{
    public function create(array $attributes): Dispute;

    public function findOpenForOrder(string $orderId): ?Dispute;

    /** Paginated list of open disputes with their orders eager-loaded (admin queue). */
    public function listOpen(int $perPage = 15): LengthAwarePaginator;

    public function update(Dispute $dispute, array $attributes): Dispute;
}
