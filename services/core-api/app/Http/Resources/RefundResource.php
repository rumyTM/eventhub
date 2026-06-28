<?php

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'reason' => [
                'value' => $this->reason->value,
                'label' => $this->reason->label(),
            ],
            'policy_applied' => $this->policy_applied, // '100' | '50' | '0'
            'amount' => $this->amount,                 // integer minor units (poisha) — auto-derived
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
