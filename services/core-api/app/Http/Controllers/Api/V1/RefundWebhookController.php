<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RefundWebhookRequest;
use App\Services\Payments\ProcessRefundWebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RefundWebhookController extends Controller
{
    public function __construct(
        private readonly ProcessRefundWebhookService $processor,
    ) {}

    /**
     * Receive the payment-service refund result (signature already verified by middleware) and apply it
     * idempotently. Always 200 on a well-formed, authentic payload — including a replay, which is a
     * deliberate no-op — so the payment-service stops retrying delivery.
     */
    public function handle(RefundWebhookRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->processor->handle($request->validated());

        return ApiResponse::success(message: __('api.refunds.webhook_processed'));
    }
}
