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
        return true; // gated by role:admin middleware
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
}
