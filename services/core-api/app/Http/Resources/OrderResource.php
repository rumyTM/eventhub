<?php

namespace App\Http\Resources;

use App\Enums\HoldStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                $perUnit = $item->original_price - $item->unit_price;
                $percent = $item->original_price > 0
                    ? intdiv($perUnit * 100, $item->original_price)
                    : 0;

                return [
                    'id' => $item->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'ticket_type' => $item->relationLoaded('ticketType') && $item->ticketType !== null ? [
                        'id' => $item->ticketType->id,
                        'kind' => [
                            'value' => $item->ticketType->kind->value,
                            'label' => $item->ticketType->kind->label(),
                        ],
                    ] : null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'original_price' => $item->original_price,
                    'discount' => [
                        'per_unit' => $perUnit,
                        'line_total' => $perUnit * $item->quantity,
                        'percent' => $percent,
                    ],
                ];
            })),
            // Distinct events represented by this order's items — display context so the attendee/admin
            // never has to resolve a ticket_type_id to know which event an order belongs to.
            'events' => $this->whenLoaded('items', fn () => $this->eventSummaries()),
            'attendee' => $this->whenLoaded('attendee', fn () => [
                'id' => $this->attendee->id,
                'name' => $this->attendee->relationLoaded('user') ? $this->attendee->user?->name : null,
            ]),
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
            // True when the order is pending but the most-recent payment attempt failed.
            // Lets the checkout page surface a retry prompt instead of spinning indefinitely.
            'payment_failed' => $this->whenLoaded(
                'latestPayment',
                fn () => $this->status === OrderStatus::Pending
                    && $this->latestPayment?->status === PaymentStatus::Failed,
                false,
            ),
            // True when a refund in requested/pending status exists for this order.
            // Lets the attendee UI hide the "Request Refund" button without relying on local state.
            'has_pending_refund' => $this->whenLoaded(
                'latestOpenRefund',
                fn () => $this->latestOpenRefund !== null,
                false,
            ),
            // Most recent refund for this order (any status). Lets the attendee UI show refund
            // amount, policy, and current processing state without a separate API call.
            'latest_refund' => $this->whenLoaded(
                'latestRefund',
                fn () => $this->latestRefund ? [
                    'id' => $this->latestRefund->id,
                    'amount' => $this->latestRefund->amount,
                    'policy_applied' => $this->latestRefund->policy_applied,
                    'status' => [
                        'value' => $this->latestRefund->status->value,
                        'label' => $this->latestRefund->status->label(),
                    ],
                    'created_at' => $this->latestRefund->created_at?->toIso8601String(),
                ] : null,
                null,
            ),
            // Latest dispute for this order (open / resolved / rejected). Lets the attendee UI
            // surface dispute status and any rejection reason without a separate API call.
            'latest_dispute' => $this->whenLoaded(
                'latestDispute',
                fn () => $this->latestDispute ? [
                    'id' => $this->latestDispute->id,
                    'status' => [
                        'value' => $this->latestDispute->status->value,
                        'label' => $this->latestDispute->status->label(),
                    ],
                    'reason' => $this->latestDispute->reason,
                    'resolution' => $this->latestDispute->resolution,
                    'created_at' => $this->latestDispute->created_at?->toIso8601String(),
                ] : null,
                null,
            ),
            // Issued ticket artifacts with their check-in state. Only present on the detail endpoint
            // (not the list) since the list does not eager-load tickets.
            'tickets' => $this->whenLoaded('tickets', fn () => $this->tickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'qr_code' => $ticket->qr_code,
                'ticket_type' => $ticket->ticketType ? [
                    'id' => $ticket->ticketType->id,
                    'kind' => [
                        'value' => $ticket->ticketType->kind->value,
                        'label' => $ticket->ticketType->kind->label(),
                    ],
                ] : null,
                'status' => [
                    'value' => $ticket->status->value,
                    'label' => $ticket->status->label(),
                ],
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            ])),
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

    /**
     * Distinct events across this order's items, in encounter order. Normally exactly one — checkout
     * doesn't explicitly forbid mixing ticket types from different events, so this stays a list rather
     * than asserting a single-event invariant that isn't actually enforced.
     *
     * @return list<array{id: string, title: string}>
     */
    private function eventSummaries(): array
    {
        $seen = [];
        $events = [];

        foreach ($this->items as $item) {
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
