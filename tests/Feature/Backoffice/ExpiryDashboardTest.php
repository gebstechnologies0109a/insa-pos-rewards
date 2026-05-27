<?php

namespace Tests\Feature\Backoffice;

use App\Models\POS\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_dashboard_loads_for_admin(): void
    {
        $branch = Branch::create(['name' => 'Main']);
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'expiry-admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)
            ->get(route('backoffice.inventory.expiry', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Expiry Dashboard');
    }

    public function test_expiry_dashboard_shows_migration_notice_when_table_missing(): void
    {
        $branch = Branch::create(['name' => 'Main']);
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'expiry-migrate@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'branch_id' => $branch->id,
        ]);

        \Illuminate\Support\Facades\Schema::dropIfExists('expiry_alerts');

        $this->actingAs($admin)
            ->get(route('backoffice.inventory.expiry', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Expiry Dashboard')
            ->assertSee('expiry_alerts');
    }
}
