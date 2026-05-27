<?php

namespace App\Http\Requests\MayaBiller;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePaymentRequest extends FormRequest
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
            'accountNumber' => ['required_without:account_number', 'string', 'max:128'],
            'account_number' => ['required_without:accountNumber', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function billerCode(): string
    {
        return (string) ($this->input('billerCode') ?? $this->input('biller_code'));
    }

    public function accountNumber(): string
    {
        return (string) ($this->input('accountNumber') ?? $this->input('account_number'));
    }
}
