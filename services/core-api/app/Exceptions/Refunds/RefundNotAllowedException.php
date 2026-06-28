<?php

namespace App\Exceptions\Refunds;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an order cannot be refunded at all (wrong state, no succeeded charge of record, or a
 * selected line that does not belong to the order). Maps to HTTP 422. The specific reason is in the
 * human message, never in `errors` (reserved for field-level validation).
 */
final class RefundNotAllowedException extends HttpException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(statusCode: 422, message: $message ?? __('api.refunds.not_allowed'));
    }
}
