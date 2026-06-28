<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreateRefundRequest;
use App\Http\Resources\RefundResource;
use App\Services\Payments\RefundService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
    ) {}

    /**
     * Execute a refund for an original charge. Reserves a `pending` Refund idempotently and returns 201
     * immediately; the gateway outcome is resolved asynchronously and reported to core-api via the signed
     * webhook (the real completed/failed never travels in this response — mirrors the charge path, ADR-10).
     */
    public function store(CreateRefundRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $refund = $this->refunds->createRefund(
            idempotencyKey: $request->validated('idempotency_key'),
            payload: $request->safe()->only(['payment_ref', 'amount', 'currency', 'reason']),
        );

        // Queue the async resolution + signed webhook (no-op on an already-resolved replay).
        $this->refunds->scheduleResolution($refund);

        return ApiResponse::success(
            data: ['refund' => new RefundResource($refund)],
            message: __('api.refunds.created'),
            status: 201,
        );
    }
}
