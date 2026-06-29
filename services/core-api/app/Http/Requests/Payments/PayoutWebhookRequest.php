<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payment-service PAYOUT webhook payload. The route is gated by the `webhook.signature`
 * middleware (bearer + HMAC over the raw body), so authorize() passes — there is no user identity.
 * Only the two terminal statuses are accepted; the amount is matched against the payout's payable
 * in the service before anything is mutated.
 */
class PayoutWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the webhook.signature middleware (bearer + raw-body HMAC)
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:255'],
            'payout_ref' => ['required', 'string', 'max:255'], // core-api Payout ID — never card data
            'vendor_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'array'],
            // payment-service vocabulary: 'completed' (success) | 'failed' — different from core-api's
            // PayoutStatus which uses 'paid'. The webhook carries the payment-service's status string.
            'status.value' => ['required', Rule::in(['completed', 'failed'])],
            'amount' => ['required', 'integer', 'min:1'], // integer minor units — never float
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
