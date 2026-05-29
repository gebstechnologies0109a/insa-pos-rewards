<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\StockMovement;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeDeviceTestSales extends Command
{
    protected $signature = 'insapos:purge-device-test-sales
        {--dry-run : Preview counts only (default when --force is omitted)}
        {--force : Delete matching sales and restore deducted stock}
        {--branch=1 : Branch ID to scope}
        {--since=2026-05-28 : Include device-synced sales created on/after this date}
        {--local-id-only : Match any sale with local_id set (ignores --since)}
        {--reference-pattern= : Extra filter: sale_number LIKE pattern (e.g. S20260528%)}
        {--sale-day= : Purge by sale_number day token only (e.g. 20260528); ignores local_id requirement}
        {--limit=0 : Max sales to process (0 = unlimited)}
        {--chunk=250 : Sales processed per batch}
        {--yes : Skip confirmation prompt (use with --force on servers)}';

    protected $description = 'Remove device-synced test POS sales and linked stock movements; restore FEFO batch qty where applicable';

    public function handle(): int
    {
        $branchId = (int) $this->option('branch');
        $force = (bool) $this->option('force');
        $dryRun = ! $force;
        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($branchId < 1) {
            $this->error('Invalid --branch value.');

            return self::FAILURE;
        }

        $query = $this->matchingSalesQuery($branchId);
        $saleIds = (clone $query)->pluck('id');
        if ($limit > 0) {
            $saleIds = $saleIds->take($limit)->values();
        }

        $salesCount = $saleIds->count();
        $itemsCount = $salesCount > 0
            ? PosSaleItem::whereIn('sale_id', $saleIds)->count()
            : 0;
        $movementsCount = $this->saleMovementCount($saleIds);

        $branchMovementTotal = StockMovement::where('branch_id', $branchId)->count();
        $branchSaleTotal = PosSale::where('branch_id', $branchId)->count();

        $this->info("Branch #{$branchId}");
        $this->info('Selection: ' . $this->describeSelection());
        $this->line('');
        $this->table(
            ['Metric', 'Before purge (branch)', 'Matching test records'],
            [
                ['pos_sales', number_format($branchSaleTotal), number_format($salesCount)],
                ['pos_sale_items', '—', number_format($itemsCount)],
                ['stock_movements (sale-linked)', number_format($branchMovementTotal), number_format($movementsCount)],
            ],
        );

        if ($salesCount === 0) {
            $this->warn('No matching sales found. Nothing to do.');

            return self::SUCCESS;
        }

        $sample = PosSale::whereIn('id', $saleIds->take(5))->get(['id', 'sale_number', 'local_id', 'total', 'created_at']);
        $this->line('');
        $this->info('Sample sales (up to 5):');
        foreach ($sample as $sale) {
            $this->line("  #{$sale->id} {$sale->sale_number} local_id={$sale->local_id} total={$sale->total} @ {$sale->created_at}");
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('Dry run — no changes made. Re-run with --force to purge and restore stock.');
            $this->line('Estimated movements page total after purge: ' . number_format($branchMovementTotal - $movementsCount));

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm("Permanently delete {$salesCount} sales, {$itemsCount} items, {$movementsCount} movements and restore stock?", false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $restoredLines = 0;
        $deletedMovements = 0;
        $deletedItems = 0;
        $deletedSales = 0;

        $saleIdChunks = $saleIds->chunk($chunkSize);

        foreach ($saleIdChunks as $chunk) {
            DB::transaction(function () use ($chunk, &$restoredLines, &$deletedMovements, &$deletedItems, &$deletedSales) {
                $movements = StockMovement::query()
                    ->where('type', 'sale')
                    ->whereIn('reference_id', $chunk)
                    ->orderBy('id')
                    ->get();

                $restoredLines += $this->restoreStockFromMovements($movements);

                $deletedMovements += StockMovement::query()
                    ->where('type', 'sale')
                    ->whereIn('reference_id', $chunk)
                    ->delete();

                $deletedItems += PosSaleItem::whereIn('sale_id', $chunk)->delete();
                $deletedSales += PosSale::whereIn('id', $chunk)->delete();
            });

            $this->output->write('.');
        }

        $this->newLine(2);

        $afterMovements = StockMovement::where('branch_id', $branchId)->count();
        $afterSales = PosSale::where('branch_id', $branchId)->count();

        $this->info("Purged {$deletedSales} sales, {$deletedItems} line items, {$deletedMovements} stock movements.");
        $this->info("Restored stock on {$restoredLines} movement line(s) (FEFO batches incremented where batch_id was set).");
        $this->info('Branch stock_movements after purge: ' . number_format($afterMovements) . " (was {$branchMovementTotal})");
        $this->info('Branch pos_sales after purge: ' . number_format($afterSales) . " (was {$branchSaleTotal})");

        return self::SUCCESS;
    }

    protected function matchingSalesQuery(int $branchId): Builder
    {
        $query = PosSale::query()->where('branch_id', $branchId);

        $saleDay = trim((string) $this->option('sale-day'));
        if ($saleDay !== '') {
            $query->where('sale_number', 'like', '%' . $saleDay . '%');
        } elseif ($this->option('local-id-only')) {
            $query->whereNotNull('local_id');
        } else {
            $since = Carbon::parse((string) $this->option('since'))->startOfDay();
            $query->whereNotNull('local_id')
                ->where(function (Builder $q) use ($since) {
                    $q->where('created_at', '>=', $since)
                        ->orWhere('synced_at', '>=', $since);
                });
        }

        $pattern = trim((string) $this->option('reference-pattern'));
        if ($pattern !== '' && $saleDay === '') {
            $query->where('sale_number', 'like', $pattern);
        }

        return $query->orderBy('id');
    }

    protected function saleMovementCount(Collection $saleIds): int
    {
        if ($saleIds->isEmpty()) {
            return 0;
        }

        return StockMovement::query()
            ->where('type', 'sale')
            ->whereIn('reference_id', $saleIds)
            ->count();
    }

    protected function describeSelection(): string
    {
        $parts = ['branch=' . $this->option('branch')];

        if ($saleDay = trim((string) $this->option('sale-day'))) {
            $parts[] = "sale_number contains {$saleDay}";
        } elseif ($this->option('local-id-only')) {
            $parts[] = 'local_id IS NOT NULL';
        } else {
            $parts[] = 'local_id IS NOT NULL';
            $parts[] = 'created_at OR synced_at >= ' . $this->option('since');
        }

        $pattern = trim((string) $this->option('reference-pattern'));
        if ($pattern !== '') {
            $parts[] = "sale_number LIKE {$pattern}";
        }

        return implode('; ', $parts);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StockMovement>  $movements
     */
    protected function restoreStockFromMovements(Collection $movements): int
    {
        $restored = 0;
        $hasBatchColumn = Schema::hasColumn('stock_movements', 'inventory_batch_id');

        foreach ($movements as $movement) {
            $qty = (float) $movement->qty;
            if ($qty >= -0.0001) {
                continue;
            }

            $restoreQty = abs($qty);
            $batchId = $hasBatchColumn ? $movement->inventory_batch_id : null;

            if ($batchId) {
                InventoryBatch::where('id', $batchId)->increment('quantity', $restoreQty);
                $restored++;

                continue;
            }

            $attributes = [
                'branch_id'          => $movement->branch_id,
                'product_id'         => $movement->product_id,
                'type'               => 'adjustment',
                'qty'                => $restoreQty,
                'reference_id'       => $movement->reference_id,
                'reference_number'   => 'PURGE-RESTORE-' . ($movement->reference_number ?? $movement->reference_id),
                'reason'             => 'insapos:purge-device-test-sales stock restore',
            ];

            if (Schema::hasColumn('stock_movements', 'user_id')) {
                $attributes['user_id'] = $movement->user_id;
            }
            if (Schema::hasColumn('stock_movements', 'shift_id')) {
                $attributes['shift_id'] = $movement->shift_id;
            }

            StockMovement::create(
                array_filter(
                    $attributes,
                    fn ($value, string $column) => Schema::hasColumn('stock_movements', $column),
                    ARRAY_FILTER_USE_BOTH,
                ),
            );

            $restored++;
        }

        return $restored;
    }
}
