<?php

namespace Database\Seeders;

use App\Models\POS\Category;
use App\Models\POS\Product;
use Illuminate\Database\Seeder;

class SampleProductsSeeder extends Seeder
{
    public function run(): void
    {
        $cat1 = Category::firstOrCreate(['name' => 'Beverages']);
        $cat2 = Category::firstOrCreate(['name' => 'Snacks']);
        $cat3 = Category::firstOrCreate(['name' => 'Personal Care']);

        $products = [
            ['name' => 'Coca-Cola 330ml',        'sku' => 'BEV001', 'barcode' => '4800100012345', 'price' => 35.00, 'category_id' => $cat1->id],
            ['name' => 'Sprite 330ml',            'sku' => 'BEV002', 'barcode' => '4800100012346', 'price' => 35.00, 'category_id' => $cat1->id],
            ['name' => 'Mineral Water 500ml',     'sku' => 'BEV003', 'barcode' => '4800100012347', 'price' => 15.00, 'category_id' => $cat1->id],
            ['name' => 'Kopiko Brown 25g',        'sku' => 'BEV004', 'barcode' => '4800100012348', 'price' => 8.00,  'category_id' => $cat1->id],
            ['name' => 'Piattos Cheese 85g',      'sku' => 'SNK001', 'barcode' => '4800100022345', 'price' => 42.00, 'category_id' => $cat2->id],
            ['name' => 'Nova Cheddar 78g',        'sku' => 'SNK002', 'barcode' => '4800100022346', 'price' => 38.00, 'category_id' => $cat2->id],
            ['name' => 'Skyflakes Crackers',      'sku' => 'SNK003', 'barcode' => '4800100022347', 'price' => 12.00, 'category_id' => $cat2->id],
            ['name' => 'Boy Bawang Garlic 100g',  'sku' => 'SNK004', 'barcode' => '4800100022348', 'price' => 25.00, 'category_id' => $cat2->id],
            ['name' => 'Safeguard Soap 135g',     'sku' => 'PC001',  'barcode' => '4800100032345', 'price' => 45.00, 'category_id' => $cat3->id],
            ['name' => 'Colgate Toothpaste 75ml', 'sku' => 'PC002',  'barcode' => '4800100032346', 'price' => 65.00, 'category_id' => $cat3->id],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['sku' => $p['sku']], array_merge($p, ['active' => true]));
        }
    }
}
