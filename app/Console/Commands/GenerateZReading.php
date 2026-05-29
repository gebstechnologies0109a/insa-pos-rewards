<?php

namespace App\Console\Commands;

use App\Models\POS\Branch;
use App\Models\User;
use App\Services\ReadingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateZReading extends Command
{
    protected $signature = 'pos:generate-z-reading
                            {branch_id : Branch ID}
                            {--date= : Limit to sales on this date (Y-m-d); generated_at set to end of that day}
                            {--cashier_id= : Cashier user ID on the Z record (defaults to first admin/manager for branch)}';

    protected $description = 'Generate a BIR Z-reading for untagged sales on a branch (backfill or ops)';

    public function handle(ReadingService $readingService): int
    {
        $branchId = (int) $this->argument('branch_id');
        $salesDate = $this->option('date') ?: null;

        if (! Branch::find($branchId)) {
            $this->error("Branch {$branchId} not found.");

            return self::FAILURE;
        }

        $pending = $readingService->countUntaggedSales($branchId, $salesDate);
        if ($pending === 0) {
            $this->warn('No untagged sales to include.');

            return self::SUCCESS;
        }

        $cashierId = $this->option('cashier_id')
            ? (int) $this->option('cashier_id')
            : (int) (User::where('branch_id', $branchId)
                ->whereIn('role', ['admin', 'manager', 'cashier'])
                ->orderByRaw("FIELD(role, 'admin', 'manager', 'cashier')")
                ->value('id') ?? User::whereIn('role', ['admin', 'owner'])->value('id'));

        if (! $cashierId) {
            $this->error('No cashier_id provided and no user found for this branch.');

            return self::FAILURE;
        }

        $generatedAt = $salesDate
            ? Carbon::parse($salesDate, config('app.timezone'))->endOfDay()
            : null;

        $reading = $readingService->generateZReadingForBranch(
            $branchId,
            $cashierId,
            null,
            $generatedAt,
            $salesDate,
        );

        $this->info("Z-Reading #{$reading->z_count} created (id {$reading->id}, {$pending} sales tagged).");

        return self::SUCCESS;
    }
}
