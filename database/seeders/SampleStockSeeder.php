<?php

namespace Database\Seeders;

use App\Models\Inventory\StockMovement;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use Illuminate\Database\Seeder;

class SampleStockSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first();
        if (! $branch) {
            return;
        }

        $products = Product::all();

        foreach ($products as $product) {
            StockMovement::firstOrCreate(
                ['product_id' => $product->id, 'branch_id' => $branch->id, 'type' => 'stock_in'],
                [
                    'qty'              => 100,
                    'reference_number' => 'INIT-STOCK',
                ],
            );
        }
    }
}
