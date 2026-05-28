<?php

namespace Tests\Feature\POS;

use App\Models\POS\PosSale;
use App\Models\POS\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosSyncIdempotencyTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
    }

    public function test_sync_push_is_idempotent_by_local_id(): void
    {
        $this->postJson('/api/pos/stock-in', [
            'branch_id'     => 1,
            'user_id'       => $this->posUser->id,
            'supplier_name' => 'Test Supplier',
            'items'         => [
                ['product_id' => 1, 'product_name' => 'Coke Mismo', 'qty' => 100, 'cost' => 15],
            ],
        ])->assertCreated();

        $product = Product::find(1);
        $localId = (string) Str::uuid();

        $payload = [
            'local_id'        => $localId,
            'branch_id'       => 1,
            'cashier_id'      => $this->posUser->id,
            'payment_method'  => 'cash',
            'amount_tendered' => 100,
            'items'           => [[
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'qty'          => 1,
                'price'        => (float) $product->price,
            ]],
        ];

        $first = $this->postJson('/api/pos/sync/push', $payload);
        $first->assertCreated()
            ->assertJsonPath('success', true);

        $serverId = $first->json('server_id');

        $second = $this->postJson('/api/pos/sync/push', $payload);
        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('server_id', $serverId);

        $this->assertSame(1, PosSale::where('local_id', $localId)->count());
    }
}
