<?php

namespace App\Exceptions\TicketTypes;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an update would set quantity_total below the tickets already sold (quantity_sold).
 * Maps to HTTP 422 — you can never sell more inventory than exists.
 */
final class QuantityBelowSoldException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.ticket_types.quantity_below_sold'));
    }
}
