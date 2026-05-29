<?php

namespace Tests\Feature\POS;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\PosLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosLicenseValidateTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
    }

    public function test_license_validate_returns_allowed_with_active_license(): void
    {
        PosLicense::create([
            'branch_id' => 1,
            'pos_slots' => 2,
            'active'    => true,
            'status'    => 'active',
        ]);

        $this->postJson('/api/pos/license/validate', [
            'device_fingerprint' => 'test-device-fp-1',
            'branch_id'          => 1,
        ])
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('branch_id', 1)
            ->assertJsonStructure(['pos_settings', 'slots']);
    }

    public function test_license_validate_rejects_inactive_license(): void
    {
        PosLicense::create([
            'branch_id' => 1,
            'pos_slots' => 2,
            'active'    => false,
            'status'    => 'suspended',
        ]);

        $this->postJson('/api/pos/license/validate', [
            'device_fingerprint' => 'test-device-fp-2',
            'branch_id'          => 1,
        ])
            ->assertForbidden()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('code', 'license_inactive');
    }

    public function test_license_validate_includes_company_hierarchy(): void
    {
        $company = Company::first();
        $branch = Branch::find(1);

        $this->postJson('/api/pos/license/validate', [
            'device_fingerprint' => 'test-device-fp-3',
            'branch_id'          => 1,
        ])
            ->assertOk()
            ->assertJsonPath('company_id', $company->id)
            ->assertJsonPath('company_name', $company->name)
            ->assertJsonPath('branch_name', $branch->name);
    }
}
