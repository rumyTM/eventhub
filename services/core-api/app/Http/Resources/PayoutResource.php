<?php

namespace App\Http\Resources;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payout
 */
class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'business_name' => $this->vendor->business_name,
            ]),
            'batch_id' => $this->batch_id,
            'currency' => $this->currency,
            'gross' => $this->gross,
            'commission' => $this->commission,
            'net' => $this->net,               // gross − commission; accounting net
            'payable' => $this->payable,       // net + adjustments, floored at 0; disbursable amount
            'reserved_refund' => $this->reserved_refund,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
