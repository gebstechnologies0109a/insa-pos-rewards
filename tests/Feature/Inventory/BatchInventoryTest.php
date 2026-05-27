<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\ExpiryAlert;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\StockMovement;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class BatchInventoryTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected Branch $branch;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
        $this->branch = Branch::find(1);
        $this->product = Product::first();
    }

    public function test_fefo_stock_out_consumes_earliest_expiry_first(): void
    {
        $service = app(InventoryService::class);

        $service->stockIn($this->branch->id, [
            ['product_id' => $this->product->id, 'qty' => 10, 'expiry_date' => now()->addDays(30)->toDateString()],
            ['product_id' => $this->product->id, 'qty' => 10, 'expiry_date' => now()->addDays(5)->toDateString()],
        ], 'stock_in', 1, 'SI-1');

        $service->stockOut(
            branchId: $this->branch->id,
            productId: $this->product->id,
            qty: 8,
            type: 'sale',
            referenceNumber: 'S-1',
        );

        $soonBatch = InventoryBatch::where('product_id', $this->product->id)
            ->whereDate('expiry_date', now()->addDays(5)->toDateString())
            ->first();

        $laterBatch = InventoryBatch::where('product_id', $this->product->id)
            ->whereDate('expiry_date', now()->addDays(30)->toDateString())
            ->first();

        $this->assertEquals(2, (float) $soonBatch->quantity);
        $this->assertEquals(10, (float) $laterBatch->quantity);
    }

    public function test_stock_in_and_sale_create_movements_with_user_and_batch(): void
    {
        $this->postJson('/api/pos/stock-in', [
            'branch_id'     => 1,
            'user_id'       => $this->posUser->id,
            'supplier_name' => 'Supplier',
            'items'         => [
                ['product_id' => $this->product->id, 'product_name' => $this->product->name, 'qty' => 20, 'cost' => 10],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_batches', [
            'branch_id'  => 1,
            'product_id' => $this->product->id,
            'quantity'   => 20,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'type'       => 'stock_in',
            'user_id'    => $this->posUser->id,
            'product_id' => $this->product->id,
        ]);

        $this->postJson('/api/pos/sales', [
            'branch_id'       => 1,
            'cashier_id'      => $this->posUser->id,
            'payment_method'  => 'cash',
            'amount_tendered' => 500,
            'items'           => [
                ['product_id' => $this->product->id, 'product_name' => $this->product->name, 'qty' => 3, 'price' => 25, 'discount' => 0],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('stock_movements', [
            'type'       => 'sale',
            'user_id'    => $this->posUser->id,
            'qty'        => -3,
        ]);
    }

    public function test_expiry_scan_is_idempotent(): void
    {
        InventoryBatch::create([
            'branch_id'   => $this->branch->id,
            'product_id'  => $this->product->id,
            'quantity'    => 5,
            'expiry_date' => now()->addDays(3),
            'received_at' => now(),
        ]);

        $this->artisan('inventory:scan-expiry')->assertSuccessful();
        $this->artisan('inventory:scan-expiry')->assertSuccessful();

        $this->assertEquals(
            1,
            ExpiryAlert::where('product_id', $this->product->id)
                ->where('alert_type', ExpiryAlert::TYPE_SEVEN_DAY)
                ->count()
        );
    }

    public function test_adjustment_api_updates_stock(): void
    {
        app(InventoryService::class)->stockIn($this->branch->id, [
            ['product_id' => $this->product->id, 'qty' => 10],
        ]);

        $this->postJson('/api/pos/inventory/adjustments', [
            'branch_id'  => 1,
            'product_id' => $this->product->id,
            'direction'  => 'out',
            'qty'        => 4,
            'reason'     => 'Damaged units',
        ])->assertOk()
            ->assertJsonPath('stock_on_hand', 6);

        $this->assertDatabaseHas('stock_movements', [
            'type'   => 'adjustment',
            'reason' => 'Damaged units',
        ]);
    }

    public function test_pos_inventory_batches_api_is_branch_scoped(): void
    {
        app(InventoryService::class)->stockIn($this->branch->id, [
            ['product_id' => $this->product->id, 'qty' => 6, 'expiry_date' => now()->addDays(10)->toDateString()],
        ]);

        $this->getJson('/api/pos/inventory/batches?branch_id=1&product_id=' . $this->product->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'batches');
    }

    public function test_legacy_movement_stock_when_no_batches(): void
    {
        StockMovement::create([
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
            'type'       => 'stock_in',
            'qty'        => 15,
        ]);

        $service = app(InventoryService::class);
        $this->assertEquals(15, $service->getStockOnHand($this->branch->id, $this->product->id));
    }
}
