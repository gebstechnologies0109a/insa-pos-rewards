<?php

namespace Tests\Feature;

use App\Models\Inventory\StockMovement;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use App\Models\User;
use App\Services\POS\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $admin;
    protected User $manager;
    protected User $stockman;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);

        $this->cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);
        $this->manager = User::create([
            'name' => 'Manager', 'email' => 'mgr@test.com',
            'password' => bcrypt('password'), 'role' => 'manager',
            'branch_id' => $this->branch->id,
        ]);
        $this->stockman = User::create([
            'name' => 'Stockman', 'email' => 'stockman@test.com',
            'password' => bcrypt('password'), 'role' => 'stockman',
            'branch_id' => $this->branch->id,
        ]);
    }

    // ────── OPEN SHIFT ──────

    public function test_cashier_can_open_shift(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/open', ['opening_cash' => 500])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shift.user_id', $this->cashier->id)
            ->assertJsonPath('shift.status', 'open');

        $this->assertDatabaseHas('pos_shifts', [
            'user_id' => $this->cashier->id,
            'status' => 'open',
            'opening_cash' => 500,
        ]);
    }

    public function test_cannot_open_duplicate_shift(): void
    {
        PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/open', ['opening_cash' => 300])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_open_shift_validates_opening_cash(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/open', ['opening_cash' => -100])
            ->assertStatus(422);

        $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/open', [])
            ->assertStatus(422);
    }

    // ────── CLOSE SHIFT ──────

    public function test_cashier_can_close_shift(): void
    {
        $shift = PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/close', ['closing_cash' => 600])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shift.status', 'closed');

        $this->assertDatabaseHas('pos_shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'closing_cash' => 600,
        ]);
    }

    public function test_close_shift_calculates_variance(): void
    {
        $shift = PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        PosSale::create([
            'sale_number' => 'TEST001',
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 200, 'discount_total' => 0, 'total' => 200,
            'payment_method' => 'cash', 'amount_tendered' => 200,
            'change_due' => 0, 'status' => 'completed', 'sold_at' => now(),
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/close', ['closing_cash' => 750]);

        $response->assertOk();

        $closed = $response->json('shift');
        $this->assertEquals(200, (float) $closed['system_sales_total']);
        $this->assertEquals(50, (float) $closed['cash_variance']);
    }

    public function test_close_shift_no_active_shift(): void
    {
        $this->actingAs($this->cashier)
            ->postJson('/api/pos/shift/close', ['closing_cash' => 500])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ────── CURRENT SHIFT ──────

    public function test_get_current_shift_when_active(): void
    {
        PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        $this->actingAs($this->cashier)
            ->getJson('/api/pos/shift/current')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shift.status', 'open');
    }

    public function test_get_current_shift_when_none(): void
    {
        $this->actingAs($this->cashier)
            ->getJson('/api/pos/shift/current')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shift', null);
    }

    // ────── SALE TIED TO SHIFT ──────

    public function test_sale_includes_shift_id(): void
    {
        $shift = PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        $product = Product::create([
            'name' => 'Widget', 'price' => 100, 'active' => true,
        ]);

        StockMovement::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'type' => 'stock_in',
            'qty' => 50,
        ]);

        $response = $this->actingAs($this->cashier)
            ->postJson('/api/pos/sales', [
                'branch_id' => $this->branch->id,
                'shift_id' => $shift->id,
                'cashier_id' => $this->cashier->id,
                'payment_method' => 'cash',
                'amount_tendered' => 100,
                'items' => [[
                    'product_id' => $product->id,
                    'product_name' => 'Widget',
                    'qty' => 1,
                    'price' => 100,
                ]],
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('pos_sales', [
            'shift_id' => $shift->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'shift_id' => $shift->id,
            'type' => 'sale',
        ]);
    }

    // ────── SHIFT SERVICE UNIT TESTS ──────

    public function test_shift_service_open(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 1000);

        $this->assertEquals('open', $shift->status);
        $this->assertEquals(1000, (float) $shift->opening_cash);
        $this->assertEquals($this->cashier->id, $shift->user_id);
        $this->assertEquals($this->branch->id, $shift->branch_id);
    }

    public function test_shift_service_close(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 1000);
        $closed = $service->closeShift($shift, 1200);

        $this->assertEquals('closed', $closed->status);
        $this->assertEquals(1200, (float) $closed->closing_cash);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_shift_service_prevents_double_open(): void
    {
        $service = app(ShiftService::class);
        $service->openShift($this->cashier, 500);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You already have an active shift.');

        $service->openShift($this->cashier, 300);
    }

    public function test_shift_service_prevents_closing_already_closed(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);
        $service->closeShift($shift, 500);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This shift is already closed.');

        $service->closeShift($shift->fresh(), 500);
    }

    // ────── BACK-OFFICE ACCESS ──────

    public function test_admin_can_view_shifts(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts'))
            ->assertOk()
            ->assertSee('Shift Management');
    }

    public function test_manager_can_view_shifts(): void
    {
        $this->actingAs($this->manager)
            ->get(route('backoffice.shifts'))
            ->assertOk();
    }

    public function test_cashier_cannot_view_backoffice_shifts(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('backoffice.shifts'))
            ->assertForbidden();
    }

    public function test_stockman_cannot_view_backoffice_shifts(): void
    {
        $this->actingAs($this->stockman)
            ->get(route('backoffice.shifts'))
            ->assertForbidden();
    }

    public function test_shifts_page_filters_by_branch(): void
    {
        $branch2 = Branch::create(['name' => 'Branch B']);

        PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'closed',
            'closed_at' => now(),
            'system_sales_total' => 0,
            'cash_variance' => 0,
            'closing_cash' => 500,
        ]);

        PosShift::create([
            'branch_id' => $branch2->id,
            'user_id' => $this->admin->id,
            'opened_at' => now(),
            'opening_cash' => 1000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts', ['branch_id' => $branch2->id]));

        $response->assertOk();
        $response->assertSee('Branch B');
    }

    public function test_manager_scoped_to_own_branch(): void
    {
        $branch2 = Branch::create(['name' => 'Branch B']);

        PosShift::create([
            'branch_id' => $branch2->id,
            'user_id' => $this->admin->id,
            'opened_at' => now(),
            'opening_cash' => 1000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('backoffice.shifts'));

        $response->assertOk();
        $response->assertDontSee('Branch B');
    }
}
