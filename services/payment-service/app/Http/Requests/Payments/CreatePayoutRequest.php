<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a payout execution request from core-api (CLAUDE.md §C). The route is gated by
 * EnsureServiceToken (shared secret), so authorize() simply passes — there is no user identity here.
 *
 * The Idempotency-Key arrives as a header; we fold it into the validated data (mirroring
 * CreateRefundRequest) so a missing key is a clean 422, not a 500, and the same key always maps to
 * the same payout execution (ADR-09). No card field is accepted here — only references and an amount.
 */
class CreatePayoutRequest extends FormRequest
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
            'payout_ref' => ['required', 'string', 'max:255'],  // core-api Payout ULID — never card data
            'vendor_id' => ['required', 'string', 'max:255'],   // core-api vendor ULID — no PAN/PII
            'amount' => ['required', 'integer', 'min:1'],       // integer minor units (poisha) — never float
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => __('api.payouts.idempotency_key_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }
}
