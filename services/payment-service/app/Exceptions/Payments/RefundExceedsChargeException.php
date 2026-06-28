<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a single refund request exceeds the original charge amount. This is a local sanity
 * invariant — not a policy decision (core-api owns the 100/50/0% policy and the cumulative-refund
 * validation against its ledger). A processor must never refund more than was charged on one charge,
 * so we reject it loudly rather than execute an impossible refund. Maps to HTTP 422.
 */
final class RefundExceedsChargeException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.refunds.exceeds_charge'));
    }
}
