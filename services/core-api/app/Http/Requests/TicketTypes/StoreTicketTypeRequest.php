<?php

namespace App\Http\Requests\TicketTypes;

use App\Enums\TicketKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ability checked in the controller via the TicketTypePolicy
    }

    /**
     * Money: `price` is integer minor units (poisha) + currency. A group bundle requires a
     * `group_discount` fraction in [0, 1). The capacity invariant is enforced in the service (in a txn).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $salesEndRules = ['nullable', 'date', 'after:sales_start'];
        if ($event !== null) {
            $salesEndRules[] = 'before_or_equal:'.$event->starts_at->toIso8601String();
        }

        return [
            'kind' => ['required', 'string', Rule::in(array_column(TicketKind::cases(), 'value'))],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'quantity_total' => ['required', 'integer', 'min:1'],
            'group_size' => ['nullable', 'integer', 'min:2'],
            'group_discount' => ['nullable', 'required_with:group_size', 'numeric', 'min:0', 'lt:1'],
            'sales_start' => ['nullable', 'date'],
            'sales_end' => $salesEndRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group_discount.lt' => __('api.validation.group_discount_fraction'),
            'group_discount.required_with' => __('api.validation.group_discount_fraction'),
            'sales_end.after' => __('api.validation.sales_ends_after_starts'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->currency)) {
            $this->merge(['currency' => mb_strtoupper($this->currency)]);
        }

        $event = $this->route('event');

        if (empty($this->sales_start)) {
            $this->merge(['sales_start' => now()->toIso8601String()]);
        }

        if (empty($this->sales_end) && $event !== null) {
            $this->merge(['sales_end' => $event->starts_at->toIso8601String()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [
            'kind' => ['example' => 'general'],
            'price' => ['example' => 50000],
            'currency' => ['example' => 'BDT'],
            'quantity_total' => ['example' => 200],
            'group_size' => ['example' => null],
            'group_discount' => ['example' => null],
            'sales_start' => ['example' => '2026-08-01T00:00:00+06:00'],
            'sales_end' => ['example' => '2026-09-19T23:59:59+06:00'],
        ];
    }
}
