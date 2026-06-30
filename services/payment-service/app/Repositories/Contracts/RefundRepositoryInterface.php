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

    /**
     * Sum of all non-failed (pending + completed) refund amounts (minor units) for a given payment.
     * Used as a cumulative cap guard inside createRefund() to prevent total refunds exceeding the charge.
     */
    public function sumNonFailedForPayment(string $paymentId): int;
}
