<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Company::create(['name' => 'GEBS', 'status' => 'active']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_branch_list_page_loads(): void
    {
        Branch::create(['name' => 'Main Branch', 'company_id' => Company::first()->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee('Main Branch');
    }

    public function test_create_branch(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.branches.store'), [
                'name'    => 'New Branch',
                'address' => '123 Main St',
            ])
            ->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseHas('branches', ['name' => 'New Branch', 'address' => '123 Main St']);
    }

    public function test_update_branch(): void
    {
        $branch = Branch::create(['name' => 'Old Name', 'company_id' => Company::first()->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.branches.update', $branch), [
                'name'    => 'Updated Branch',
                'address' => 'New Address',
            ])
            ->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Updated Branch']);
    }

    public function test_delete_branch(): void
    {
        $branch = Branch::create(['name' => 'To Delete', 'company_id' => Company::first()->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.branches.destroy', $branch))
            ->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_assign_user_to_branch(): void
    {
        $branch = Branch::create(['name' => 'Target Branch', 'company_id' => Company::first()->id]);
        $cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.branches.assign'), [
                'user_id'   => $cashier->id,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'branch_id' => $branch->id]);
    }

    public function test_delete_branch_unassigns_users(): void
    {
        $branch = Branch::create(['name' => 'Doomed Branch', 'company_id' => Company::first()->id]);
        $user = User::create([
            'name' => 'Orphan', 'email' => 'orphan@test.com',
            'password' => bcrypt('password'), 'branch_id' => $branch->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.branches.destroy', $branch));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'branch_id' => null]);
    }
}
