<?php

namespace App\Http\Requests\Payments;

use App\Enums\Gateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a charge request from core-api. The route is already gated by EnsureServiceToken
 * (shared secret), so authorize() simply passes — there is no user identity at this boundary.
 *
 * The Idempotency-Key arrives as a header; we fold it into the validated data (mirroring core-api's
 * CheckoutRequest) so a missing key is a clean 422, not a 500, and the same key always maps to the
 * same charge (ADR-09). The callback URL is NOT taken from the request — it is a fixed, trusted
 * config value (SSRF guard); accepting an arbitrary URL here would let a caller redirect signed
 * webhooks anywhere.
 */
class CreatePaymentRequest extends FormRequest
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
            'order_id' => ['required', 'string', 'max:255'],
            'gateway' => ['required', Rule::in(array_column(Gateway::cases(), 'value'))],
            'amount' => ['required', 'integer', 'min:1'], // integer minor units (poisha) — never float, never 0
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => __('api.payments.idempotency_key_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
