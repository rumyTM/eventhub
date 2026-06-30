<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disputes\RejectDisputeRequest;
use App\Http\Requests\Disputes\ResolveDisputeRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Services\Disputes\DisputeService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Disputes (Admin)
 *
 * Admin management of out-of-policy refund disputes (ADR-11).
 * Disputes are opened automatically when an attendee requests a refund inside the <24 h 0% window.
 * Admin can approve (resolve) or deny (reject) each dispute.
 */
final class DisputeController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly DisputeService $disputes,
    ) {}

    /**
     * List open disputes
     *
     * Returns a paginated list of open disputes awaiting admin review.
     *
     * @group Admin
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Disputes retrieved.","data":{"disputes":[{"id":"01J000000000000DEMODISPUTE","order_id":"01J000000000000DEMOORDER1","status":{"value":"open","label":"Open"},"reason":"attendee_requested","created_at":"2026-06-30T20:00:00Z"}],"pagination":{"current_page":1,"per_page":15,"total":1,"last_page":1}},"errors":null}
     */
    public function index(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $page = $this->disputes->list(perPage: self::PER_PAGE);

        return ApiResponse::success(
            data: [
                'disputes' => DisputeResource::collection($page->getCollection()),
                'pagination' => $this->pagination($page),
            ],
            message: __('api.disputes.listed'),
        );
    }

    /**
     * Resolve dispute (approve refund)
     *
     * Approve the dispute: creates a full-remaining-balance refund override (ignoring the
     * time-based policy) and marks the dispute resolved. Idempotent on an already-resolved dispute.
     *
     * @group Admin
     *
     * @authenticated
     *
     * @response 200 scenario="Resolved" {"success":true,"message":"Dispute resolved. Refund has been queued.","data":{"dispute":{"id":"01J000000000000DEMODISPUTE","order_id":"01J000000000000DEMOORDER1","status":{"value":"resolved","label":"Resolved"},"reason":"attendee_requested","created_at":"2026-06-30T20:00:00Z"}},"errors":null}
     */
    public function resolve(ResolveDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $resolved = $this->disputes->resolve(
            dispute: $dispute,
            admin: $request->user(),
            resolution: $request->validated('resolution'),
        );

        return ApiResponse::success(
            data: ['dispute' => new DisputeResource($resolved)],
            message: __('api.disputes.resolved'),
        );
    }

    /**
     * Reject dispute
     *
     * Deny the dispute: no refund is issued. The dispute is closed with the admin's resolution note.
     * Idempotent on an already-rejected dispute.
     *
     * @group Admin
     *
     * @authenticated
     *
     * @response 200 scenario="Rejected" {"success":true,"message":"Dispute rejected.","data":{"dispute":{"id":"01J000000000000DEMODISPUTE","order_id":"01J000000000000DEMOORDER1","status":{"value":"rejected","label":"Rejected"},"reason":"attendee_requested","created_at":"2026-06-30T20:00:00Z"}},"errors":null}
     */
    public function reject(RejectDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $rejected = $this->disputes->reject(
            dispute: $dispute,
            admin: $request->user(),
            resolution: $request->validated('resolution'),
        );

        return ApiResponse::success(
            data: ['dispute' => new DisputeResource($rejected)],
            message: __('api.disputes.rejected'),
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
