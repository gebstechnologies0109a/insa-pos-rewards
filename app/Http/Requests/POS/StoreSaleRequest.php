<?php

namespace App\Http\Requests\POS;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
            'branch_id'            => 'required|integer',
            'shift_id'             => 'nullable|integer',
            'cashier_id'           => 'required|integer',
            'member_id'            => 'nullable|integer',
            'payment_method'       => 'required|string|in:cash,debit_card,credit_card,gcash,maya,palawanpay,other',
            'amount_tendered'      => 'required|numeric|min:0',

            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.product_name' => 'required|string',
            'items.*.sku'          => 'nullable|string',
            'items.*.barcode'      => 'nullable|string',
            'items.*.qty'          => 'required|numeric|min:0.001',
            'items.*.price'        => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
        ];
    }
}
