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

    public function test_unauthenticated_android_pos_redirect_includes_error_hint(): void
    {
        $this->withHeader('User-Agent', 'INSAPOSv3/1.0 Android/14')
            ->get(route('pos.cashier'))
            ->assertRedirect(route('login', ['error' => 'session_required']));
    }

    public function test_login_page_shows_android_session_error_message(): void
    {
        $this->withHeader('User-Agent', 'INSAPOSv3/1.0 Android/14')
            ->get(route('login', ['error' => 'session_required']))
            ->assertOk()
            ->assertSee('session was not kept', false);
    }

    public function test_android_cashier_login_redirect_stays_on_request_host(): void
    {
        User::create([
            'name' => 'Cashier', 'email' => 'android-cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier',
        ]);

        $this->withHeader('User-Agent', 'INSAPOSv3/1.0 Android/14')
            ->post('/login', ['email' => 'android-cashier@test.com', 'password' => 'password'])
            ->assertRedirectContains('/pos/cashier');
    }
}
