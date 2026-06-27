<?php

namespace App\Exceptions\Orders;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a checkout requests more of a ticket type than is currently available
 * (quantity_total - quantity_sold - active holds). Maps to HTTP 409. The shortfall detail goes in the
 * human message, never in `errors` (which is reserved for field-level validation).
 */
final class TicketsUnavailableException extends HttpException
{
    public function __construct(int $requested, int $available)
    {
        parent::__construct(
            statusCode: 409,
            message: __('api.orders.tickets_unavailable', ['requested' => $requested, 'available' => $available]),
        );
    }
}
