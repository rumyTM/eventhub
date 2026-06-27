<?php

namespace App\Http\Requests\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use DateTimeZone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ability checked in the controller via the EventPolicy
    }

    /**
     * All fields optional (partial update). `status` may be supplied to drive a lifecycle transition;
     * the legality of the transition is enforced in EventService, not here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'timezone' => ['sometimes', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(array_column(EventStatus::cases(), 'value'))],
        ];
    }

    /**
     * Ensure starts_at < ends_at using the incoming values merged over the existing event.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Event $event */
            $event = $this->route('event');

            $starts = $this->date('starts_at') ?? $event->starts_at;
            $ends = $this->date('ends_at') ?? $event->ends_at;

            if ($starts !== null && $ends !== null && $starts >= $ends) {
                $validator->errors()->add('ends_at', __('api.validation.ends_after_starts'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.in' => __('api.validation.timezone'),
        ];
    }
}
