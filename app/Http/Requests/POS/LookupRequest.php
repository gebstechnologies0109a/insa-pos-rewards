<?php

namespace App\Http\Requests\POS;

use Illuminate\Foundation\Http\FormRequest;

class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type'  => ['required', 'string', 'in:qr,barcode,phone,search'],
            'value' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Lookup type must be one of: qr, barcode, phone, search.',
        ];
    }
}
