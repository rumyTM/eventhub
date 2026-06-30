<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ability checked in the controller via the VendorPolicy
    }

    /**
     * The client submits document *references* (e.g. a signed-upload key), never raw bytes. Storage paths
     * are treated as opaque references and stored encrypted; they are never returned or logged.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.type' => ['required', 'string', Rule::in(['trade_license', 'nid', 'bank_statement'])],
            'documents.*.storage_path' => ['required', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [
            'documents.*.type' => ['example' => 'trade_license'],
            'documents.*.storage_path' => ['example' => 'kyc/vendors/01JWXYZ0000VENDOR/trade_license.pdf'],
        ];
    }
}
