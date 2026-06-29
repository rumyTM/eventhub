<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RefundReason;
use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Refunds\InitiateRefundRequest;
use App\Http\Requests\Refunds\RequestRefundRequest;
use App\Http\Resources\RefundResource;
use App\Jobs\ExecuteRefundJob;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Refunds\RefundService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Refunds
 *
 * Request and manage order refunds. Amount is always **policy-derived** — attendees never specify
 * a sum. Time-based policy: >48 h before event start → 100%; 24–48 h → 50%; <24 h → 0%.
 */
final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
    ) {}

    /**
     * Request refund (attendee)
     *
     * Request a refund for a paid order (or a subset of its tickets). The refund amount is
     * auto-derived from the time-based cancellation policy — the attendee does not specify an amount.
     * In-policy requests are auto-approved and executed immediately. Out-of-policy requests open a
     * dispute for admin mediation.
     *
     * @group Attendee
     * @subgroup Refunds
     * @authenticated
     * @response 202 scenario="Refund accepted" {"success":true,"message":"Refund requested and queued for processing.","data":{"refund":{"id":"01J000000000000DEMOREFUND","order_id":"01J000000000000DEMOORDER1","amount":75000,"currency":"BDT","policy_applied":"100","status":{"value":"pending","label":"Pending"},"reason":{"value":"attendee_requested","label":"Attendee Requested"},"created_at":"2026-06-30T10:10:00Z"}},"errors":null}
     * @response 422 scenario="Order not refundable" {"success":false,"message":"Validation failed.","data":null,"errors":{"order":["This order is not eligible for a refund."]}}
     */
    public function store(RequestRefundRequest $request, Order $order): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('refund', $order);

        $refund = $this->refunds->request(
            order: $order,
            reason: RefundReason::AttendeeRequested,
            items: $request->validated('items'),
        );

        return $this->respond($refund);
    }

    /**
     * Initiate refund (admin)
     *
     * Admin-initiated refund — e.g. when an event is cancelled. The reason can be
     * `attendee_requested` or `event_cancelled`. Amount is policy-derived (cancellations are 100%).
     * Idempotent: replaying the same order returns the existing open refund.
     *
     * @group Admin
     * @subgroup Refunds
     * @authenticated
     * @response 202 scenario="Refund initiated" {"success":true,"message":"Refund requested and queued for processing.","data":{"refund":{"id":"01J000000000000DEMOREFUND","order_id":"01J000000000000DEMOORDER1","amount":75000,"currency":"BDT","policy_applied":"100","status":{"value":"pending","label":"Pending"},"reason":{"value":"event_cancelled","label":"Event Cancelled"},"created_at":"2026-06-30T10:10:00Z"}},"errors":null}
     */
    public function initiate(InitiateRefundRequest $request, Order $order): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('initiateRefund', $order);

        $refund = $this->refunds->request(
            order: $order,
            reason: $request->refundReason(),
            items: $request->validated('items'),
        );

        return $this->respond($refund);
    }

    /**
     * Shared response: dispatch the execution job ONLY for a freshly-created refund (a duplicate request
     * returns the existing open refund with `wasRecentlyCreated` false, so it never re-fires). afterCommit
     * so the job is enqueued only once the refund row has durably committed. Execution is Chunk C.
     */
    private function respond(Refund $refund): JsonResponse
    {
        if ($refund->wasRecentlyCreated) {
            ExecuteRefundJob::dispatch($refund->id)->afterCommit();
        }

        return ApiResponse::success(
            data: ['refund' => new RefundResource($refund)],
            message: __('api.refunds.requested'),
            status: 202,
        );
    }
}
