<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth:sanctum + role:attendee
    }

    /**
     * The Idempotency-Key arrives as a header; we fold it into the validated data so a missing key is a
     * clean 422 (not a 500), and the same key always maps to the same order (ADR-09).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            // Exclude soft-deleted ticket types — the default `exists` rule would accept them.
            'items.*.ticket_type_id' => ['required', 'string', Rule::exists('ticket_types', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => __('api.orders.idempotency_key_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [
            'idempotency_key' => ['example' => 'No-example'],
            'items.*.ticket_type_id' => ['example' => '01JWXYZ0000000000000TICKET1'],
            'items.*.quantity' => ['example' => 2],
        ];
    }
}
