<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a refund webhook's amount/currency does not match the open refund it claims to resolve. A
 * mismatch means the callback cannot be trusted to complete the refund (a tampered or misrouted result),
 * so we reject it (HTTP 422) and mutate nothing — no ledger, no status change. Maps via bootstrap/app.php.
 */
final class RefundWebhookMismatchException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.refunds.webhook_amount_mismatch'));
    }
}
