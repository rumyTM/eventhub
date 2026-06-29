<?php

namespace App\Exceptions\Payments;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a payout webhook's amount does not match the payout's payable amount. A mismatch means
 * the callback cannot be trusted (tampered or mis-routed), so we reject it (HTTP 422) and mutate
 * nothing — no ledger, no status change. Maps via bootstrap/app.php → HttpExceptionInterface → 422.
 */
final class PayoutWebhookMismatchException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 422, message: __('api.payouts.webhook_amount_mismatch'));
    }
}
