<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\Device;
use App\Models\POS\PosLicense;
use App\Models\POS\PosTerminalSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->company = Company::create([
            'name'   => 'GEBS',
            'status' => Company::STATUS_ACTIVE,
        ]);
    }

    public function test_super_admin_can_create_company(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.companies.store'), [
                'name'   => 'Acme Retail',
                'status' => 'active',
            ])
            ->assertRedirect(route('super-admin.companies.index'));

        $this->assertDatabaseHas('companies', [
            'name'   => 'Acme Retail',
            'status' => 'active',
        ]);
    }

    public function test_branch_requires_company_on_create(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.branches.store'), [
                'name'    => 'Store One',
                'address' => '123 Street',
            ])
            ->assertSessionHasErrors('company_id');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.branches.store'), [
                'company_id' => $this->company->id,
                'name'       => 'Store One',
                'address'    => '123 Street',
            ])
            ->assertRedirect(route('super-admin.branches.index'));

        $this->assertDatabaseHas('branches', [
            'company_id' => $this->company->id,
            'name'       => 'Store One',
        ]);
    }

    public function test_device_belongs_to_branch(): void
    {
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'name'       => 'INSAPOS',
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.devices.store'), [
                'branch_id'          => $branch->id,
                'device_name'        => 'Counter 1',
                'device_fingerprint' => 'fp-counter-1',
                'status'             => 'active',
            ])
            ->assertRedirect(route('super-admin.devices.index'));

        $this->assertDatabaseHas('devices', [
            'branch_id'          => $branch->id,
            'device_fingerprint' => 'fp-counter-1',
        ]);
    }

    public function test_session_links_to_device_when_fingerprint_matches(): void
    {
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'name'       => 'INSAPOS',
        ]);

        PosLicense::create([
            'branch_id' => $branch->id,
            'pos_slots' => 2,
            'active'    => true,
            'status'    => 'active',
        ]);

        $device = Device::create([
            'branch_id'          => $branch->id,
            'device_name'        => 'Terminal A',
            'device_fingerprint' => 'terminal-a-fp',
            'status'             => Device::STATUS_ACTIVE,
        ]);

        $cashier = User::factory()->create([
            'role'      => 'cashier',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($cashier)
            ->postJson('/api/pos/terminal/register', [
                'device_fingerprint' => 'terminal-a-fp',
                'branch_id'          => $branch->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $session = PosTerminalSession::where('device_fingerprint', 'terminal-a-fp')->first();

        $this->assertNotNull($session);
        $this->assertSame($device->id, $session->device_id);
    }
}
