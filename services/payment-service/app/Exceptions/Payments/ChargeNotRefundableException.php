<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a refund is requested against a charge that is not `succeeded` — a `pending` charge may
 * still fail, and a `failed` charge never captured money, so there is nothing to give back. A local
 * sanity invariant (not policy): this service refunds only money it actually captured. Maps to HTTP 422.
 */
final class ChargeNotRefundableException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.refunds.charge_not_refundable'));
    }
}
