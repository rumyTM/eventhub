<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by auth:sanctum middleware
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('api.orders.validation.status_invalid'),
            'per_page.integer' => __('api.orders.validation.per_page_integer'),
            'per_page.min' => __('api.orders.validation.per_page_min'),
            'per_page.max' => __('api.orders.validation.per_page_max'),
        ];
    }

    /**
     * For Scribe query parameter documentation.
     *
     * @return array<string, mixed>
     */
    public function queryParameters(): array
    {
        return [
            'status' => [
                'description' => 'Filter orders by status. Attendees always see only their own orders; admins may use this to narrow the result set.',
                'example' => 'paid',
            ],
            'per_page' => [
                'description' => 'Number of items per page (1–100).',
                'example' => 15,
            ],
        ];
    }
}
