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

    /** @return array<string, mixed> */
    public function bodyParameters(): array
    {
        return [
            'resolution' => [
                'description' => 'Admin note explaining why the dispute was rejected (required).',
                'example' => 'Event terms clearly state no refunds within 24 hours. Dispute rejected.',
            ],
        ];
    }
}
