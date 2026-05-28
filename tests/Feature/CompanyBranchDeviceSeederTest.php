<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\Device;
use App\Models\POS\PosTerminalSession;
use Database\Seeders\CompanyBranchDeviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyBranchDeviceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_gebs_insapos_and_links_devices_from_sessions(): void
    {
        $branch = Branch::create([
            'name'    => 'INSAPOS',
            'address' => 'Test branch',
        ]);

        foreach (['fp-tablet-1', 'fp-tablet-2'] as $fp) {
            PosTerminalSession::create([
                'id'                 => (string) Str::uuid(),
                'branch_id'          => $branch->id,
                'device_fingerprint' => $fp,
                'user_id'            => 1,
                'started_at'         => now(),
                'is_active'          => true,
            ]);
        }

        $this->seed(CompanyBranchDeviceSeeder::class);

        $company = Company::where('name', 'GEBS')->first();
        $this->assertNotNull($company);
        $this->assertDatabaseHas('branches', ['name' => 'INSAPOS', 'company_id' => $company->id]);
        $this->assertSame(2, Device::where('branch_id', $branch->id)->count());
        $this->assertSame(
            2,
            PosTerminalSession::where('branch_id', $branch->id)->whereNotNull('device_id')->count()
        );
    }
}
