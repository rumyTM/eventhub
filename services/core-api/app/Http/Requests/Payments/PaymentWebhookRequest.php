<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payment-service webhook payload (system-architecture.md §API Contracts). The route is
 * gated by the `webhook.signature` middleware (bearer + HMAC over the raw body), so authorize() passes
 * — there is no user identity here. Only the two terminal statuses are accepted; the amount/currency
 * are matched against the order in the service before anything is mutated.
 */
class PaymentWebhookRequest extends FormRequest
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
            'payment_ref' => ['required', 'string', 'max:255'],
            'order_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'array'],
            'status.value' => ['required', Rule::in([
                PaymentStatus::Succeeded->value,
                PaymentStatus::Failed->value,
            ])],
            'amount' => ['required', 'integer', 'min:1'], // integer minor units — never float
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
