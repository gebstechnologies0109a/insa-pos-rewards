<?php

namespace App\Http\Requests\MayaBiller;

use Illuminate\Foundation\Http\FormRequest;

class GetFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billerCode' => ['required_without:biller_code', 'string', 'max:64'],
            'biller_code' => ['required_without:billerCode', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function billerCode(): string
    {
        return (string) ($this->input('billerCode') ?? $this->input('biller_code'));
    }
}
