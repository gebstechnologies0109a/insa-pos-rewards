<?php

namespace Tests\Feature\POS;

use App\Models\POS\Category;
use App\Models\POS\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_product_list_page_loads(): void
    {
        Product::create(['name' => 'Test Product', 'price' => 99.99]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Test Product')
            ->assertSee('99.99');
    }

    public function test_create_product_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Add Product');
    }

    public function test_store_product(): void
    {
        $category = Category::create(['name' => 'Beverages']);

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name'        => 'Coffee',
                'sku'         => 'BEV-001',
                'barcode'     => '1234567890',
                'price'       => 150.00,
                'category_id' => $category->id,
                'active'      => true,
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'name'    => 'Coffee',
            'sku'     => 'BEV-001',
            'barcode' => '1234567890',
            'price'   => 150.00,
        ]);
    }

    public function test_update_product(): void
    {
        $product = Product::create(['name' => 'Old Name', 'price' => 50]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name'  => 'New Name',
                'price' => 75,
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name', 'price' => 75]);
    }

    public function test_deactivate_product(): void
    {
        $product = Product::create(['name' => 'To Deactivate', 'price' => 50, 'active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'active' => false]);
    }

    public function test_product_search_api_by_name(): void
    {
        Product::create(['name' => 'Apple Juice', 'price' => 30, 'active' => true]);
        Product::create(['name' => 'Orange Juice', 'price' => 35, 'active' => true]);
        Product::create(['name' => 'Bread', 'price' => 50, 'active' => true]);

        $response = $this->getJson('/api/pos/products/search?q=Juice');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_product_search_api_by_barcode(): void
    {
        Product::create(['name' => 'Barcode Item', 'price' => 25, 'barcode' => '9999888877', 'active' => true]);

        $response = $this->getJson('/api/pos/products/search?q=9999888877');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Barcode Item');
    }

    public function test_product_search_api_by_sku(): void
    {
        Product::create(['name' => 'SKU Item', 'price' => 15, 'sku' => 'SKU-123', 'active' => true]);

        $response = $this->getJson('/api/pos/products/search?q=SKU-123');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'SKU Item');
    }

    public function test_inactive_products_excluded_from_search(): void
    {
        Product::create(['name' => 'Active Product', 'price' => 10, 'active' => true]);
        Product::create(['name' => 'Inactive Product', 'price' => 10, 'active' => false]);

        $response = $this->getJson('/api/pos/products/search?q=Product');

        $response->assertOk()->assertJsonCount(1);
        $this->assertEquals('Active Product', $response->json('0.name'));
    }

    public function test_product_search_returns_empty_for_no_match(): void
    {
        $this->getJson('/api/pos/products/search?q=nonexistent')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_product_list_filter_by_category(): void
    {
        $cat = Category::create(['name' => 'Food']);
        Product::create(['name' => 'Rice', 'price' => 50, 'category_id' => $cat->id]);
        Product::create(['name' => 'Water', 'price' => 20]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['category' => $cat->id]))
            ->assertOk()
            ->assertSee('Rice')
            ->assertDontSee('Water');
    }

    public function test_product_list_search_filter(): void
    {
        Product::create(['name' => 'Alpha Product', 'price' => 10]);
        Product::create(['name' => 'Beta Product', 'price' => 20]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Product')
            ->assertDontSee('Beta Product');
    }

    public function test_sku_must_be_unique(): void
    {
        Product::create(['name' => 'First', 'price' => 10, 'sku' => 'UNIQUE-SKU']);

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name'  => 'Second',
                'price' => 20,
                'sku'   => 'UNIQUE-SKU',
            ])
            ->assertSessionHasErrors('sku');
    }
}
