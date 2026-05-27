<?php

namespace App\Console\Commands;

use App\Models\EPayPlus\Retailer;
use Illuminate\Console\Command;

class SyncRetailerDualWallets extends Command
{
    protected $signature = 'epay:sync-dual-wallets
                            {--dry-run : List retailers that would be updated without saving}';

    protected $description = 'Split legacy retailer balance into E-Load (70%) and Bills/Cash-In (30%) wallets';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $candidates = Retailer::query()
                ->where('balance', '>', 0)
                ->orderBy('account_id')
                ->get()
                ->filter(fn (Retailer $r) => $r->needsDualWalletSplit());

            if ($candidates->isEmpty()) {
                $this->info('No retailers need dual-wallet split.');

                return self::SUCCESS;
            }

            $this->table(
                ['account_id', 'balance', 'eload_balance', 'bills_balance', '→ eload', '→ bills'],
                $candidates->map(function (Retailer $retailer) {
                    $split = Retailer::splitBalanceFromTotal((float) $retailer->balance);

                    return [
                        $retailer->account_id,
                        $retailer->balance,
                        $retailer->eload_balance,
                        $retailer->bills_balance,
                        $split['eload'],
                        $split['bills'],
                    ];
                })->all()
            );

            $this->info("{$candidates->count()} retailer(s) would be updated. Run without --dry-run to apply.");

            return self::SUCCESS;
        }

        $updated = Retailer::syncAllDualWallets();
        $this->info("Dual-wallet split applied to {$updated} retailer(s).");

        return self::SUCCESS;
    }
}
