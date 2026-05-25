<?php

namespace App\Exports;

use App\Models\POS\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Product::with('category')->orderBy('name')->get();
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'SKU', 'Barcode', 'Price', 'Category', 'Active'];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->barcode,
            $product->price,
            $product->category?->name ?? '',
            $product->active ? 'Yes' : 'No',
        ];
    }
}
