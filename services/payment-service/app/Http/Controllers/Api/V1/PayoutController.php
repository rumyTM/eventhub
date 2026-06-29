<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreatePayoutRequest;
use App\Http\Resources\PayoutResource;
use App\Services\Payments\PayoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payouts,
    ) {}

    /**
     * Execute a payout for a vendor. Reserves a `pending` Payout idempotently and returns 201
     * immediately; the gateway outcome is resolved asynchronously and reported to core-api via the
     * signed webhook (the real completed/failed never travels in this response — mirrors charge/refund,
     * ADR-10). No card data is ever processed here.
     */
    public function store(CreatePayoutRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $payout = $this->payouts->createPayout(
            idempotencyKey: $request->validated('idempotency_key'),
            payload: $request->safe()->only(['payout_ref', 'vendor_id', 'amount', 'currency']),
        );

        // Queue the async resolution + signed webhook (no-op on an already-resolved replay).
        $this->payouts->scheduleResolution($payout);

        return ApiResponse::success(
            data: ['payout' => new PayoutResource($payout)],
            message: __('api.payouts.created'),
            status: 201,
        );
    }
}
