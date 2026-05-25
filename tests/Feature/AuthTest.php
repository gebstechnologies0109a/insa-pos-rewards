<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Test', 'email' => 'test@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);

        $this->post('/login', ['email' => 'test@test.com', 'password' => 'password'])
            ->assertRedirect(route('backoffice.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Test', 'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', ['email' => 'test@test.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_cashier_redirected_to_pos(): void
    {
        User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier',
        ]);

        $this->post('/login', ['email' => 'cashier@test.com', 'password' => 'password'])
            ->assertRedirect(route('pos.cashier'));
    }

    public function test_stockman_redirected_to_inventory(): void
    {
        User::create([
            'name' => 'Stockman', 'email' => 'stockman@test.com',
            'password' => bcrypt('password'), 'role' => 'stockman',
        ]);

        $this->post('/login', ['email' => 'stockman@test.com', 'password' => 'password'])
            ->assertRedirect(route('stockman.inventory'));
    }

    public function test_manager_redirected_to_backoffice(): void
    {
        User::create([
            'name' => 'Manager', 'email' => 'mgr@test.com',
            'password' => bcrypt('password'), 'role' => 'manager',
        ]);

        $this->post('/login', ['email' => 'mgr@test.com', 'password' => 'password'])
            ->assertRedirect(route('backoffice.dashboard'));
    }

    public function test_owner_redirected_to_backoffice(): void
    {
        User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('password'), 'role' => 'owner',
        ]);

        $this->post('/login', ['email' => 'owner@test.com', 'password' => 'password'])
            ->assertRedirect(route('backoffice.dashboard'));
    }

    public function test_logout(): void
    {
        $user = User::create([
            'name' => 'Test', 'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unauthenticated_redirected_to_login(): void
    {
        $this->get(route('pos.cashier'))->assertRedirect('/login');
        $this->get(route('backoffice.dashboard'))->assertRedirect('/login');
        $this->get(route('admin.products.index'))->assertRedirect('/login');
    }
}
