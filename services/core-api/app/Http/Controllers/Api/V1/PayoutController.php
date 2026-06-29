<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayoutStatus;
use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payouts\BuildPayoutsRequest;
use App\Http\Requests\Payouts\ListPayoutsRequest;
use App\Http\Resources\PayoutResource;
use App\Jobs\ExecutePayoutJob;
use App\Models\Payout;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Services\Payouts\PayoutBuildService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin payout endpoints.
 *
 * GET  /admin/payouts               — paginated list of all payouts (filterable by status/vendor).
 * POST /admin/payouts/build         — trigger a payout-build run (decide-only; no money moves).
 * POST /admin/payouts/{payout}/execute — dispatch ExecutePayoutJob for a pending/approved payout.
 */
final class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly PayoutBuildService $buildService,
    ) {}

    /**
     * Dispatch ExecutePayoutJob for a payout that is `pending` or `approved`. The job flips it to
     * `processing` and POSTs to payment-service; the terminal result arrives via the signed webhook.
     * Idempotent: re-dispatching the same payout reuses the deterministic idempotency key (ADR-09).
     */
    public function execute(Request $request, Payout $payout): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $executables = [PayoutStatus::Pending, PayoutStatus::Approved];

        if (! in_array($payout->status, $executables, true)) {
            return ApiResponse::error(
                message: __('api.payouts.not_executable'),
                status: 409,
            );
        }

        ExecutePayoutJob::dispatch($payout->id);

        return ApiResponse::success(
            data: ['payout' => new PayoutResource($payout)],
            message: __('api.payouts.execution_queued'),
        );
    }

    public function index(ListPayoutsRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $validated = $request->validated();
        $payouts = $this->payouts->list(
            status: $validated['status'] ?? null,
            vendorId: $validated['vendor_id'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 20),
        );

        return ApiResponse::success(
            data: [
                'payouts' => PayoutResource::collection($payouts),
                'meta' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'total' => $payouts->total(),
                    'per_page' => $payouts->perPage(),
                ],
            ],
            message: __('api.payouts.listed'),
        );
    }

    public function build(BuildPayoutsRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $validated = $request->validated();
        $batchId = $validated['batch_id'] ?? now()->toDateString();
        $vendorId = $validated['vendor_id'] ?? null;

        if ($vendorId !== null) {
            $payout = $this->buildService->buildForVendor($vendorId, $batchId);
            $built = $payout !== null ? [$payout] : [];
        } else {
            $built = $this->buildService->buildAll($batchId);
        }

        return ApiResponse::success(
            data: [
                'batch_id' => $batchId,
                'count' => count($built),
                'payouts' => PayoutResource::collection(collect($built)),
            ],
            message: __('api.payouts.built', ['count' => count($built)]),
            status: 201,
        );
    }
}
