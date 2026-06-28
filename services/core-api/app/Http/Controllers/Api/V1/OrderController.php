<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\InitiateChargeJob;
use App\Services\Orders\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    /** Checkout: reserve a 15-minute hold and create a pending order. */
    public function store(CheckoutRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        // The route guarantees an attendee-role user; guard the (rare) inconsistent state where the
        // profile row is missing so it surfaces as a clean 422, not a 500 (H-3).
        $attendee = $request->user()->attendee;
        if ($attendee === null) {
            return ApiResponse::error(message: __('api.orders.attendee_profile_required'), status: 422);
        }

        $order = $this->checkout->checkout(
            attendee: $attendee,
            idempotencyKey: $request->validated('idempotency_key'),
            items: $request->validated('items'),
        );

        // Kick off the charge off the request path. Guarded by `pending` so a replayed checkout that
        // returns an already-resolved order doesn't re-charge; the job is itself idempotent (ADR-09).
        // afterCommit() so the job is only enqueued once the checkout transaction has durably committed
        // (no charge job for an order that was rolled back).
        if ($order->status === OrderStatus::Pending) {
            InitiateChargeJob::dispatch($order->id)->afterCommit();
        }

        return ApiResponse::success(
            data: ['order' => new OrderResource($order)],
            message: __('api.orders.created'),
            status: 201,
        );
    }
}
