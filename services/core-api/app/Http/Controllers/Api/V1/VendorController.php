<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\RejectVendorRequest;
use App\Http\Requests\Vendors\SubmitKycRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use App\Services\Vendors\VendorService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VendorController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly VendorService $vendors,
    ) {}

    /** Vendor: submit (or re-submit) the authenticated vendor's own KYC for review. */
    public function submitKyc(SubmitKycRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $vendor = $request->user()->vendor;
        $this->authorize('submitKyc', $vendor);

        $vendor = $this->vendors->submitForReview($vendor, $request->validated('documents'));

        return ApiResponse::success(
            data: ['vendor' => new VendorResource($vendor)],
            message: __('api.vendors.kyc_submitted'),
            status: 202,
        );
    }

    /** Admin: list vendors awaiting a KYC decision. */
    public function pending(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('reviewAny', Vendor::class);

        $page = $this->vendors->listPending(self::PER_PAGE);

        return ApiResponse::success(
            data: [
                'vendors' => VendorResource::collection($page->getCollection()),
                'pagination' => $this->pagination($page),
            ],
            message: __('api.vendors.pending_listed'),
        );
    }

    /** Admin: verify a pending vendor (pending → verified). */
    public function verify(Request $request, Vendor $vendor): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('review', $vendor);

        $vendor = $this->vendors->verify($vendor, $request->user());

        return ApiResponse::success(
            data: ['vendor' => new VendorResource($vendor)],
            message: __('api.vendors.kyc_verified'),
        );
    }

    /** Admin: reject a pending vendor with a reason (pending → rejected). */
    public function reject(RejectVendorRequest $request, Vendor $vendor): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('review', $vendor);

        $vendor = $this->vendors->reject($vendor, $request->user(), $request->validated('rejection_reason'));

        return ApiResponse::success(
            data: ['vendor' => new VendorResource($vendor)],
            message: __('api.vendors.kyc_rejected'),
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
