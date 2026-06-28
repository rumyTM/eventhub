<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The charge as core-api sees it (system-architecture.md §API Contracts). `ref` is the payment's
 * ULID — the stable handle core-api stores and the webhook echoes back. Enums render as
 * `{ value, label }`; timestamps are ISO-8601. `gateway_ref` is a clearly-fake simulated reference
 * (null until the charge resolves) — there is no card field here to expose, ever.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ref' => $this->id,
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
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
