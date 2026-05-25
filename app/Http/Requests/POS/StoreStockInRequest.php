<?php

namespace App\Http\Requests\POS;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockInRequest extends FormRequest
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
            'user_id'              => 'required|integer',
            'supplier_name'        => 'nullable|string',

            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.product_name' => 'required|string',
            'items.*.sku'          => 'nullable|string',
            'items.*.qty'          => 'required|numeric|min:0.001',
            'items.*.cost'         => 'required|numeric|min:0',
        ];
    }
}
