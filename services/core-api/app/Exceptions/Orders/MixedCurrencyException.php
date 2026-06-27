<?php

namespace App\Exceptions\Orders;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a single cart mixes ticket types of different currencies. One order = one currency.
 * Maps to HTTP 422.
 */
final class MixedCurrencyException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.orders.mixed_currency'));
    }
}
