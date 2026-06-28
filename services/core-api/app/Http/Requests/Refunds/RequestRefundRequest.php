<?php

namespace App\Http\Requests\Refunds;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Attendee refund request for their own paid order. The attendee NEVER specifies an amount — it is
 * auto-derived from the policy (CLAUDE.md §F). `items` optionally selects a subset of tickets (partial
 * refund); omitted → the whole order. Line ownership (item belongs to THIS order) + quantity bounds are
 * enforced in the service, where the order is known.
 */
class RequestRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth:sanctum + role:attendee; ownership via OrderPolicy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            // Scope existence to THIS order — a line id from another order must not even validate (the
            // service re-asserts ownership as defence in depth).
            'items.*.order_item_id' => [
                'required_with:items', 'string',
                Rule::exists('order_items', 'id')->where('order_id', $this->route('order')?->id),
            ],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100'],
        ];
    }
}
