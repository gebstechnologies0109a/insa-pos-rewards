<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class InventoryAuditApiTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    public function test_pos_inventory_audit_adjusts_batch_quantity(): void
    {
        $this->authenticatePosApi();
        $branch = Branch::find(1);
        $product = Product::first();

        $batches = app(InventoryService::class)->stockIn($branch->id, [
            ['product_id' => $product->id, 'qty' => 20],
        ]);
        $batch = $batches[0];

        $this->postJson('/api/pos/inventory/audit', [
            'branch_id' => $branch->id,
            'batch_id'  => $batch->id,
            'quantity'  => 17,
            'reason'    => 'Physical count variance',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('batch.quantity', '17.000');

        $this->assertEquals(17, (float) $batch->fresh()->quantity);
    }
}
