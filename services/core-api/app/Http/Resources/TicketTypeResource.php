<?php

namespace App\Http\Resources;

use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketType
 */
class TicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'kind' => [
                'value' => $this->kind->value,
                'label' => $this->kind->label(),
            ],
            'price' => $this->price,            // integer minor units (poisha)
            'currency' => $this->currency,
            'quantity_total' => $this->quantity_total,
            'quantity_sold' => $this->quantity_sold,
            'group_size' => $this->group_size,
            'group_discount' => $this->group_discount !== null ? (float) $this->group_discount : null,
            'sales_start' => $this->sales_start?->toIso8601String(),
            'sales_end' => $this->sales_end?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
