<?php

namespace App\Exceptions\TicketTypes;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when creating/updating a ticket type would push SUM(quantity_total) over the event's capacity.
 * Maps to HTTP 422. Computed inside a transaction under an event row lock so concurrent edits can't bypass it.
 */
final class EventCapacityExceededException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.ticket_types.capacity_exceeded'));
    }
}
