<?php

namespace Tests\Feature;

use App\Models\Inventory\StockMovement;
use App\Models\POS\Branch;
use App\Models\POS\Category;
use App\Models\POS\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch']);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_dashboard_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard'))
            ->assertOk()
            ->assertSee('Inventory Dashboard')
            ->assertSee('Test Branch');
    }

    public function test_dashboard_shows_stock_on_hand(): void
    {
        $product = Product::create(['name' => 'Stock Item', 'price' => 50]);

        StockMovement::create([
            'branch_id' => $this->branch->id, 'product_id' => $product->id,
            'type' => 'stock_in', 'qty' => 100,
            'reference_id' => 1, 'reference_number' => 'SI-TEST',
        ]);
        StockMovement::create([
            'branch_id' => $this->branch->id, 'product_id' => $product->id,
            'type' => 'sale', 'qty' => -30,
            'reference_id' => 1, 'reference_number' => 'S-TEST',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard', ['branch_id' => $this->branch->id]));

        $response->assertOk()->assertSee('Stock Item');
        $response->assertSee('70');
    }

    public function test_dashboard_shows_out_of_stock(): void
    {
        Product::create(['name' => 'Empty Product', 'price' => 10]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard', ['branch_id' => $this->branch->id]));

        $response->assertOk()->assertSee('Out of Stock');
    }

    public function test_dashboard_filters_by_branch(): void
    {
        $branch2 = Branch::create(['name' => 'Other Branch']);
        $product = Product::create(['name' => 'Branch Product', 'price' => 25]);

        StockMovement::create([
            'branch_id' => $branch2->id, 'product_id' => $product->id,
            'type' => 'stock_in', 'qty' => 50,
            'reference_id' => 1, 'reference_number' => 'SI-B2',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard', ['branch_id' => $this->branch->id]));

        $response->assertOk()->assertSee('0');

        $response2 = $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard', ['branch_id' => $branch2->id]));

        $response2->assertOk()->assertSee('50');
    }

    public function test_dashboard_filters_by_search(): void
    {
        Product::create(['name' => 'Alpha Widget', 'price' => 10]);
        Product::create(['name' => 'Beta Gadget', 'price' => 20]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard', ['branch_id' => $this->branch->id, 'search' => 'Alpha']));

        $response->assertOk()
            ->assertSee('Alpha Widget')
            ->assertDontSee('Beta Gadget');
    }

    public function test_dashboard_filters_by_category(): void
    {
        $cat = Category::create(['name' => 'Electronics']);
        Product::create(['name' => 'Phone', 'price' => 1000, 'category_id' => $cat->id]);
        Product::create(['name' => 'Bread', 'price' => 50]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.inventory.dashboard', ['branch_id' => $this->branch->id, 'category' => $cat->id]));

        $response->assertOk()
            ->assertSee('Phone')
            ->assertDontSee('Bread');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('admin.inventory.dashboard'))
            ->assertRedirect('/login');
    }

    public function test_dashboard_requires_admin_role(): void
    {
        $cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier',
        ]);

        $this->actingAs($cashier)
            ->get(route('admin.inventory.dashboard'))
            ->assertForbidden();
    }
}
