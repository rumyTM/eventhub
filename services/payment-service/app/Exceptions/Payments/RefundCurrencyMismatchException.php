<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a refund's currency does not match the original charge's currency. Refunding in a
 * different currency than was charged would poison ledger reconciliation (SUM over mixed currencies is
 * meaningless). A local sanity invariant complementing single-currency-per-order (ADR-12). Maps to 422.
 */
final class RefundCurrencyMismatchException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.refunds.currency_mismatch'));
    }
}
