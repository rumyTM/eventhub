<?php

namespace App\Http\Requests\Disputes;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route already gated to admin
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
