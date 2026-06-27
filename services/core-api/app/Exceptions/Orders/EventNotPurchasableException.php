<?php

namespace App\Exceptions\Orders;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a ticket type's event is not purchasable (only published/ongoing events sell). Maps to 422.
 */
final class EventNotPurchasableException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.orders.event_not_purchasable'));
    }
}
