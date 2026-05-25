<?php

namespace Tests\Feature\POS;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'branch_id'     => 1,
            'user_id'       => 10,
            'supplier_name' => 'Coca-Cola FEMSA',
            'items'         => [
                [
                    'product_id'   => 1,
                    'product_name' => 'Coke Mismo 300ml',
                    'sku'          => 'COKE-300',
                    'qty'          => 24,
                    'cost'         => 18.50,
                ],
                [
                    'product_id'   => 2,
                    'product_name' => 'Royal 1L',
                    'sku'          => 'ROYAL-1L',
                    'qty'          => 12,
                    'cost'         => 32.00,
                ],
            ],
        ], $overrides);
    }

    public function test_store_stock_in_successfully(): void
    {
        $response = $this->postJson('/api/pos/stock-in', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stock_in.branch_id', 1)
            ->assertJsonPath('stock_in.user_id', 10)
            ->assertJsonPath('stock_in.supplier_name', 'Coca-Cola FEMSA');
    }

    public function test_stock_in_computes_total_cost(): void
    {
        $response = $this->postJson('/api/pos/stock-in', $this->validPayload());

        $response->assertCreated();

        $stockIn = $response->json('stock_in');

        // (24 * 18.50) + (12 * 32.00) = 444 + 384 = 828
        $this->assertEquals(828, (float) $stockIn['total_cost']);
    }

    public function test_stock_in_items_are_persisted(): void
    {
        $response = $this->postJson('/api/pos/stock-in', $this->validPayload());

        $response->assertCreated()
            ->assertJsonCount(2, 'stock_in.items');

        $items = $response->json('stock_in.items');

        $this->assertEquals('Coke Mismo 300ml', $items[0]['product_name']);
        $this->assertEquals(444, (float) $items[0]['line_total']); // 24 * 18.50
        $this->assertEquals('Royal 1L', $items[1]['product_name']);
        $this->assertEquals(384, (float) $items[1]['line_total']); // 12 * 32.00
    }

    public function test_stock_in_number_is_generated(): void
    {
        $response = $this->postJson('/api/pos/stock-in', $this->validPayload());

        $stockInNumber = $response->json('stock_in.stock_in_number');

        $this->assertStringStartsWith('SI', $stockInNumber);
        $this->assertGreaterThanOrEqual(19, strlen($stockInNumber));
    }

    public function test_stock_in_without_supplier(): void
    {
        $payload = $this->validPayload(['supplier_name' => null]);

        $response = $this->postJson('/api/pos/stock-in', $payload);

        $response->assertCreated()
            ->assertJsonPath('stock_in.supplier_name', null);
    }

    public function test_received_at_is_set(): void
    {
        $response = $this->postJson('/api/pos/stock-in', $this->validPayload());

        $response->assertCreated();
        $this->assertNotNull($response->json('stock_in.received_at'));
    }

    public function test_validation_rejects_missing_items(): void
    {
        $payload = $this->validPayload();
        unset($payload['items']);

        $response = $this->postJson('/api/pos/stock-in', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_validation_rejects_empty_items(): void
    {
        $payload = $this->validPayload(['items' => []]);

        $response = $this->postJson('/api/pos/stock-in', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/pos/stock-in', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id', 'user_id', 'items']);
    }

    public function test_validation_rejects_item_without_product_name(): void
    {
        $payload = $this->validPayload([
            'items' => [
                [
                    'product_id' => 1,
                    'qty'        => 5,
                    'cost'       => 10,
                ],
            ],
        ]);

        $response = $this->postJson('/api/pos/stock-in', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.product_name']);
    }

    public function test_validation_rejects_zero_qty(): void
    {
        $payload = $this->validPayload([
            'items' => [
                [
                    'product_id'   => 1,
                    'product_name' => 'Test',
                    'qty'          => 0,
                    'cost'         => 10,
                ],
            ],
        ]);

        $response = $this->postJson('/api/pos/stock-in', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.qty']);
    }

    public function test_stock_in_persisted_in_database(): void
    {
        $this->postJson('/api/pos/stock-in', $this->validPayload())
            ->assertCreated();

        $this->assertDatabaseCount('stock_ins', 1);
        $this->assertDatabaseCount('stock_in_items', 2);
        $this->assertDatabaseHas('stock_ins', [
            'branch_id'     => 1,
            'user_id'       => 10,
            'supplier_name' => 'Coca-Cola FEMSA',
        ]);
    }
}
