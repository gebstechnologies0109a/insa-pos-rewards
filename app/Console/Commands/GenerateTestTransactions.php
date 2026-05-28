<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTestTransactions extends Command
{
    protected $signature = 'test:generate-transactions {--tag=TEST-001} {--batch=200} {--branch=}';
    protected $description = 'Generate test POS sales for branch testing (batch-safe)';

    public function handle(): int
    {
        $tag   = $this->option('tag');
        $batch = (int) $this->option('batch');

        $branchQuery = DB::table('branches');
        if ($branchId = $this->option('branch')) {
            $branchQuery->where('id', (int) $branchId);
        }
        $branch = $branchQuery->first();
        if (! $branch) {
            $this->error('Branch not found.');

            return 1;
        }
        $user   = DB::table('users')->where('branch_id', $branch->id)->first();

        $existingSaleCustomers = DB::table('pos_sales')
            ->where('sale_number', 'like', "{$tag}-%")
            ->pluck('member_id')
            ->toArray();

        $pendingCustomers = DB::table('customers')
            ->where('last_name', 'Test')
            ->whereNotIn('id', $existingSaleCustomers)
            ->limit($batch)
            ->pluck('id')
            ->toArray();

        $total = DB::table('customers')
            ->where('last_name', 'Test')
            ->whereNotIn('id', $existingSaleCustomers)
            ->count();

        $this->info("Tag: {$tag} | Batch: {$batch} | Pending: {$total} | Processing: " . count($pendingCustomers));

        if (empty($pendingCustomers)) {
            $this->info('All test customers already have transactions!');
            return 0;
        }

        $products = DB::table('products')
            ->where('active', true)
            ->select('id', 'name', 'sku', 'barcode', 'price')
            ->get()
            ->all();
        $prodCount = count($products);

        $shift = DB::table('pos_shifts')
            ->where('branch_id', $branch->id)
            ->orderByDesc('id')
            ->first();

        $shiftId = $shift ? $shift->id : DB::table('pos_shifts')->insertGetId([
            'branch_id'    => $branch->id,
            'user_id'      => $user->id,
            'opened_at'    => Carbon::now()->subHours(8),
            'opening_cash' => 5000,
            'status'       => 'closed',
            'closed_at'    => Carbon::now(),
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        $saleSeq    = DB::table('pos_sales')->max('id') ?? 0;
        $startDate  = Carbon::now()->subDays(90);
        $endDate    = Carbon::now();
        $payments   = ['cash', 'cash', 'cash', 'cash', 'gcash', 'gcash', 'card'];
        $revenue    = 0;

        $bar = $this->output->createProgressBar(count($pendingCustomers));

        foreach ($pendingCustomers as $custId) {
            $saleSeq++;
            $target   = rand(200000, 1000000) / 100;
            $saleDate = Carbon::createFromTimestamp(rand($startDate->timestamp, $endDate->timestamp));
            $saleNum  = $tag . '-' . str_pad($saleSeq, 8, '0', STR_PAD_LEFT);
            $payment  = $payments[array_rand($payments)];

            $items    = [];
            $subtotal = 0;

            while ($subtotal < $target) {
                $p   = $products[rand(0, $prodCount - 1)];
                $qty = rand(1, 5);
                $lt  = round($p->price * $qty, 2);
                if ($subtotal + $lt > $target * 1.2 && $subtotal > 0) break;

                $items[] = [
                    'product_id' => $p->id, 'product_name' => $p->name,
                    'sku' => $p->sku, 'barcode' => $p->barcode,
                    'qty' => $qty, 'price' => $p->price, 'discount' => 0, 'line_total' => $lt,
                    'created_at' => $saleDate, 'updated_at' => $saleDate,
                ];
                $subtotal += $lt;
            }

            $subtotal = round($subtotal, 2);
            $tendered = $payment === 'cash' ? (float)(ceil($subtotal / 50) * 50) : $subtotal;

            $saleId = DB::table('pos_sales')->insertGetId([
                'sale_number'     => $saleNum,
                'branch_id'       => $branch->id,
                'cashier_id'      => $user->id,
                'shift_id'        => $shiftId,
                'member_id'       => $custId,
                'subtotal'        => $subtotal,
                'discount_total'  => 0,
                'total'           => $subtotal,
                'payment_method'  => $payment,
                'amount_tendered' => $tendered,
                'change_due'      => round($tendered - $subtotal, 2),
                'status'          => 'completed',
                'sold_at'         => $saleDate,
                'created_at'      => $saleDate,
                'updated_at'      => $saleDate,
            ]);

            foreach ($items as &$item) $item['sale_id'] = $saleId;
            DB::table('pos_sale_items')->insert($items);

            $stockMoves = [];
            foreach ($items as $item) {
                $stockMoves[] = [
                    'branch_id' => $branch->id, 'product_id' => $item['product_id'],
                    'type' => 'sale', 'qty' => -$item['qty'],
                    'reference_number' => $saleNum,
                    'created_at' => $saleDate, 'updated_at' => $saleDate,
                ];
            }
            DB::table('stock_movements')->insert($stockMoves);

            $revenue += $subtotal;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Batch complete: " . count($pendingCustomers) . " transactions | P" . number_format($revenue, 2));
        $this->info("Remaining: " . ($total - count($pendingCustomers)));

        if ($total - count($pendingCustomers) > 0) {
            $this->warn("Run again to process next batch.");
        } else {
            $this->info("ALL DONE — all test customers have transactions!");
        }

        return 0;
    }
}
