<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vendor profile — deliberately omits encrypted KYC/PII (tin_bin, representative_nid, payout_account,
 * webhook_secret). Those are never returned by the API; document bytes are served only via signed URLs.
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
            'legal_name' => $this->legal_name,
            'trade_license_no' => $this->trade_license_no,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'kyc_status' => [
                'value' => $this->kyc_status->value,
                'label' => $this->kyc_status->label(),
            ],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'kyc_documents' => KycDocumentResource::collection($this->whenLoaded('kycDocuments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
