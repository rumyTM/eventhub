<?php

namespace App\Repositories\Contracts;

use App\Enums\PayoutStatus;
use App\Models\Payout;

interface PayoutRepositoryInterface
{
    public function create(array $attributes): Payout;

    public function findOrFail(string $id): Payout;

    /** Lock the row FOR UPDATE inside an active transaction. */
    public function findForUpdate(string $id): Payout;

    public function markResolved(Payout $payout, PayoutStatus $status, string $gatewayRef): Payout;
}
