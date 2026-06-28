<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentStatus;
use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function create(array $attributes): Payment;

    public function findOrFail(string $id): Payment;

    /** Row-lock a payment for the resolution transaction (SELECT … FOR UPDATE). */
    public function findForUpdate(string $id): Payment;

    /** Persist the terminal outcome (status + clearly-fake gateway reference). */
    public function markResolved(Payment $payment, PaymentStatus $status, string $gatewayRef): Payment;
}
