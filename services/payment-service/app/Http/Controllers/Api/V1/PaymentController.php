<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\Payments\ChargeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly ChargeService $charges,
    ) {}

    /**
     * Create a charge for an order. Reserves a `pending` Payment idempotently and returns 201
     * immediately; the gateway outcome is resolved asynchronously and reported to core-api via the
     * signed webhook (the real success/failure never travels in this response — see ADR-10).
     */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $payment = $this->charges->createCharge(
            idempotencyKey: $request->validated('idempotency_key'),
            payload: $request->safe()->only(['order_id', 'gateway', 'amount', 'currency']),
        );

        // Queue the async resolution + signed webhook (no-op on an already-resolved replay).
        $this->charges->scheduleResolution($payment);

        return ApiResponse::success(
            data: ['payment' => new PaymentResource($payment)],
            message: __('api.payments.created'),
            status: 201,
        );
    }
}
