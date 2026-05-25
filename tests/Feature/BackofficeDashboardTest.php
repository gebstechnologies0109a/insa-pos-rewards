<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackofficeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Branch $branch1;
    protected Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::create(['name' => 'Branch A']);
        $this->branch2 = Branch::create(['name' => 'Branch B']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
            'branch_id' => $this->branch1->id,
        ]);
    }

    public function test_dashboard_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Branch A')
            ->assertSee('Branch B');
    }

    public function test_dashboard_shows_stats(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard'))
            ->assertOk()
            ->assertSee('Total Products')
            ->assertSee('Sales Today')
            ->assertSee('Revenue Today')
            ->assertSee('Low Stock')
            ->assertSee('Out of Stock');
    }

    public function test_dashboard_filters_by_branch(): void
    {
        $response1 = $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard', ['branch_id' => $this->branch1->id]));
        $response1->assertOk()->assertSee('Branch A');

        $response2 = $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard', ['branch_id' => $this->branch2->id]));
        $response2->assertOk()->assertSee('Branch B');
    }

    public function test_dashboard_shows_quick_links(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard'))
            ->assertOk()
            ->assertSee('Products')
            ->assertSee('Inventory')
            ->assertSee('Users')
            ->assertSee('POS Cashier')
            ->assertSee('Stock In');
    }

    public function test_manager_dashboard_hides_user_management_link(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'mgr@test.com',
            'password' => bcrypt('password'), 'role' => 'manager',
            'branch_id' => $this->branch1->id,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('backoffice.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Manage accounts');
    }

    public function test_branch_selector_auto_submits(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard'))
            ->assertOk()
            ->assertSee('onchange="this.closest', false);
    }
}
