<?php

namespace Tests\Feature\POS;

use App\Models\POS\Category;
use App\Models\POS\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosOfflinePrefetchTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
    }

    public function test_products_all_returns_catalog_for_offline_cache(): void
    {
        $response = $this->getJson('/api/pos/products/all?branch_id=1');

        $response->assertOk()
            ->assertJsonStructure(['products', 'categories']);

        $this->assertGreaterThanOrEqual(1, count($response->json('products')));
    }

    public function test_products_all_includes_category_id_for_native_filtering(): void
    {
        $category = Category::create(['name' => 'Cafe Meals']);
        Product::create([
            'name' => 'Rice Bowl',
            'price' => 99,
            'category_id' => $category->id,
            'active' => true,
        ]);

        $response = $this->getJson('/api/pos/products/all?branch_id=1');

        $response->assertOk();

        $product = collect($response->json('products'))
            ->firstWhere('name', 'Rice Bowl');

        $this->assertNotNull($product);
        $this->assertSame($category->id, $product['category_id']);
    }

    public function test_sync_pull_returns_stock_when_branch_id_set(): void
    {
        $this->getJson('/api/pos/sync/pull')
            ->assertOk()
            ->assertJsonStructure(['success', 'products', 'pulled_at']);

        $this->getJson('/api/pos/sync/pull?branch_id=1')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_pos_settings_api_for_offline_cache(): void
    {
        $this->getJson('/api/pos/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['settings']);
    }

    public function test_ping_is_reachable_without_auth(): void
    {
        $this->getJson('/api/pos/ping')
            ->assertOk();
    }
}
