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
            'ref' => $this->id,
            'payout_ref' => $this->payout_ref,   // core-api Payout ID (correlation key)
            'vendor_id' => $this->vendor_id,
            'amount' => (int) $this->amount,      // integer minor units — never float, never card data
            'currency' => $this->currency,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'gateway_ref' => $this->gateway_ref,  // fake simulated ref ([PLACEHOLDER]) — never card data
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
