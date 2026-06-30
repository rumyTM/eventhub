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
                'attendee_name' => $this->order->relationLoaded('attendee')
                    ? $this->order->attendee?->user?->name
                    : null,
                'events' => $this->order->relationLoaded('items') ? $this->eventSummaries() : [],
                'created_at' => $this->order->created_at?->toIso8601String(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Distinct events across the order's items, in encounter order (mirrors OrderResource's helper —
     * kept local since each Resource here is self-contained and this is the only other call site).
     *
     * @return list<array{id: string, title: string}>
     */
    private function eventSummaries(): array
    {
        $seen = [];
        $events = [];

        foreach ($this->order->items as $item) {
            $event = $item->ticketType?->event;
            if ($event === null || isset($seen[$event->id])) {
                continue;
            }

            $seen[$event->id] = true;
            $events[] = ['id' => $event->id, 'title' => $event->title];
        }

        return $events;
    }
}
