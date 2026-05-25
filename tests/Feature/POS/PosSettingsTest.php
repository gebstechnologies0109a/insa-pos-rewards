<?php

namespace Tests\Feature\POS;

use App\Models\POS\PosSetting;
use App\Models\User;
use App\Services\POS\PosSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSettingsTest extends TestCase
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

    public function test_settings_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pos/settings');

        $response->assertOk()
            ->assertSee('DIY Biz Rewards Engine')
            ->assertSee('Enable Rewards Engine')
            ->assertSee('Reward Mode')
            ->assertSee('Reward Value')
            ->assertSee('Block Amount')
            ->assertSee('Override Rate (Level 2)');
    }

    public function test_settings_page_shows_default_values(): void
    {
        $response = $this->actingAs($this->admin)->get('/pos/settings');

        $response->assertOk()
            ->assertSee('value="0.50"', false)
            ->assertSee('value="200"', false);
    }

    public function test_update_settings_via_post(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/pos/settings', [
            'settings' => [
                ['key' => 'rewards_enabled', 'value' => '1'],
                ['key' => 'reward_mode', 'value' => 'points'],
                ['key' => 'reward_value', 'value' => '1.25'],
                ['key' => 'reward_block_amount', 'value' => '100'],
                ['key' => 'rewards_override_l2', 'value' => '2'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Settings saved successfully.');

        $this->assertDatabaseHas('pos_settings', ['key' => 'reward_value', 'value' => '1.25']);
        $this->assertDatabaseHas('pos_settings', ['key' => 'reward_block_amount', 'value' => '100']);
        $this->assertDatabaseHas('pos_settings', ['key' => 'reward_mode', 'value' => 'points']);
    }

    public function test_settings_page_requires_admin_role(): void
    {
        $cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('password'), 'role' => 'cashier',
        ]);

        $this->actingAs($cashier)->get('/pos/settings')->assertForbidden();
    }

    public function test_settings_page_requires_auth(): void
    {
        $this->get('/pos/settings')->assertRedirect('/login');
    }

    public function test_api_returns_settings(): void
    {
        $response = $this->getJson('/api/pos/settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'settings' => [
                    'rewards_enabled'     => ['key', 'label', 'value', 'group'],
                    'reward_mode'         => ['key', 'label', 'value', 'group'],
                    'reward_value'        => ['key', 'label', 'value', 'group'],
                    'reward_block_amount' => ['key', 'label', 'value', 'group'],
                ],
            ]);
    }

    public function test_settings_service_returns_defaults_when_no_db_rows(): void
    {
        $service = app(PosSettingsService::class);

        $this->assertEquals('1', $service->get('rewards_enabled'));
        $this->assertEquals('rebate', $service->get('reward_mode'));
        $this->assertEquals('0.50', $service->get('reward_value'));
        $this->assertEquals('200', $service->get('reward_block_amount'));
        $this->assertEquals('1', $service->get('rewards_override_l2'));
    }

    public function test_settings_service_returns_db_value_when_set(): void
    {
        PosSetting::create(['key' => 'reward_value', 'value' => '2.00', 'label' => 'Reward Value', 'group' => 'rewards']);

        $service = app(PosSettingsService::class);

        $this->assertEquals('2.00', $service->get('reward_value'));
    }

    public function test_rewards_disabled_creates_no_rewards(): void
    {
        PosSetting::create(['key' => 'rewards_enabled', 'value' => '0', 'label' => 'Rewards Enabled', 'group' => 'rewards']);

        $member = User::create(['name' => 'Test', 'email' => 'test@test.com', 'password' => bcrypt('password')]);

        $this->postJson('/api/pos/stock-in', [
            'branch_id' => 1, 'user_id' => 1, 'supplier_name' => 'Supplier',
            'items' => [['product_id' => 1, 'product_name' => 'Product', 'qty' => 100, 'cost' => 10]],
        ]);

        $this->postJson('/api/pos/sales', [
            'branch_id' => 1, 'cashier_id' => 10, 'member_id' => $member->id,
            'payment_method' => 'cash', 'amount_tendered' => 500,
            'items' => [['product_id' => 1, 'product_name' => 'Product', 'qty' => 2, 'price' => 250, 'discount' => 0]],
        ])->assertCreated();

        $this->assertDatabaseCount('reward_transactions', 0);
    }
}
