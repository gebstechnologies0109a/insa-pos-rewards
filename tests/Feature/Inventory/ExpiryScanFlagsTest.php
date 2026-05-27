<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\ExpiryAlert;
use App\Models\Inventory\InventoryBatch;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryScanFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Branch::firstOrCreate(['id' => 1], ['name' => 'Main Branch']);
        if (Product::count() === 0) {
            Product::create(['name' => 'Test Product', 'sku' => 'TST-001', 'price' => 10]);
        }
    }

    public function test_scan_does_not_update_handled_alerts(): void
    {
        $branch = Branch::find(1);
        $product = Product::first();

        $batch = InventoryBatch::create([
            'branch_id'   => $branch->id,
            'product_id'  => $product->id,
            'quantity'    => 5,
            'expiry_date' => now()->addDays(3),
            'received_at' => now(),
        ]);

        $alert = ExpiryAlert::create([
            'inventory_batch_id' => $batch->id,
            'branch_id'          => $branch->id,
            'product_id'         => $product->id,
            'alert_type'         => ExpiryAlert::TYPE_SEVEN_DAY,
            'expiry_date'        => $batch->expiry_date,
            'quantity'           => 5,
            'handled_at'         => now(),
        ]);

        $batch->update(['quantity' => 99]);

        $this->artisan('inventory:scan-expiry')->assertSuccessful();

        $this->assertEquals(5, (float) $alert->fresh()->quantity);
    }

    public function test_scan_does_not_update_snoozed_alerts(): void
    {
        $branch = Branch::find(1);
        $product = Product::first();

        $batch = InventoryBatch::create([
            'branch_id'   => $branch->id,
            'product_id'  => $product->id,
            'quantity'    => 8,
            'expiry_date' => now()->addDays(3),
            'received_at' => now(),
        ]);

        $alert = ExpiryAlert::create([
            'inventory_batch_id' => $batch->id,
            'branch_id'          => $branch->id,
            'product_id'         => $product->id,
            'alert_type'         => ExpiryAlert::TYPE_SEVEN_DAY,
            'expiry_date'        => $batch->expiry_date,
            'quantity'           => 8,
            'snoozed_until'      => now()->addDays(14),
        ]);

        $batch->update(['quantity' => 50]);

        $this->artisan('inventory:scan-expiry')->assertSuccessful();

        $this->assertEquals(8, (float) $alert->fresh()->quantity);
    }
}
