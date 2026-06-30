<?php

namespace App\Http\Resources;

use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'reason' => $this->reason,
            'resolution' => $this->resolution,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'total' => $this->order->total,
                'currency' => $this->order->currency,
                'status' => [
                    'value' => $this->order->status->value,
                    'label' => $this->order->status->label(),
                ],
                'created_at' => $this->order->created_at?->toIso8601String(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
