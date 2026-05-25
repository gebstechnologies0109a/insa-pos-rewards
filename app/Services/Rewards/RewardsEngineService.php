<?php

namespace App\Services\Rewards;

use App\Models\POS\PosSale;
use App\Models\Rewards\LoyaltyPoint;
use App\Models\Rewards\RewardTransaction;
use App\Models\Rewards\WalletLedger;
use App\Models\User;
use App\Services\POS\PosSettingsService;
use Illuminate\Support\Facades\DB;

class RewardsEngineService
{
    public function __construct(
        protected PosSettingsService $settings,
    ) {}

    /**
     * @return array<int, float>
     */
    protected function getOverrideRates(): array
    {
        return [
            1 => $this->settings->getFloat('rewards_override_l2') / 100,
            2 => $this->settings->getFloat('rewards_override_l3') / 100,
            3 => $this->settings->getFloat('rewards_override_l4') / 100,
        ];
    }

    public function processSale(PosSale $sale): void
    {
        if (! $sale->member_id) {
            return;
        }

        if (! $this->settings->getBool('rewards_enabled')) {
            return;
        }

        if ($sale->is_rebated) {
            return;
        }

        DB::transaction(function () use ($sale) {
            $this->processBlockReward($sale);
            $this->processOverrides($sale);
            $this->updateQualification($sale);

            $sale->update(['is_rebated' => true]);
        });
    }

    /**
     * Block-based reward: floor(total / block_amount) * reward_value
     */
    protected function processBlockReward(PosSale $sale): void
    {
        $blockAmount = $this->settings->getFloat('reward_block_amount');
        $rewardValue = $this->settings->getFloat('reward_value');
        $mode = $this->settings->get('reward_mode');

        if ($blockAmount <= 0) {
            return;
        }

        $blocks = intdiv((int) floor($sale->total), (int) $blockAmount);

        if ($blocks <= 0) {
            return;
        }

        $reward = $blocks * $rewardValue;

        RewardTransaction::create([
            'member_id' => $sale->member_id,
            'sale_id'   => $sale->id,
            'type'      => $mode === 'points' ? 'points' : 'rebate',
            'amount'    => $reward,
        ]);

        if ($mode === 'points') {
            LoyaltyPoint::create([
                'member_id' => $sale->member_id,
                'points'    => $reward,
                'reference' => $sale->sale_number,
            ]);
        } else {
            WalletLedger::create([
                'member_id' => $sale->member_id,
                'amount'    => $reward,
                'source'    => 'rebate',
                'reference' => $sale->sale_number,
            ]);
        }
    }

    protected function processOverrides(PosSale $sale): void
    {
        $member = User::find($sale->member_id);
        if (! $member) {
            return;
        }

        $uplineId = $member->upline_id;

        foreach ($this->getOverrideRates() as $level => $rate) {
            if (! $uplineId) {
                break;
            }

            $amount = $sale->total * $rate;

            RewardTransaction::create([
                'member_id' => $uplineId,
                'sale_id'   => $sale->id,
                'type'      => "override_level_{$level}",
                'amount'    => $amount,
            ]);

            WalletLedger::create([
                'member_id' => $uplineId,
                'amount'    => $amount,
                'source'    => "override_level_{$level}",
                'reference' => $sale->sale_number,
            ]);

            $upline = User::find($uplineId);
            $uplineId = $upline?->upline_id;
        }
    }

    protected function updateQualification(PosSale $sale): void
    {
        $month = now()->format('Y-m');

        $existing = DB::table('qualification_progress')
            ->where('member_id', $sale->member_id)
            ->where('month', $month)
            ->first();

        if ($existing) {
            DB::table('qualification_progress')
                ->where('id', $existing->id)
                ->update([
                    'total'      => $existing->total + $sale->total,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('qualification_progress')->insert([
                'member_id'  => $sale->member_id,
                'month'      => $month,
                'total'      => $sale->total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
