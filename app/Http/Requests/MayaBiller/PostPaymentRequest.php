<?php

namespace App\Http\Requests\MayaBiller;

use Illuminate\Foundation\Http\FormRequest;

class PostPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transactionId' => ['nullable', 'string', 'max:128'],
            'mayaTransactionId' => ['nullable', 'string', 'max:128'],
            'billerCode' => ['nullable', 'string', 'max:64'],
            'accountNumber' => ['nullable', 'string', 'max:128'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
