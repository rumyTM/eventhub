<?php

namespace App\Http\Requests\Refunds;

use App\Enums\RefundReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-initiated refund for an order (CLAUDE.md §F / ADR-11). The admin may set the `reason` — notably
 * `event_cancelled`, which the policy refunds at a flat 100% regardless of the time window (ADR-23).
 * Defaults to `attendee_requested` when omitted. Like the attendee path, no amount is ever supplied.
 */
class InitiateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth:sanctum + role:admin; defence-in-depth via OrderPolicy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['sometimes', Rule::in(array_column(RefundReason::cases(), 'value'))],
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            // Scope existence to THIS order (the service re-asserts ownership as defence in depth).
            'items.*.order_item_id' => [
                'required_with:items', 'string',
                Rule::exists('order_items', 'id')->where('order_id', $this->route('order')?->id),
            ],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** The chosen reason category, defaulting to attendee-requested. */
    public function refundReason(): RefundReason
    {
        return RefundReason::from($this->validated('reason', RefundReason::AttendeeRequested->value));
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [
            'reason' => ['example' => 'event_cancelled'],
            'items.*.order_item_id' => ['example' => '01JWXYZ000000000000OITEM1'],
            'items.*.quantity' => ['example' => 1],
        ];
    }
}
