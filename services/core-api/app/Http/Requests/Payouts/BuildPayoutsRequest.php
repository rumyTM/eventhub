<?php

namespace App\Http\Requests\Payouts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional filters for the admin payout-build trigger. `vendor_id` scopes the build to one vendor;
 * omit to run the full batch for all eligible vendors. `batch_id` defaults to today's ISO date.
 */
class BuildPayoutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by role:admin middleware
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['sometimes', 'string', 'max:26', 'exists:vendors,id'],
            'batch_id' => ['sometimes', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_id.exists' => __('api.payouts.vendor_not_found'),
        ];
    }
}
