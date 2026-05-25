<?php

namespace Tests\Feature\POS;

use App\Models\Inventory\StockMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function stockInPayload(): array
    {
        return [
            'branch_id'     => 1,
            'user_id'       => 1,
            'supplier_name' => 'Test Supplier',
            'items'         => [
                ['product_id' => 1, 'product_name' => 'Coke Mismo', 'qty' => 50, 'cost' => 15],
                ['product_id' => 2, 'product_name' => 'Piattos', 'qty' => 30, 'cost' => 12],
            ],
        ];
    }

    protected function salePayload(): array
    {
        return [
            'branch_id'       => 1,
            'cashier_id'      => 10,
            'payment_method'  => 'cash',
            'amount_tendered' => 500,
            'items'           => [
                ['product_id' => 1, 'product_name' => 'Coke Mismo', 'qty' => 5, 'price' => 25, 'discount' => 0],
                ['product_id' => 2, 'product_name' => 'Piattos', 'qty' => 3, 'price' => 18, 'discount' => 0],
            ],
        ];
    }

    public function test_stock_in_creates_positive_movements(): void
    {
        $this->postJson('/api/pos/stock-in', $this->stockInPayload())
            ->assertCreated();

        $this->assertDatabaseHas('stock_movements', [
            'branch_id'  => 1,
            'product_id' => 1,
            'type'       => 'stock_in',
            'qty'        => 50.000,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'branch_id'  => 1,
            'product_id' => 2,
            'type'       => 'stock_in',
            'qty'        => 30.000,
        ]);
    }

    public function test_sale_creates_negative_movements(): void
    {
        $this->postJson('/api/pos/stock-in', $this->stockInPayload());
        $this->postJson('/api/pos/sales', $this->salePayload())
            ->assertCreated();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => 1,
            'type'       => 'sale',
            'qty'        => -5.000,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => 2,
            'type'       => 'sale',
            'qty'        => -3.000,
        ]);
    }

    public function test_inventory_service_computes_stock_on_hand(): void
    {
        $this->postJson('/api/pos/stock-in', $this->stockInPayload());
        $this->postJson('/api/pos/sales', $this->salePayload());

        $service = app(InventoryService::class);

        // 50 in - 5 sold = 45
        $this->assertEquals(45, $service->getStockOnHand(1, 1));
        // 30 in - 3 sold = 27
        $this->assertEquals(27, $service->getStockOnHand(1, 2));
    }

    public function test_stock_on_hand_zero_for_unknown_product(): void
    {
        $service = app(InventoryService::class);

        $this->assertEquals(0, $service->getStockOnHand(1, 999));
    }

    public function test_insufficient_stock_blocks_sale(): void
    {
        $response = $this->postJson('/api/pos/sales', $this->salePayload());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_stock_movements_reference_sale_number(): void
    {
        $this->postJson('/api/pos/stock-in', $this->stockInPayload());

        $response = $this->postJson('/api/pos/sales', $this->salePayload());
        $saleNumber = $response->json('sale.sale_number');

        $this->assertDatabaseHas('stock_movements', [
            'type'             => 'sale',
            'reference_number' => $saleNumber,
        ]);
    }

    public function test_stock_movements_reference_stock_in_number(): void
    {
        $response = $this->postJson('/api/pos/stock-in', $this->stockInPayload());
        $stockInNumber = $response->json('stock_in.stock_in_number');

        $this->assertDatabaseHas('stock_movements', [
            'type'             => 'stock_in',
            'reference_number' => $stockInNumber,
        ]);
    }

    public function test_stock_movements_api_endpoint(): void
    {
        $this->postJson('/api/pos/stock-in', $this->stockInPayload());
        $this->postJson('/api/pos/sales', $this->salePayload());

        $response = $this->getJson('/api/pos/stock-movements/1');

        $response->assertOk()
            ->assertJsonCount(2); // 1 stock_in + 1 sale
    }

    public function test_multiple_stock_ins_accumulate(): void
    {
        $this->postJson('/api/pos/stock-in', $this->stockInPayload());
        $this->postJson('/api/pos/stock-in', $this->stockInPayload());

        $service = app(InventoryService::class);

        $this->assertEquals(100, $service->getStockOnHand(1, 1));
        $this->assertEquals(60, $service->getStockOnHand(1, 2));
    }
}
