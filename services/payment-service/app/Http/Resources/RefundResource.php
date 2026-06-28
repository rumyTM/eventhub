<?php

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The refund as core-api sees it (system-architecture.md §3.5). `ref` is the refund's ULID — the stable
 * handle the webhook echoes back; `payment_ref` is the original charge. Enums render as `{ value, label }`;
 * timestamps are ISO-8601. `gateway_ref` is a clearly-fake simulated reference (null until the refund
 * resolves) — there is no card field here to expose, ever.
 *
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
            'ref' => $this->id,
            'payment_ref' => $this->payment_id,
            'order_id' => $this->order_id,
            'gateway' => [
                'value' => $this->gateway->value,
                'label' => $this->gateway->label(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'gateway_ref' => $this->gateway_ref,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
