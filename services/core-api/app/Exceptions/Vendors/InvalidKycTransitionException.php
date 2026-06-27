<?php

namespace App\Exceptions\Vendors;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a KYC status change is not legal — e.g. re-deciding a terminal status (verifying an
 * already-verified vendor) or submitting a profile that is already verified. Maps to HTTP 409 Conflict.
 */
final class InvalidKycTransitionException extends HttpException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(statusCode: 409, message: $message ?? __('api.vendors.invalid_kyc_transition'));
    }
}
