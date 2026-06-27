<?php

namespace App\Exceptions\Events;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a vendor whose KYC is not `verified` tries to publish an event. Maps to HTTP 422 —
 * the action is blocked by an unmet business precondition (CLAUDE.md §F: only verified vendors publish).
 */
final class VendorNotVerifiedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.events.not_verified_vendor'));
    }
}
