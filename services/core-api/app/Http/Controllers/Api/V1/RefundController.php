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

final class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
    ) {}

    /** Attendee: request a refund for their own paid order (reason is always attendee-requested). */
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

    /** Admin: initiate a refund for any order (e.g. an event-cancellation 100% refund). */
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
