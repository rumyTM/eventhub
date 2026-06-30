<?php

namespace App\Http\Requests\Payouts;

use App\Enums\PayoutStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPayoutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role gating is handled by route middleware
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(array_column(PayoutStatus::cases(), 'value'))],
            'vendor_id' => ['sometimes', 'string', 'max:26'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The status must be a valid payout status.',
            'per_page.integer' => 'The per_page value must be a whole number.',
            'per_page.min' => 'The per_page value must be at least 1.',
            'per_page.max' => 'The per_page value may not exceed 100.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'Filter by payout status.', 'example' => 'pending'],
            'vendor_id' => ['description' => 'Filter by vendor (admin only).', 'example' => 'no-example'],
            'per_page' => ['description' => 'Items per page (1–100).', 'example' => 20],
        ];
    }
}
