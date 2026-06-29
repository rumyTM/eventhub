<?php

namespace App\Http\Requests\Payments;

use App\Enums\RefundStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payment-service REFUND webhook payload (system-architecture.md §3.5). The route is gated
 * by the SAME `webhook.signature` middleware as the charge webhook (bearer + HMAC over the raw body), so
 * authorize() passes — there is no user identity here. Only the two terminal statuses are accepted; the
 * amount/currency are matched against the open refund in the service before anything is mutated.
 */
class RefundWebhookRequest extends FormRequest
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
            'refund_ref' => ['required', 'string', 'max:255'],
            'payment_ref' => ['required', 'string', 'max:255'],
            'order_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'array'],
            'status.value' => ['required', Rule::in([
                RefundStatus::Completed->value,
                RefundStatus::Failed->value,
            ])],
            'amount' => ['required', 'integer', 'min:1'], // integer minor units — never float
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
