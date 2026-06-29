<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payouts\BuildPayoutsRequest;
use App\Http\Requests\Payouts\ListPayoutsRequest;
use App\Http\Resources\PayoutResource;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Services\Payouts\PayoutBuildService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Admin payout endpoints (Chunk D — decide/preview only; no money moves until Chunk E execution).
 *
 * GET  /admin/payouts           — paginated list of all payouts (filterable by status/vendor).
 * POST /admin/payouts/build     — trigger a payout-build run: creates pending Payout + PayoutItem rows
 *                                 for all eligible vendors (or a single vendor if `vendor_id` supplied).
 *                                 Idempotent: re-running the same batch_id returns existing records.
 */
final class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly PayoutBuildService $buildService,
    ) {}

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
