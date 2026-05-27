<?php

namespace Tests\Unit\Models;

use App\Models\POS\PosLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosLicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_flag_allows_pos_when_status_stale_suspended(): void
    {
        $license = PosLicense::make([
            'branch_id' => 1,
            'pos_slots' => 1,
            'active'    => true,
            'status'    => PosLicense::STATUS_SUSPENDED,
        ]);
        $license->saveQuietly();

        $this->assertTrue($license->hasActiveEntitlement());
        $this->assertTrue($license->isCurrentlyActive());
    }

    public function test_status_active_allows_pos_when_active_flag_false(): void
    {
        $license = PosLicense::create([
            'branch_id' => 1,
            'pos_slots' => 1,
            'active'    => false,
            'status'    => PosLicense::STATUS_ACTIVE,
        ]);

        $this->assertTrue($license->hasActiveEntitlement());
        $this->assertTrue($license->isCurrentlyActive());
    }

    public function test_both_inactive_denies_pos(): void
    {
        $license = PosLicense::create([
            'branch_id' => 1,
            'pos_slots' => 1,
            'active'    => false,
            'status'    => PosLicense::STATUS_SUSPENDED,
        ]);

        $this->assertFalse($license->isCurrentlyActive());
    }

    public function test_null_dates_do_not_block_active_license(): void
    {
        $license = PosLicense::create([
            'branch_id' => 1,
            'pos_slots' => 2,
            'active'    => true,
            'status'    => PosLicense::STATUS_ACTIVE,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        $this->assertTrue($license->isCurrentlyActive());
    }

    public function test_saving_active_syncs_status_column(): void
    {
        $license = PosLicense::create([
            'branch_id' => 1,
            'pos_slots' => 1,
            'active'    => false,
            'status'    => PosLicense::STATUS_SUSPENDED,
        ]);

        $license->update(['active' => true]);

        $this->assertSame(PosLicense::STATUS_ACTIVE, $license->fresh()->status);
        $this->assertTrue($license->fresh()->active);
    }
}
