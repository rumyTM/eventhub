<?php

namespace App\Repositories\Contracts;

use App\Enums\RefundStatus;
use App\Models\Refund;

interface RefundRepositoryInterface
{
    public function create(array $attributes): Refund;

    public function findOrFail(string $id): Refund;

    /** Row-lock a refund for the resolution transaction (SELECT … FOR UPDATE). */
    public function findForUpdate(string $id): Refund;

    /** Persist the terminal outcome (status + clearly-fake gateway reference). */
    public function markResolved(Refund $refund, RefundStatus $status, string $gatewayRef): Refund;
}
