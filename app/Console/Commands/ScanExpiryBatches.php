<?php

namespace App\Console\Commands;

use App\Models\Inventory\ExpiryAlert;
use App\Models\Inventory\InventoryBatch;
use Illuminate\Console\Command;

class ScanExpiryBatches extends Command
{
    protected $signature = 'inventory:scan-expiry';

    protected $description = 'Create idempotent expiry alerts for batches (30-day, 7-day, expired)';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $created = 0;

        $batches = InventoryBatch::withStock()
            ->whereNotNull('expiry_date')
            ->get();

        foreach ($batches as $batch) {
            if ($batch->expiry_date === null) {
                continue;
            }

            $days = $today->diffInDays($batch->expiry_date, false);
            $types = [];

            if ($days < 0) {
                $types[] = ExpiryAlert::TYPE_EXPIRED;
            } elseif ($days <= 7) {
                $types[] = ExpiryAlert::TYPE_SEVEN_DAY;
            } elseif ($days <= 30) {
                $types[] = ExpiryAlert::TYPE_THIRTY_DAY;
            }

            foreach ($types as $type) {
                $alert = ExpiryAlert::firstOrCreate(
                    [
                        'inventory_batch_id' => $batch->id,
                        'alert_type'         => $type,
                    ],
                    [
                        'branch_id'   => $batch->branch_id,
                        'product_id'  => $batch->product_id,
                        'expiry_date' => $batch->expiry_date,
                        'quantity'    => $batch->quantity,
                    ]
                );

                if ($alert->wasRecentlyCreated) {
                    $created++;

                    continue;
                }

                if ($alert->handled_at !== null) {
                    continue;
                }

                if ($alert->snoozed_until !== null && $alert->snoozed_until->isFuture()) {
                    continue;
                }

                $alert->update([
                    'quantity'    => $batch->quantity,
                    'expiry_date' => $batch->expiry_date,
                ]);
            }
        }

        $this->info("Expiry scan complete. {$created} new alert(s).");

        return self::SUCCESS;
    }
}
