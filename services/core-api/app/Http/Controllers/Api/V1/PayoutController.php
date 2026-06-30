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
 * @group Payouts
 *
 * Admin payout management. Payouts are calculated per-vendor after their events complete.
 * Net = gross_sales − (gross × commission_rate). A minimum threshold is enforced; below it the
 * payout rolls to the next cycle. Execution is asynchronous — the terminal result arrives via
 * the payment-service webhook.
 *
 * GET  /admin/payouts               — paginated list (filterable by status/vendor).
 * POST /admin/payouts/build         — calculate pending settlements (decide-only; no money moves).
 * POST /admin/payouts/{payout}/execute — dispatch payment to the vendor.
 */
final class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly PayoutBuildService $buildService,
    ) {}

    /**
     * My payouts (vendor)
     *
     * Paginated list of the authenticated vendor's own payouts, newest first.
     * Optionally filter by `status`.
     *
     * @group Payouts
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Payouts retrieved.","data":{"payouts":[{"id":"01JWXYZ000000000000PAYOUT1","vendor_id":"01JWXYZ0000000000000VENDOR","batch_id":"2026-09-20","currency":"BDT","gross":500000,"commission":50000,"net":450000,"payable":450000,"reserved_refund":0,"status":{"value":"pending","label":"Pending"},"created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}],"pagination":{"current_page":1,"last_page":1,"total":1,"per_page":20}},"errors":null}
     * @response 401 scenario="Unauthenticated" {"success":false,"message":"Unauthenticated.","data":null,"errors":null}
     */
    public function myPayouts(ListPayoutsRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $vendor = $request->user()->vendor;
        if ($vendor === null) {
            return ApiResponse::error(message: 'Vendor profile not found.', status: 422);
        }

        $validated = $request->validated();
        $payouts = $this->payouts->list(
            status: $validated['status'] ?? null,
            vendorId: $vendor->id,
            perPage: (int) ($validated['per_page'] ?? 20),
        );

        return ApiResponse::success(
            data: [
                'payouts' => PayoutResource::collection($payouts),
                'pagination' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'total' => $payouts->total(),
                    'per_page' => $payouts->perPage(),
                ],
            ],
            message: __('api.payouts.listed'),
        );
    }

    /**
     * Preview next payout (vendor)
     *
     * Returns the estimated payout breakdown without creating anything. Returns null data when
     * there are no eligible settled orders or the balance is below the minimum threshold.
     *
     * @group Payouts
     *
     * @authenticated
     */
    public function preview(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $vendor = $request->user()->vendor;
        if ($vendor === null) {
            return ApiResponse::error(message: 'Vendor profile not found.', status: 422);
        }

        $preview = $this->buildService->previewForVendor($vendor->id);

        return ApiResponse::success(
            data: ['preview' => $preview],
            message: $preview !== null ? __('api.payouts.preview_ready') : __('api.payouts.nothing_eligible'),
        );
    }

    /**
     * Request payout (vendor)
     *
     * Creates a pending payout record for the authenticated vendor using today as the batch date.
     * Idempotent — calling again on the same day returns the existing pending payout unchanged.
     * Returns 422 when there are no eligible orders or the balance is below threshold.
     *
     * @group Payouts
     *
     * @authenticated
     */
    public function requestPayout(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $vendor = $request->user()->vendor;
        if ($vendor === null) {
            return ApiResponse::error(message: 'Vendor profile not found.', status: 422);
        }

        $batchId = now()->toDateString();
        $payout  = $this->buildService->buildForVendor($vendor->id, $batchId);

        if ($payout === null) {
            return ApiResponse::error(
                message: __('api.payouts.nothing_eligible'),
                status: 422,
            );
        }

        return ApiResponse::success(
            data: ['payout' => new PayoutResource($payout)],
            message: __('api.payouts.requested'),
            status: 201,
        );
    }

    /**
     * Execute payout
     *
     * Dispatch payment for a `pending` or `approved` payout. The job transitions the payout to
     * `processing` and calls payment-service; the terminal result (`paid` or `failed`) arrives
     * via the signed payment-service webhook. Idempotent — re-dispatching the same payout
     * reuses the deterministic idempotency key.
     *
     * @group Admin
     *
     * @subgroup Payouts
     *
     * @authenticated
     *
     * @response 200 scenario="Queued" {"success":true,"message":"Payout execution queued.","data":{"payout":{"id":"01J000000000000DEMOPAYOUT","vendor_id":"01J000000000000DEMOVENDOR","gross":500000,"commission":50000,"net":450000,"currency":"BDT","status":{"value":"pending","label":"Pending"},"batch_id":"01J0BATCHID","created_at":"2026-06-30T09:00:00Z"}},"errors":null}
     * @response 409 scenario="Not executable" {"success":false,"message":"This payout cannot be executed in its current status.","data":null,"errors":null}
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

    /**
     * List payouts (admin)
     *
     * Paginated list of all vendor payouts. Filter by `status` and/or `vendor_id`.
     *
     * @group Admin
     *
     * @subgroup Payouts
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Payouts retrieved.","data":{"payouts":[{"id":"01JWXYZ000000000000PAYOUT1","vendor_id":"01JWXYZ0000000000000VENDOR","batch_id":"2026-09-20","currency":"BDT","gross":500000,"commission":50000,"net":450000,"payable":450000,"reserved_refund":0,"status":{"value":"pending","label":"Pending"},"created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}],"pagination":{"current_page":1,"last_page":1,"total":1,"per_page":20}},"errors":null}
     */
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
                'pagination' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'total' => $payouts->total(),
                    'per_page' => $payouts->perPage(),
                ],
            ],
            message: __('api.payouts.listed'),
        );
    }

    /**
     * Build payout batch (admin)
     *
     * Calculate pending settlements for all eligible vendors (or a single vendor if `vendor_id`
     * is provided). This is a **decide-only** step — it creates `Payout` records but moves no money.
     * Call `/admin/payouts/{payout}/execute` to actually disburse. Idempotent per `batch_id`.
     *
     * @group Admin
     *
     * @subgroup Payouts
     *
     * @authenticated
     *
     * @response 201 scenario="Batch built" {"success":true,"message":"1 payout(s) built.","data":{"batch_id":"2026-06-30","count":1,"payouts":[{"id":"01J000000000000DEMOPAYOUT","vendor_id":"01J000000000000DEMOVENDOR","gross":500000,"commission":50000,"net":450000,"currency":"BDT","status":{"value":"pending","label":"Pending"},"batch_id":"2026-06-30","created_at":"2026-06-30T09:00:00Z"}]},"errors":null}
     */
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
