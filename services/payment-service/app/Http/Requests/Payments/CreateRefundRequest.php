<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a refund request from core-api (system-architecture.md §3.5). The route is already gated by
 * EnsureServiceToken (shared secret), so authorize() simply passes — there is no user identity here.
 *
 * The Idempotency-Key arrives as a header; we fold it into the validated data (mirroring CreatePaymentRequest)
 * so a missing key is a clean 422, not a 500, and the same key always maps to the same refund (ADR-09).
 * core-api decides the amount (the 100/50/0% policy lives there); this service executes and records the
 * exact amount it is told — never recomputed here, and never any card field on the boundary.
 */
class CreateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by the service.token (shared-secret) middleware
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            // The original charge being refunded — must be a real payment in this service.
            'payment_ref' => ['required', 'string', Rule::exists('payments', 'id')],
            'amount' => ['required', 'integer', 'min:1'], // integer minor units (poisha) — never float, never 0
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => __('api.refunds.idempotency_key_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
