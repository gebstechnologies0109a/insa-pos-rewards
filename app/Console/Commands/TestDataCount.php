<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDataCount extends Command
{
    protected $signature = 'test:count {--tag=TEST-001}';
    protected $description = 'Count test data records';

    public function handle(): int
    {
        $tag = $this->option('tag');
        $this->info('Products: ' . DB::table('products')->count());
        $this->info('Categories: ' . DB::table('categories')->count());
        $this->info('Customers (all): ' . DB::table('customers')->count());
        $this->info('Customers (Test): ' . DB::table('customers')->where('last_name', 'Test')->count());
        $this->info('Sales (all): ' . DB::table('pos_sales')->count());
        $this->info("Sales ({$tag}): " . DB::table('pos_sales')->where('sale_number', 'like', "{$tag}-%")->count());
        $this->info('Sale items: ' . DB::table('pos_sale_items')->count());
        $this->info("Revenue ({$tag}): P" . number_format(
            DB::table('pos_sales')->where('sale_number', 'like', "{$tag}-%")->sum('total'), 2
        ));
        return 0;
    }
}
