<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Services\Payments\ProcessPaymentWebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly ProcessPaymentWebhookService $processor,
    ) {}

    /**
     * Receive the payment-service charge result (signature already verified by middleware) and apply
     * it idempotently. Always 200 on a well-formed, authentic payload — including a replay, which is a
     * deliberate no-op — so the payment-service stops retrying delivery.
     */
    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->processor->handle($request->validated());

        return ApiResponse::success(message: __('api.payments.webhook_processed'));
    }
}
