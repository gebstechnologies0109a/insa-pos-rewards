<?php

namespace Tests\Feature\Stockman;

use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockInPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_page_loads_for_admin_without_branch_id(): void
    {
        Branch::create(['name' => 'Main']);
        Product::create(['name' => 'Test Product', 'sku' => 'TST-1', 'price' => 10, 'active' => true]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'stockin-admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'branch_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('stockman.stock-in'))
            ->assertOk()
            ->assertSee('Stock In')
            ->assertSee('Branch')
            ->assertSee('Scan / search product', false)
            ->assertDontSee('Select product', false);
    }

    public function test_stock_in_page_loads_when_inventory_batches_table_missing(): void
    {
        $branch = Branch::create(['name' => 'Main']);
        Product::create(['name' => 'Test Product', 'sku' => 'TST-2', 'price' => 10, 'active' => true]);

        $stockman = User::create([
            'name' => 'Stockman',
            'email' => 'stockin-sm@test.com',
            'password' => bcrypt('password'),
            'role' => 'stockman',
            'branch_id' => $branch->id,
        ]);

        Schema::dropIfExists('inventory_batches');

        $this->actingAs($stockman)
            ->get(route('stockman.stock-in'))
            ->assertOk()
            ->assertSee('Stock In')
            ->assertSee('inventory_batches');
    }

    public function test_stock_in_store_works_when_inventory_batches_table_missing(): void
    {
        $branch = Branch::create(['name' => 'Main']);
        $product = Product::create(['name' => 'Test Product', 'sku' => 'TST-4', 'price' => 10, 'active' => true]);

        $stockman = User::create([
            'name' => 'Stockman',
            'email' => 'stockin-post@test.com',
            'password' => bcrypt('password'),
            'role' => 'stockman',
            'branch_id' => $branch->id,
        ]);

        Schema::dropIfExists('inventory_batches');

        $this->actingAs($stockman)
            ->post(route('stockman.stock-in.store'), [
                'supplier_name' => 'Supplier',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 5, 'cost' => 10],
                ],
            ])
            ->assertRedirect(route('stockman.inventory'))
            ->assertSessionHas('success');
    }

    public function test_stock_in_store_works_when_inventory_batches_missing_supplier_name_column(): void
    {
        $branch = Branch::create(['name' => 'Main']);
        $product = Product::create(['name' => 'Legacy Batch Product', 'sku' => 'TST-5', 'price' => 10, 'active' => true]);

        $stockman = User::create([
            'name' => 'Stockman',
            'email' => 'stockin-legacy-batch@test.com',
            'password' => bcrypt('password'),
            'role' => 'stockman',
            'branch_id' => $branch->id,
        ]);

        if (Schema::hasColumn('inventory_batches', 'supplier_name')) {
            Schema::table('inventory_batches', function ($table) {
                $table->dropColumn('supplier_name');
            });
        }

        if (Schema::hasColumn('inventory_batches', 'received_at')) {
            Schema::table('inventory_batches', function ($table) {
                $table->dropColumn('received_at');
            });
        }

        $this->actingAs($stockman)
            ->post(route('stockman.stock-in.store'), [
                'supplier_name' => 'Supplier',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 3, 'cost' => 12],
                ],
            ])
            ->assertRedirect(route('stockman.inventory'))
            ->assertSessionHas('success');
    }

    public function test_product_search_endpoint_returns_matches(): void
    {
        $branch = Branch::create(['name' => 'Main']);
        Product::create(['name' => 'Coke Zero', 'sku' => 'COKE-Z', 'price' => 20, 'active' => true]);

        $stockman = User::create([
            'name' => 'Stockman',
            'email' => 'stockin-search@test.com',
            'password' => bcrypt('password'),
            'role' => 'stockman',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($stockman)
            ->getJson(route('stockman.products.search', ['q' => 'Coke']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Coke Zero']);
    }
}
