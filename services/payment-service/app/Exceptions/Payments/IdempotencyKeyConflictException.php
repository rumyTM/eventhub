<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an Idempotency-Key was already used with a DIFFERENT request body. Reusing a key for a
 * different payload is a client error — we never silently process it (would risk a second charge).
 * Maps to HTTP 409 (ADR-09).
 */
final class IdempotencyKeyConflictException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 409, message: __('api.payments.idempotency_conflict'));
    }
}
