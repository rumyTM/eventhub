<?php

namespace App\Exceptions\Events;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an event's capacity is lowered below the sum of its ticket types' quantity_total.
 * Maps to HTTP 422. Checked under the event row lock so it can't race a concurrent ticket-type change.
 */
final class CapacityBelowAllocatedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.events.capacity_below_allocated'));
    }
}
