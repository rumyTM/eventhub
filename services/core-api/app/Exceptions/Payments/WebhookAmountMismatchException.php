<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a payment webhook's amount/currency does not match the order it claims to settle. A
 * mismatch means the callback cannot be trusted to mark the order paid (a tampered or misrouted
 * result), so we reject it (HTTP 422) and mutate nothing. Maps via bootstrap/app.php.
 */
final class WebhookAmountMismatchException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.payments.webhook_amount_mismatch'));
    }
}
