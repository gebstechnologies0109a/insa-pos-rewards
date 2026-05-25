<?php

namespace Tests\Feature\POS;

use App\Models\POS\PosSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test block-based reward calculations per the specification table:
 *
 * | Total | Block | Reward Value | Expected |
 * |-------|-------|-------------|----------|
 * | 200   | 200   | 0.50        | 0.50     |
 * | 200   | 200   | 1.25        | 1.25     |
 * | 400   | 200   | 0.50        | 1.00     |
 * | 199   | 200   | any         | 0.00     |
 * | 788   | 200   | 0.50        | 1.50     |
 * | 788   | 200   | 2.00        | 6.00     |
 */
class BlockRewardsTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::create([
            'name' => 'Block Tester', 'email' => 'block@test.com',
            'password' => bcrypt('password'), 'upline_id' => null,
        ]);

        $this->postJson('/api/pos/stock-in', [
            'branch_id' => 1, 'user_id' => 1, 'supplier_name' => 'Supplier',
            'items' => [['product_id' => 1, 'product_name' => 'Product', 'qty' => 1000, 'cost' => 1]],
        ]);
    }

    protected function configureRewards(string $value, string $block = '200'): void
    {
        PosSetting::setValue('reward_value', $value);
        PosSetting::setValue('reward_block_amount', $block);
    }

    protected function makeSale(float $total): void
    {
        $unitPrice = $total;

        $response = $this->postJson('/api/pos/sales', [
            'branch_id' => 1, 'cashier_id' => 10,
            'member_id' => $this->member->id,
            'payment_method' => 'cash', 'amount_tendered' => $total + 100,
            'items' => [
                ['product_id' => 1, 'product_name' => 'Product', 'qty' => 1, 'price' => $unitPrice, 'discount' => 0],
            ],
        ]);
        $response->assertCreated();
    }

    public function test_200_total_block200_value050(): void
    {
        $this->configureRewards('0.50', '200');
        $this->makeSale(200);

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate', 'amount' => 0.50,
        ]);
    }

    public function test_200_total_block200_value125(): void
    {
        $this->configureRewards('1.25', '200');
        $this->makeSale(200);

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate', 'amount' => 1.25,
        ]);
    }

    public function test_400_total_block200_value050(): void
    {
        $this->configureRewards('0.50', '200');
        $this->makeSale(400);

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate', 'amount' => 1.00,
        ]);
    }

    public function test_199_total_block200_no_reward(): void
    {
        $this->configureRewards('0.50', '200');
        $this->makeSale(199);

        $this->assertDatabaseMissing('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate',
        ]);
    }

    public function test_788_total_block200_value050(): void
    {
        $this->configureRewards('0.50', '200');
        $this->makeSale(788);

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate', 'amount' => 1.50,
        ]);
    }

    public function test_788_total_block200_value200(): void
    {
        $this->configureRewards('2.00', '200');
        $this->makeSale(788);

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate', 'amount' => 6.00,
        ]);
    }

    public function test_custom_block_amount_500(): void
    {
        $this->configureRewards('1.00', '500');
        $this->makeSale(1200);

        // floor(1200/500) = 2 blocks, 2 * 1.00 = 2.00
        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'rebate', 'amount' => 2.00,
        ]);
    }

    public function test_rewards_disabled_creates_nothing(): void
    {
        PosSetting::setValue('rewards_enabled', '0');
        $this->configureRewards('0.50', '200');
        $this->makeSale(400);

        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_points_mode_credits_loyalty_points_table(): void
    {
        PosSetting::setValue('reward_mode', 'points');
        $this->configureRewards('0.50', '200');
        $this->makeSale(400);

        // 2 blocks * 0.50 = 1.00
        $this->assertDatabaseHas('loyalty_points', [
            'member_id' => $this->member->id, 'points' => 1.00,
        ]);
        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'points', 'amount' => 1.00,
        ]);
        $this->assertDatabaseMissing('wallet_ledgers', [
            'member_id' => $this->member->id, 'source' => 'rebate',
        ]);
    }
}
