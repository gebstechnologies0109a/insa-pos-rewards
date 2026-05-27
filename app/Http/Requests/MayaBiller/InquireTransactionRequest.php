<?php

namespace App\Http\Requests\MayaBiller;

use Illuminate\Foundation\Http\FormRequest;

class InquireTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestReferenceNo' => ['required_without:request_reference_no', 'string', 'max:128'],
            'request_reference_no' => ['required_without:requestReferenceNo', 'string', 'max:128'],
        ];
    }

    public function requestReferenceNo(): string
    {
        return (string) ($this->input('requestReferenceNo') ?? $this->input('request_reference_no'));
    }
}
