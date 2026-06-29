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

/**
 * @group Vendors / KYC
 *
 * Vendor onboarding and KYC (Know Your Customer) review. Vendors must be KYC-verified
 * before they can publish events or receive payouts.
 */
final class VendorController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly VendorService $vendors,
    ) {}

    /**
     * Submit KYC (vendor)
     *
     * Submit or re-submit the authenticated vendor's KYC documents for admin review.
     * KYC status transitions: `pending → verified` (admin approves) or `pending → rejected`.
     * Only `pending` and `rejected` vendors may re-submit.
     *
     * @group Vendor
     * @subgroup KYC
     * @authenticated
     */
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

    /**
     * List pending vendors (admin)
     *
     * Paginated list of vendors with `kyc_status=pending`, awaiting an admin decision.
     *
     * @group Admin
     * @subgroup Vendors
     * @authenticated
     * @response 200 scenario="Success" {"success":true,"message":"Vendors pending KYC review retrieved.","data":{"vendors":[{"id":"01JWXYZ0000000000000VENDOR","business_name":"Acme Events Ltd","legal_name":null,"trade_license_no":null,"contact_phone":"+8801711000000","address":null,"kyc_status":{"value":"pending","label":"Pending"},"submitted_at":"2026-06-30T09:00:00+00:00","reviewed_at":null,"rejection_reason":null,"kyc_documents":[],"created_at":"2026-06-30T09:00:00+00:00"}],"pagination":{"current_page":1,"per_page":25,"total":1,"last_page":1}},"errors":null}
     */
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

    /**
     * Verify vendor (admin)
     *
     * Approve a vendor's KYC submission (`pending → verified`). Verified vendors can publish
     * events and receive payouts.
     *
     * @group Admin
     * @subgroup Vendors
     * @authenticated
     */
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

    /**
     * Reject vendor (admin)
     *
     * Reject a vendor's KYC submission (`pending → rejected`) with a mandatory reason.
     * The vendor may re-submit after addressing the stated issues.
     *
     * @group Admin
     * @subgroup Vendors
     * @authenticated
     */
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
