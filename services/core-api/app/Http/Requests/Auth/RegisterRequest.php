<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Public self-registration is limited to vendor/attendee. Admin accounts are provisioned via
     * seeder/console only — never through a public endpoint (privilege-escalation guard).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in([Role::Vendor->value, Role::Attendee->value])],

            // Vendor-only: the business name is required to create the vendor profile.
            'business_name' => ['required_if:role,'.Role::Vendor->value, 'nullable', 'string', 'max:255'],

            // Attendee-only: optional contact phone.
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => __('api.auth.role_not_self_assignable'),
            'business_name.required_if' => __('api.auth.business_name_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [
            'name'                  => ['example' => 'Alice Smith'],
            'email'                 => ['example' => 'alice@example.com'],
            'password'              => ['example' => 'password123'],
            'password_confirmation' => ['example' => 'password123'],
            'role'                  => ['example' => 'vendor'],
            'business_name'         => ['example' => 'Acme Events Ltd'],
            'phone'                 => ['example' => '+8801711000000'],
        ];
    }
}
