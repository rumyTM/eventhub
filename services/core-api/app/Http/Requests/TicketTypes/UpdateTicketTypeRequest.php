<?php

namespace App\Http\Requests\TicketTypes;

use App\Enums\TicketKind;
use App\Models\TicketType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ability checked in the controller via the TicketTypePolicy
    }

    /**
     * Partial update. The capacity invariant and the quantity_total >= quantity_sold guard are enforced
     * in the service (inside a transaction under an event row lock).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['sometimes', 'string', Rule::in(array_column(TicketKind::cases(), 'value'))],
            'price' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'quantity_total' => ['sometimes', 'integer', 'min:1'],
            'group_size' => ['sometimes', 'nullable', 'integer', 'min:2'],
            'group_discount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'lt:1'],
            'sales_start' => ['sometimes', 'nullable', 'date'],
            'sales_end' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var TicketType $ticketType */
            $ticketType = $this->route('ticketType');

            $start = $this->date('sales_start') ?? $ticketType->sales_start;
            $end = $this->date('sales_end') ?? $ticketType->sales_end;

            if ($start !== null && $end !== null && $start >= $end) {
                $validator->errors()->add('sales_end', __('api.validation.sales_ends_after_starts'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group_discount.lt' => __('api.validation.group_discount_fraction'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->currency)) {
            $this->merge(['currency' => mb_strtoupper($this->currency)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [
            'kind' => ['example' => 'vip'],
            'price' => ['example' => 150000],
            'currency' => ['example' => 'BDT'],
            'quantity_total' => ['example' => 50],
            'group_size' => ['example' => null],
            'group_discount' => ['example' => null],
            'sales_start' => ['example' => '2026-08-01T00:00:00+06:00'],
            'sales_end' => ['example' => '2026-09-19T23:59:59+06:00'],
        ];
    }
}
