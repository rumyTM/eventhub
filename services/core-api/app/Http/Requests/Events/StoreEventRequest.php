<?php

namespace App\Http\Requests\Events;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ability checked in the controller via the EventPolicy
    }

    /**
     * New events are always created as `draft`; status is not accepted here (use update to transition).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.in' => __('api.validation.timezone'),
            'ends_at.after' => __('api.validation.ends_after_starts'),
        ];
    }
}
