<?php

namespace App\Http\Resources;

use App\Enums\HoldStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'total' => $this->total,                 // integer minor units (poisha)
            'currency' => $this->currency,
            // Exact decimal string (e.g. "0.1000") — never a float, to preserve the snapshot precision.
            'commission_rate' => $this->commission_rate,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'ticket_type_id' => $item->ticket_type_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])),
            'holds' => $this->whenLoaded('holds', fn () => $this->holds->map(fn ($hold) => [
                'id' => $hold->id,
                'ticket_type_id' => $hold->ticket_type_id,
                'quantity' => $hold->quantity,
                'status' => [
                    'value' => $hold->status->value,
                    'label' => $hold->status->label(),
                ],
                'expires_at' => $hold->expires_at?->toIso8601String(),
            ])),
            // Soonest active-hold expiry — the client renders the checkout countdown from this.
            'hold_expires_at' => $this->whenLoaded('holds', fn () => $this->soonestActiveHoldExpiry()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function soonestActiveHoldExpiry(): ?string
    {
        $expiry = $this->holds
            ->where('status', HoldStatus::Active)
            ->min('expires_at');

        return $expiry?->toIso8601String();
    }
}
