<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CheckoutRequest;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\InitiateChargeJob;
use App\Models\Order;
use App\Services\Orders\CheckoutService;
use App\Services\Orders\OrderService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Orders / Checkout
 *
 * Attendee ticket checkout. Creates a 15-minute inventory hold and initiates payment.
 */
final class OrderController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly OrderService $orders,
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
     *
     * @subgroup Orders
     *
     * @authenticated
     *
     * @header Idempotency-Key string required A unique key (UUID recommended) to make this request idempotent. Example: 550e8400-e29b-41d4-a716-446655440000
     *
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

        return ApiResponse::success(
            data: ['order' => new OrderResource($order)],
            message: __('api.orders.created'),
            status: 201,
        );
    }

    /**
     * Pay order
     *
     * Explicitly initiates payment for a pending order. Call this after displaying the payment
     * form to the attendee — the charge job is dispatched only when the attendee submits.
     *
     * @group Attendee
     *
     * @subgroup Orders
     *
     * @authenticated
     *
     * @response 200 scenario="Payment initiated" {"success":true,"message":"Payment initiated. Your order will be confirmed shortly.","data":{"order":{"id":"01J000000000000DEMOORDER1","status":{"value":"pending","label":"Pending"}}},"errors":null}
     * @response 422 scenario="Order not payable" {"success":false,"message":"This order is not in a payable state.","data":null,"errors":null}
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('view', $order);

        if ($order->status !== OrderStatus::Pending) {
            return ApiResponse::error(message: __('api.orders.not_payable'), status: 422);
        }

        LogHelper::logEntry(LogHelper::LOG_DEBUG, '[PAYMENT-CHAIN:0] OrderController::pay — dispatching InitiateChargeJob on attendee submit', [
            'order_id' => $order->id,
            'total' => $order->total,
            'currency' => $order->currency,
        ]);

        InitiateChargeJob::dispatch($order->id);

        return ApiResponse::success(
            data: ['order' => new OrderResource($order)],
            message: __('api.orders.payment_initiated'),
        );
    }

    /**
     * List orders
     *
     * Returns a paginated list of orders. Attendees see only their own orders;
     * admins see all orders and can filter by status (e.g. for the dispute queue).
     *
     * @group Orders
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Orders retrieved.","data":{"orders":[{"id":"01J000000000000DEMOORDER1","status":{"value":"paid","label":"Paid"},"total":75000,"currency":"BDT","commission_rate":"0.1000","created_at":"2026-06-30T10:05:00Z"}],"pagination":{"current_page":1,"per_page":15,"total":1,"last_page":1}},"errors":null}
     * @response 401 scenario="Unauthenticated" {"success":false,"message":"Unauthenticated.","data":null,"errors":null}
     */
    public function index(ListOrdersRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $validated = $request->validated();
        $page = $this->orders->list(
            user: $request->user(),
            perPage: (int) ($validated['per_page'] ?? self::PER_PAGE),
            status: $validated['status'] ?? null,
        );

        return ApiResponse::success(
            data: [
                'orders' => OrderResource::collection($page->getCollection()),
                'pagination' => $this->pagination($page),
            ],
            message: __('api.orders.listed'),
        );
    }

    /**
     * Get order
     *
     * Retrieve a single order with its items and holds. Attendees can only view
     * their own orders; admins can view any order.
     *
     * @group Orders
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Order retrieved.","data":{"order":{"id":"01J000000000000DEMOORDER1","status":{"value":"paid","label":"Paid"},"total":75000,"currency":"BDT","commission_rate":"0.1000","items":[{"id":"01J000000000000DEMOITEM1","ticket_type_id":"01J000000000000DEMOTICKET","quantity":3,"unit_price":25000}],"holds":[],"hold_expires_at":null,"created_at":"2026-06-30T10:05:00Z"}},"errors":null}
     * @response 403 scenario="Unauthorized" {"success":false,"message":"This action is unauthorized.","data":null,"errors":null}
     * @response 404 scenario="Not Found" {"success":false,"message":"Resource not found.","data":null,"errors":null}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('view', $order);

        $order->load([
            'items',
            // withTrashed: a cancelled event's ticket_type/event are soft-deleted, but a historical
            // order should still show which event it was for.
            'items.ticketType' => fn ($q) => $q->withTrashed(),
            'items.ticketType.event' => fn ($q) => $q->withTrashed(),
            'attendee.user',
            'holds',
            'latestPayment',
            'latestOpenRefund',
            'latestRefund',
            'latestDispute',
            'tickets.ticketType' => fn ($q) => $q->withTrashed(),
        ]);

        return ApiResponse::success(
            data: ['order' => new OrderResource($order)],
            message: __('api.orders.retrieved'),
        );
    }

    /**
     * @return array<string, int>
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ];
    }
}
