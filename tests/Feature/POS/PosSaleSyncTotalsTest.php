<?php

namespace Tests\Feature\POS;

use App\Models\POS\PosSale;
use App\Services\POS\PosSaleTotalsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosSaleSyncTotalsTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
        $this->seedStock();
    }

    protected function seedStock(): void
    {
        $this->postJson('/api/pos/stock-in', [
            'branch_id'     => 1,
            'user_id'       => $this->posUser->id,
            'supplier_name' => 'Test Supplier',
            'items'         => [
                ['product_id' => 1, 'product_name' => 'Coke Mismo 300ml', 'qty' => 2000, 'cost' => 15],
            ],
        ]);
    }

    public function test_sync_push_preserves_register_total_with_order_discount(): void
    {
        $localId = 'test-local-15000-order-disc';
        $unitPrice = 25.0;

        $payload = [
            'local_id'        => $localId,
            'branch_id'       => 1,
            'cashier_id'      => 10,
            'payment_method'  => 'cash',
            'amount_tendered' => 15000,
            'subtotal'        => 15000,
            'discount_total'  => 0,
            'order_discount'  => 0,
            'total'           => 15000,
            'items'           => [
                [
                    'product_id'   => 1,
                    'product_name' => 'Coke Mismo 300ml',
                    'qty'          => 600,
                    'price'        => $unitPrice,
                    'discount'     => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/pos/sync/push', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $sale = PosSale::where('local_id', $localId)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(15000, (float) $sale->total);
        $this->assertEquals(15000, (float) $sale->subtotal);
        $this->assertEquals(0, (float) $sale->discount_total);

        $check = app(PosSaleTotalsResolver::class)->checkSale($sale);
        $this->assertTrue($check['consistent'], implode('; ', $check['messages']));

        $receipt = $this->getJson('/api/pos/sales/' . $sale->id . '/receipt');
        $receipt->assertOk()->assertJsonPath('receipt.total', 15000);
    }

    public function test_sync_push_applies_order_discount_from_payload(): void
    {
        $localId = 'test-local-order-disc-11031';

        $response = $this->postJson('/api/pos/sync/push', [
            'local_id'        => $localId,
            'branch_id'       => 1,
            'cashier_id'      => 10,
            'payment_method'  => 'cash',
            'amount_tendered' => 11031.86,
            'subtotal'        => 15000,
            'discount_total'  => 3968.14,
            'order_discount'  => 3968.14,
            'total'           => 11031.86,
            'items'           => [
                [
                    'product_id'   => 1,
                    'product_name' => 'Coke Mismo 300ml',
                    'qty'          => 600,
                    'price'        => 25,
                    'discount'     => 0,
                ],
            ],
        ]);

        $response->assertCreated();

        $sale = PosSale::where('local_id', $localId)->first();
        $this->assertEquals(11031.86, (float) $sale->total);
        $this->assertEquals(3968.14, (float) $sale->discount_total);
    }

    public function test_api_sale_uses_register_total_when_provided(): void
    {
        $response = $this->postJson('/api/pos/sales', [
            'branch_id'       => 1,
            'cashier_id'      => 10,
            'payment_method'  => 'cash',
            'amount_tendered' => 15000,
            'subtotal'        => 15000,
            'discount_total'  => 3968.14,
            'order_discount'  => 3968.14,
            'total'           => 15000,
            'items'           => [
                [
                    'product_id'   => 1,
                    'product_name' => 'Coke Mismo 300ml',
                    'qty'          => 600,
                    'price'        => 25,
                    'discount'     => 0,
                ],
            ],
        ]);

        $response->assertCreated();
        $saleId = $response->json('sale.id');

        $this->assertEquals(15000, (float) $response->json('sale.total'));

        $this->getJson('/api/pos/sales/' . $saleId . '/receipt')
            ->assertOk()
            ->assertJsonPath('receipt.total', 15000);
    }
}
