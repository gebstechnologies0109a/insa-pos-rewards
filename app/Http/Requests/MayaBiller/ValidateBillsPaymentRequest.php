<?php

namespace App\Http\Requests\MayaBiller;

use App\Support\MayaBiller\MayaBillerResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ValidateBillsPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:32'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'mobileNo' => ['nullable', 'string', 'max:32'],
            'mobile_no' => ['nullable', 'string', 'max:32'],
            'referenceNo' => ['nullable', 'string', 'max:128'],
            'reference_no' => ['nullable', 'string', 'max:128'],
            'billExpiry' => ['nullable', 'date'],
            'bill_expiry' => ['nullable', 'date'],
            'referenceExpiry' => ['nullable', 'date'],
            'reference_expiry' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function billerCode(): string
    {
        return trim((string) ($this->input('billerCode') ?? $this->input('biller_code')));
    }

    public function accountNumber(): string
    {
        return trim((string) ($this->input('accountNumber') ?? $this->input('account_number')));
    }

    public function amount(): float
    {
        return (float) $this->input('amount');
    }

    public function mobileNo(): ?string
    {
        $value = $this->input('mobileNo')
            ?? $this->input('mobile_no')
            ?? $this->input('customerPhone')
            ?? $this->input('customer_phone');

        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    public function billExpiry(): ?string
    {
        return $this->input('billExpiry') ?? $this->input('bill_expiry');
    }

    public function referenceExpiry(): ?string
    {
        return $this->input('referenceExpiry') ?? $this->input('reference_expiry');
    }

    public function referenceNo(): ?string
    {
        $value = $this->input('referenceNo') ?? $this->input('reference_no');

        return $value !== null && $value !== '' ? trim((string) $value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function billingData(): array
    {
        $data = $this->input('data');

        return is_array($data) ? $data : [];
    }

    protected function failedValidation(Validator $validator): void
    {
        $failed = array_keys($validator->failed());
        $accountFields = ['accountNumber', 'account_number', 'billerCode', 'biller_code'];

        if (count(array_intersect($failed, $accountFields)) > 0) {
            throw new HttpResponseException(
                MayaBillerResponse::error('2559', 'Account Number is invalid')
            );
        }

        $message = match (true) {
            in_array('amount', $failed, true) => 'Amount is invalid',
            in_array('mobileNo', $failed, true),
            in_array('mobile_no', $failed, true),
            in_array('customerPhone', $failed, true),
            in_array('customer_phone', $failed, true) => 'MobileNo is invalid / required',
            default => 'Billing data does not exist',
        };

        throw new HttpResponseException(
            MayaBillerResponse::error('2596', $message)
        );
    }
}
