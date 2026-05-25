<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $stockman;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create(['name' => 'Main Branch']);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('password'), 'role' => 'owner', 'branch_id' => $branch->id,
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin', 'branch_id' => $branch->id,
        ]);
        $this->manager = User::create([
            'name' => 'Manager', 'email' => 'mgr@test.com',
            'password' => bcrypt('password'), 'role' => 'manager', 'branch_id' => $branch->id,
        ]);
        $this->cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier', 'branch_id' => $branch->id,
        ]);
        $this->stockman = User::create([
            'name' => 'Stockman', 'email' => 'stockman@test.com',
            'password' => bcrypt('password'), 'role' => 'stockman', 'branch_id' => $branch->id,
        ]);
    }

    // ────── CASHIER ACCESS ──────

    public function test_cashier_can_access_pos(): void
    {
        $this->actingAs($this->cashier)->get(route('pos.cashier'))->assertOk();
    }

    public function test_cashier_cannot_access_backoffice(): void
    {
        $this->actingAs($this->cashier)->get(route('backoffice.dashboard'))->assertForbidden();
    }

    public function test_cashier_cannot_access_products(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_cashier_cannot_access_settings(): void
    {
        $this->actingAs($this->cashier)->get(route('pos.settings'))->assertForbidden();
    }

    public function test_cashier_cannot_access_user_management(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_cashier_cannot_access_stockman_pages(): void
    {
        $this->actingAs($this->cashier)->get(route('stockman.inventory'))->assertForbidden();
    }

    // ────── STOCKMAN ACCESS ──────

    public function test_stockman_can_access_inventory(): void
    {
        $this->actingAs($this->stockman)->get(route('stockman.inventory'))->assertOk();
    }

    public function test_stockman_can_access_stock_in(): void
    {
        $this->actingAs($this->stockman)->get(route('stockman.stock-in'))->assertOk();
    }

    public function test_stockman_cannot_access_pos(): void
    {
        $this->actingAs($this->stockman)->get(route('pos.cashier'))->assertForbidden();
    }

    public function test_stockman_cannot_access_backoffice(): void
    {
        $this->actingAs($this->stockman)->get(route('backoffice.dashboard'))->assertForbidden();
    }

    public function test_stockman_cannot_access_settings(): void
    {
        $this->actingAs($this->stockman)->get(route('pos.settings'))->assertForbidden();
    }

    public function test_stockman_cannot_access_user_management(): void
    {
        $this->actingAs($this->stockman)->get(route('admin.users.index'))->assertForbidden();
    }

    // ────── MANAGER ACCESS ──────

    public function test_manager_can_access_backoffice(): void
    {
        $this->actingAs($this->manager)->get(route('backoffice.dashboard'))->assertOk();
    }

    public function test_manager_can_access_products(): void
    {
        $this->actingAs($this->manager)->get(route('admin.products.index'))->assertOk();
    }

    public function test_manager_can_access_inventory(): void
    {
        $this->actingAs($this->manager)->get(route('admin.inventory.dashboard'))->assertOk();
    }

    public function test_manager_can_access_pos(): void
    {
        $this->actingAs($this->manager)->get(route('pos.cashier'))->assertOk();
    }

    public function test_manager_can_view_settings(): void
    {
        $this->actingAs($this->manager)->get(route('pos.settings'))->assertOk();
    }

    public function test_manager_cannot_update_settings(): void
    {
        $this->actingAs($this->manager)
            ->postJson(route('pos.settings.update'), ['settings' => []])
            ->assertForbidden();
    }

    public function test_manager_cannot_access_user_management(): void
    {
        $this->actingAs($this->manager)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_manager_cannot_access_branches(): void
    {
        $this->actingAs($this->manager)->get(route('admin.branches.index'))->assertForbidden();
    }

    // ────── ADMIN ACCESS ──────

    public function test_admin_can_access_backoffice(): void
    {
        $this->actingAs($this->admin)->get(route('backoffice.dashboard'))->assertOk();
    }

    public function test_admin_can_access_user_management(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_admin_can_access_settings(): void
    {
        $this->actingAs($this->admin)->get(route('pos.settings'))->assertOk();
    }

    public function test_admin_can_update_settings(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('pos.settings.update'), [
                'settings' => [['key' => 'rewards_enabled', 'value' => '1']],
            ])
            ->assertOk();
    }

    public function test_admin_can_access_branches(): void
    {
        $this->actingAs($this->admin)->get(route('admin.branches.index'))->assertOk();
    }

    public function test_admin_can_access_pos(): void
    {
        $this->actingAs($this->admin)->get(route('pos.cashier'))->assertOk();
    }

    public function test_admin_cannot_modify_owner(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->owner))
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_owner(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->owner))
            ->assertForbidden();
    }

    public function test_admin_cannot_create_owner_role(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Owner', 'email' => 'new.owner@test.com',
                'password' => 'password', 'password_confirmation' => 'password',
                'role' => 'owner',
            ])
            ->assertSessionHasErrors('role');
    }

    // ────── OWNER ACCESS ──────

    public function test_owner_can_access_everything(): void
    {
        $this->actingAs($this->owner)->get(route('backoffice.dashboard'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.branches.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('pos.settings'))->assertOk();
        $this->actingAs($this->owner)->get(route('pos.cashier'))->assertOk();
        $this->actingAs($this->owner)->get(route('stockman.inventory'))->assertOk();
    }

    public function test_owner_can_modify_admin(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.users.edit', $this->admin))
            ->assertOk();
    }

    public function test_owner_can_delete_admin(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $this->admin->id]);
    }

    public function test_owner_can_create_owner_role(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.users.store'), [
                'name' => 'New Owner', 'email' => 'new.owner@test.com',
                'password' => 'password', 'password_confirmation' => 'password',
                'role' => 'owner',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new.owner@test.com', 'role' => 'owner']);
    }

    public function test_owner_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('admin.users.destroy', $this->owner))
            ->assertForbidden();
    }

    // ────── USER MANAGEMENT CRUD ──────

    public function test_create_user(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Cashier', 'email' => 'new.cashier@test.com',
                'password' => 'password', 'password_confirmation' => 'password',
                'role' => 'cashier',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new.cashier@test.com', 'role' => 'cashier']);
    }

    public function test_update_user(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->cashier), [
                'name' => 'Updated Cashier', 'email' => $this->cashier->email,
                'role' => 'cashier',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $this->cashier->id, 'name' => 'Updated Cashier']);
    }

    public function test_update_user_password(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->cashier), [
                'name' => $this->cashier->name, 'email' => $this->cashier->email,
                'password' => 'newpassword', 'password_confirmation' => 'newpassword',
                'role' => 'cashier',
            ])
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_delete_user(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->stockman))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $this->stockman->id]);
    }
}
