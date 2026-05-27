<?php

namespace Tests\Feature\POS;

use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
    }

    protected function seedStock(): void
    {
        $this->postJson('/api/pos/stock-in', [
            'branch_id'     => 1,
            'user_id'       => $this->posUser->id,
            'supplier_name' => 'Test Supplier',
            'items'         => [
                ['product_id' => 1, 'product_name' => 'Coke Mismo 300ml', 'qty' => 100, 'cost' => 15],
                ['product_id' => 2, 'product_name' => 'Piattos Cheese', 'qty' => 100, 'cost' => 12],
            ],
        ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'branch_id'       => 1,
            'cashier_id'      => 10,
            'member_id'       => 1023,
            'payment_method'  => 'cash',
            'amount_tendered' => 500,
            'items'           => [
                [
                    'product_id'   => 1,
                    'product_name' => 'Coke Mismo 300ml',
                    'sku'          => 'COKE-300',
                    'barcode'      => '4801234567890',
                    'qty'          => 2,
                    'price'        => 25,
                    'discount'     => 0,
                ],
                [
                    'product_id'   => 2,
                    'product_name' => 'Piattos Cheese',
                    'sku'          => 'PIA-CHEESE',
                    'barcode'      => '4809876543210',
                    'qty'          => 1,
                    'price'        => 18,
                    'discount'     => 3,
                ],
            ],
        ], $overrides);
    }

    public function test_store_sale_successfully(): void
    {
        $this->seedStock();

        $response = $this->postJson('/api/pos/sales', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sale.branch_id', 1)
            ->assertJsonPath('sale.cashier_id', 10)
            ->assertJsonPath('sale.member_id', 1023)
            ->assertJsonPath('sale.payment_method', 'cash')
            ->assertJsonPath('sale.status', 'completed');
    }

    public function test_sale_computes_totals_correctly(): void
    {
        $this->seedStock();

        $response = $this->postJson('/api/pos/sales', $this->validPayload());

        $response->assertCreated();

        $sale = $response->json('sale');

        $this->assertEquals(68, (float) $sale['subtotal']);
        $this->assertEquals(3, (float) $sale['discount_total']);
        $this->assertEquals(65, (float) $sale['total']);
        $this->assertEquals(435, (float) $sale['change_due']);
    }

    public function test_sale_items_are_persisted(): void
    {
        $this->seedStock();

        $response = $this->postJson('/api/pos/sales', $this->validPayload());

        $response->assertCreated()
            ->assertJsonCount(2, 'sale.items');

        $items = $response->json('sale.items');

        $this->assertEquals('Coke Mismo 300ml', $items[0]['product_name']);
        $this->assertEquals(50, (float) $items[0]['line_total']);
        $this->assertEquals('Piattos Cheese', $items[1]['product_name']);
        $this->assertEquals(15, (float) $items[1]['line_total']);
    }

    public function test_sale_number_is_generated(): void
    {
        $this->seedStock();

        $response = $this->postJson('/api/pos/sales', $this->validPayload());

        $saleNumber = $response->json('sale.sale_number');

        $this->assertStringStartsWith('S', $saleNumber);
        $this->assertGreaterThanOrEqual(18, strlen($saleNumber));
    }

    public function test_sale_without_member_id(): void
    {
        $this->seedStock();

        $payload = $this->validPayload(['member_id' => null]);

        $response = $this->postJson('/api/pos/sales', $payload);

        $response->assertCreated()
            ->assertJsonPath('sale.member_id', null);
    }

    public function test_sale_with_gcash_payment(): void
    {
        $this->seedStock();

        $payload = $this->validPayload([
            'payment_method'  => 'gcash',
            'amount_tendered' => 65,
        ]);

        $response = $this->postJson('/api/pos/sales', $payload);

        $response->assertCreated()
            ->assertJsonPath('sale.payment_method', 'gcash');

        $this->assertEquals(0, (float) $response->json('sale.change_due'));
    }

    public function test_validation_rejects_missing_items(): void
    {
        $payload = $this->validPayload();
        unset($payload['items']);

        $response = $this->postJson('/api/pos/sales', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_validation_rejects_empty_items(): void
    {
        $payload = $this->validPayload(['items' => []]);

        $response = $this->postJson('/api/pos/sales', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_validation_rejects_invalid_payment_method(): void
    {
        $payload = $this->validPayload(['payment_method' => 'bitcoin']);

        $response = $this->postJson('/api/pos/sales', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/pos/sales', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'branch_id',
                'cashier_id',
                'payment_method',
                'amount_tendered',
                'items',
            ]);
    }

    public function test_validation_rejects_item_without_product_name(): void
    {
        $payload = $this->validPayload([
            'items' => [
                [
                    'product_id' => 1,
                    'qty'        => 1,
                    'price'      => 10,
                ],
            ],
        ]);

        $response = $this->postJson('/api/pos/sales', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.product_name']);
    }

    public function test_sale_persisted_in_database(): void
    {
        $this->seedStock();

        $this->postJson('/api/pos/sales', $this->validPayload())
            ->assertCreated();

        $this->assertDatabaseCount('pos_sales', 1);
        $this->assertDatabaseCount('pos_sale_items', 2);
        $this->assertDatabaseHas('pos_sales', [
            'branch_id'  => 1,
            'cashier_id' => 10,
            'status'     => 'completed',
        ]);
    }

    public function test_sold_at_timestamp_is_set(): void
    {
        $this->seedStock();

        $response = $this->postJson('/api/pos/sales', $this->validPayload());

        $response->assertCreated();

        $this->assertNotNull($response->json('sale.sold_at'));
    }

    public function test_insufficient_stock_returns_422(): void
    {
        $response = $this->postJson('/api/pos/sales', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient stock for product ID 1');
    }

    public function test_sale_creates_negative_stock_movements(): void
    {
        $this->seedStock();

        $this->postJson('/api/pos/sales', $this->validPayload())
            ->assertCreated();

        $this->assertDatabaseHas('stock_movements', [
            'branch_id'  => 1,
            'product_id' => 1,
            'type'       => 'sale',
            'qty'        => -2.000,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'branch_id'  => 1,
            'product_id' => 2,
            'type'       => 'sale',
            'qty'        => -1.000,
        ]);
    }

    public function test_stock_on_hand_decreases_after_sale(): void
    {
        $this->seedStock();

        $this->postJson('/api/pos/sales', $this->validPayload())
            ->assertCreated();

        $stockProduct1 = StockMovement::where('branch_id', 1)
            ->where('product_id', 1)
            ->sum('qty');

        // 100 stocked in - 2 sold = 98
        $this->assertEquals(98, (float) $stockProduct1);
    }
}
