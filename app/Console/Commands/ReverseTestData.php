<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReverseTestData extends Command
{
    protected $signature = 'test:reverse-data {--tag=TEST-001} {--confirm}';
    protected $description = 'Reverse/delete test data by tag (customers, sales, stock movements)';

    public function handle(): int
    {
        $tag = $this->option('tag');

        $sales     = DB::table('pos_sales')->where('sale_number', 'like', "{$tag}-%")->count();
        $customers = DB::table('customers')->where('last_name', 'Test')->count();
        $items     = DB::table('pos_sale_items')
            ->whereIn('sale_id', DB::table('pos_sales')->where('sale_number', 'like', "{$tag}-%")->pluck('id'))
            ->count();
        $stock     = DB::table('stock_movements')->where('reference_number', 'like', "{$tag}-%")->count();

        $this->info("Tag: {$tag}");
        $this->info("Sales to delete:          {$sales}");
        $this->info("Sale items to delete:     {$items}");
        $this->info("Stock movements to delete: {$stock}");
        $this->info("Test customers to delete:  {$customers}");

        if (!$this->option('confirm')) {
            $this->warn('Add --confirm to actually delete. This is a preview.');
            return 0;
        }

        if (!$this->confirm("Are you sure you want to delete all {$tag} test data?")) {
            return 0;
        }

        $saleIds = DB::table('pos_sales')->where('sale_number', 'like', "{$tag}-%")->pluck('id');

        DB::table('pos_sale_items')->whereIn('sale_id', $saleIds)->delete();
        $this->info("Deleted sale items.");

        DB::table('stock_movements')->where('reference_number', 'like', "{$tag}-%")->delete();
        $this->info("Deleted stock movements.");

        DB::table('pos_sales')->where('sale_number', 'like', "{$tag}-%")->delete();
        $this->info("Deleted sales.");

        DB::table('customers')->where('last_name', 'Test')->delete();
        $this->info("Deleted test customers.");

        $this->info("Reversal of {$tag} complete.");
        return 0;
    }
}
