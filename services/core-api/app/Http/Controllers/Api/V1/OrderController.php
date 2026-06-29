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

/**
 * @group Orders / Checkout
 *
 * Attendee ticket checkout. Creates a 15-minute inventory hold and initiates payment.
 */
final class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    /**
     * Checkout
     *
     * Reserve tickets with a 15-minute hold and initiate payment via the payment-service.
     * The order is created with `status=pending`; it becomes `paid` when the payment webhook
     * confirms success, or `expired` if the hold expires without payment.
     *
     * **Idempotency:** include a unique `Idempotency-Key` header. Replaying the same key returns
     * the existing order without creating a second charge.
     *
     * @group Attendee
     * @subgroup Orders
     * @authenticated
     * @header Idempotency-Key string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000
     * @response 201 scenario="Order created (pending payment)" {"success":true,"message":"Order created, payment initiated.","data":{"order":{"id":"01J000000000000DEMOORDER1","status":{"value":"pending","label":"Pending"},"total":75000,"currency":"BDT","items":[{"ticket_type_id":"01J000000000000DEMOTICKET","quantity":3,"unit_price":25000}],"created_at":"2026-06-30T10:05:00Z"}},"errors":null}
     * @response 409 scenario="Tickets unavailable" {"success":false,"message":"Insufficient tickets available. Please try a smaller quantity or a different ticket type.","data":null,"errors":null}
     * @response 422 scenario="Missing idempotency key" {"success":false,"message":"Validation failed.","data":null,"errors":{"idempotency_key":["The idempotency key is required."]}}
     */
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
