<?php

namespace Tests\Feature\POS;

use App\Models\POS\PosSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $level4;
    protected User $level3;
    protected User $level2;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->level4 = User::create([
            'name' => 'Level 4 Boss', 'email' => 'l4@test.com',
            'password' => bcrypt('password'), 'upline_id' => null,
        ]);
        $this->level3 = User::create([
            'name' => 'Level 3 Leader', 'email' => 'l3@test.com',
            'password' => bcrypt('password'), 'upline_id' => $this->level4->id,
        ]);
        $this->level2 = User::create([
            'name' => 'Level 2 Sponsor', 'email' => 'l2@test.com',
            'password' => bcrypt('password'), 'upline_id' => $this->level3->id,
        ]);
        $this->member = User::create([
            'name' => 'Juan Buyer', 'email' => 'juan@test.com',
            'password' => bcrypt('password'), 'upline_id' => $this->level2->id,
        ]);

        $this->seedStock();
    }

    protected function seedStock(): void
    {
        $this->postJson('/api/pos/stock-in', [
            'branch_id' => 1, 'user_id' => 1, 'supplier_name' => 'Test Supplier',
            'items' => [['product_id' => 1, 'product_name' => 'Test Product', 'qty' => 500, 'cost' => 100]],
        ]);
    }

    protected function salePayload(array $overrides = []): array
    {
        return array_merge([
            'branch_id' => 1, 'cashier_id' => 10,
            'member_id' => $this->member->id,
            'payment_method' => 'cash', 'amount_tendered' => 1000,
            'items' => [
                ['product_id' => 1, 'product_name' => 'Test Product', 'qty' => 2, 'price' => 250, 'discount' => 0],
            ],
        ], $overrides);
    }

    public function test_sale_creates_block_rebate_for_member(): void
    {
        // Total = 500, block = 200, value = 0.50 => floor(500/200) * 0.50 = 2 * 0.50 = 1.00
        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id,
            'type'      => 'rebate',
            'amount'    => 1.00,
        ]);

        $this->assertDatabaseHas('wallet_ledgers', [
            'member_id' => $this->member->id,
            'source'    => 'rebate',
            'amount'    => 1.00,
        ]);
    }

    public function test_sale_creates_overrides_for_uplines(): void
    {
        // Total = 500, override L2 = 1% = 5.00
        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->level2->id, 'type' => 'override_level_1', 'amount' => 5.00,
        ]);
        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->level3->id, 'type' => 'override_level_2', 'amount' => 5.00,
        ]);
        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->level4->id, 'type' => 'override_level_3', 'amount' => 5.00,
        ]);
    }

    public function test_all_reward_transactions_created(): void
    {
        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();

        // 1 block rebate + 3 overrides = 4
        $this->assertDatabaseCount('reward_transactions', 4);
    }

    public function test_walk_in_sale_creates_no_rewards(): void
    {
        $this->postJson('/api/pos/sales', $this->salePayload(['member_id' => null]))->assertCreated();

        $this->assertDatabaseCount('reward_transactions', 0);
        $this->assertDatabaseCount('wallet_ledgers', 0);
    }

    public function test_qualification_progress_updated(): void
    {
        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();

        $this->assertDatabaseHas('qualification_progress', [
            'member_id' => $this->member->id, 'month' => now()->format('Y-m'), 'total' => 500.00,
        ]);
    }

    public function test_qualification_progress_accumulates(): void
    {
        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();
        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();

        $this->assertDatabaseHas('qualification_progress', [
            'member_id' => $this->member->id, 'month' => now()->format('Y-m'), 'total' => 1000.00,
        ]);
        $this->assertDatabaseCount('qualification_progress', 1);
    }

    public function test_idempotency_prevents_double_reward(): void
    {
        $response = $this->postJson('/api/pos/sales', $this->salePayload());
        $response->assertCreated();

        $saleId = $response->json('sale.id');

        // Manually re-dispatch should not create duplicate rewards
        $sale = \App\Models\POS\PosSale::find($saleId);
        app(\App\Services\Rewards\RewardsEngineService::class)->processSale($sale);

        // Still only 1 rebate transaction
        $this->assertEquals(1, \App\Models\Rewards\RewardTransaction::where('sale_id', $saleId)->where('type', 'rebate')->count());
    }

    public function test_is_rebated_flag_set_after_processing(): void
    {
        $response = $this->postJson('/api/pos/sales', $this->salePayload());
        $response->assertCreated();

        $this->assertDatabaseHas('pos_sales', [
            'id' => $response->json('sale.id'),
            'is_rebated' => true,
        ]);
    }

    public function test_partial_upline_chain_works(): void
    {
        $solo = User::create([
            'name' => 'Solo Upline', 'email' => 'solo@test.com',
            'password' => bcrypt('password'), 'upline_id' => null,
        ]);
        $buyer = User::create([
            'name' => 'Solo Buyer', 'email' => 'buyer@test.com',
            'password' => bcrypt('password'), 'upline_id' => $solo->id,
        ]);

        $this->postJson('/api/pos/sales', $this->salePayload(['member_id' => $buyer->id]))->assertCreated();

        // 1 block rebate + 1 override = 2
        $this->assertDatabaseCount('reward_transactions', 2);
    }

    public function test_points_mode_creates_loyalty_points(): void
    {
        PosSetting::create(['key' => 'reward_mode', 'value' => 'points', 'label' => 'Mode', 'group' => 'rewards']);

        $this->postJson('/api/pos/sales', $this->salePayload())->assertCreated();

        $this->assertDatabaseHas('reward_transactions', [
            'member_id' => $this->member->id, 'type' => 'points',
        ]);
        $this->assertDatabaseHas('loyalty_points', [
            'member_id' => $this->member->id, 'points' => 1.00,
        ]);
        // No wallet ledger for rebate in points mode
        $this->assertDatabaseMissing('wallet_ledgers', [
            'member_id' => $this->member->id, 'source' => 'rebate',
        ]);
    }
}
