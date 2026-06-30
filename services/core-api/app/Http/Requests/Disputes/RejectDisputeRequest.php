<?php

namespace App\Http\Requests\Disputes;

use Illuminate\Foundation\Http\FormRequest;

class RejectDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route already gated to admin
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resolution.required' => __('api.disputes.resolution_required'),
        ];
    }
}
