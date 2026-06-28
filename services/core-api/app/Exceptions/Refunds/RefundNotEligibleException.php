<?php

namespace App\Exceptions\Refunds;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when the refund policy denies the request: either the attendee asked inside the <24h 0% window
 * (out of policy — the contest → dispute path is a later slice, ADR-11), or the charge is already fully
 * refunded so nothing remains. Maps to HTTP 422.
 */
final class RefundNotEligibleException extends HttpException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(statusCode: 422, message: $message ?? __('api.refunds.not_eligible_window'));
    }
}
