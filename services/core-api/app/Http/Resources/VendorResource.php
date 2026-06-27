<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vendor profile — deliberately omits encrypted KYC/PII (tin_bin, representative_nid, payout_account,
 * webhook_secret). Those are never returned by the API; admin review uses dedicated, audited endpoints.
 *
 * @mixin Vendor
 */
class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'kyc_status' => [
                'value' => $this->kyc_status->value,
                'label' => $this->kyc_status->label(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
