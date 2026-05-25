<?php

namespace App\Imports;

use App\Models\POS\Category;
use App\Models\POS\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $categoryId = null;

        if (! empty($row['category'])) {
            $category = Category::firstOrCreate(['name' => trim($row['category'])]);
            $categoryId = $category->id;
        }

        $active = true;
        if (isset($row['active'])) {
            $active = in_array(strtolower(trim($row['active'])), ['yes', '1', 'true', 'active']);
        }

        $existing = null;
        if (! empty($row['sku'])) {
            $existing = Product::where('sku', trim($row['sku']))->first();
        }
        if (! $existing && ! empty($row['barcode'])) {
            $existing = Product::where('barcode', trim($row['barcode']))->first();
        }

        if ($existing) {
            $existing->update([
                'name'        => trim($row['name']),
                'sku'         => ! empty($row['sku']) ? trim($row['sku']) : $existing->sku,
                'barcode'     => ! empty($row['barcode']) ? trim($row['barcode']) : $existing->barcode,
                'price'       => (float) ($row['price'] ?? $existing->price),
                'category_id' => $categoryId ?? $existing->category_id,
                'active'      => $active,
            ]);

            return null;
        }

        return new Product([
            'name'        => trim($row['name']),
            'sku'         => ! empty($row['sku']) ? trim($row['sku']) : null,
            'barcode'     => ! empty($row['barcode']) ? trim($row['barcode']) : null,
            'price'       => (float) ($row['price'] ?? 0),
            'category_id' => $categoryId,
            'active'      => $active,
        ]);
    }
}
