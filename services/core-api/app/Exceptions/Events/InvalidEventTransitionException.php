<?php

namespace App\Exceptions\Events;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an event status change is not a legal lifecycle transition
 * (draft → published → ongoing → completed; cancelled is terminal). Maps to HTTP 409 Conflict —
 * the request conflicts with the resource's current state. Never a silent no-op.
 */
final class InvalidEventTransitionException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 409, message: __('api.events.invalid_transition'));
    }
}
