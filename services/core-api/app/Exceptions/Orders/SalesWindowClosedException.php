<?php

namespace App\Exceptions\Orders;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a ticket type's sales window (sales_start..sales_end) is not currently open. Maps to 422.
 */
final class SalesWindowClosedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.orders.sales_window_closed'));
    }
}
