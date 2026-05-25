<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\PosShift;
use App\Models\POS\PosShiftAudit;
use App\Models\User;
use App\Services\POS\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $stockman;
    protected Branch $branch1;
    protected Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Branch::create(['name' => 'Branch A']);
        $this->branch2 = Branch::create(['name' => 'Branch B']);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('pw'), 'role' => 'owner', 'branch_id' => $this->branch1->id,
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('pw'), 'role' => 'admin', 'branch_id' => $this->branch1->id,
        ]);
        $this->manager = User::create([
            'name' => 'Manager', 'email' => 'mgr@test.com',
            'password' => bcrypt('pw'), 'role' => 'manager', 'branch_id' => $this->branch1->id,
        ]);
        $this->cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('pw'), 'role' => 'cashier', 'branch_id' => $this->branch1->id,
        ]);
        $this->stockman = User::create([
            'name' => 'Stockman', 'email' => 'stockman@test.com',
            'password' => bcrypt('pw'), 'role' => 'stockman', 'branch_id' => $this->branch1->id,
        ]);
    }

    protected function seedShifts(): void
    {
        PosShift::create([
            'branch_id' => $this->branch1->id, 'user_id' => $this->cashier->id,
            'opened_at' => now()->subHours(8), 'closed_at' => now()->subHours(1),
            'opening_cash' => 500, 'closing_cash' => 1400,
            'system_sales_total' => 1000, 'cash_variance' => -100,
            'status' => 'closed',
        ]);

        PosShift::create([
            'branch_id' => $this->branch1->id, 'user_id' => $this->cashier->id,
            'opened_at' => now(), 'opening_cash' => 300, 'status' => 'open',
        ]);

        PosShift::create([
            'branch_id' => $this->branch2->id, 'user_id' => $this->admin->id,
            'opened_at' => now()->subHours(5), 'closed_at' => now()->subHours(2),
            'opening_cash' => 1000, 'closing_cash' => 2500,
            'system_sales_total' => 1500, 'cash_variance' => 0,
            'status' => 'closed',
        ]);
    }

    // ────── SHIFT DASHBOARD ──────

    public function test_admin_can_access_shift_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard'))
            ->assertOk()
            ->assertSee('Shift Dashboard')
            ->assertSee('Total Shifts')
            ->assertSee('Variance Report')
            ->assertSee('Audit Trail');
    }

    public function test_shift_dashboard_shows_metrics(): void
    {
        $this->seedShifts();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard'));

        $response->assertOk()
            ->assertSee('Total Shifts')
            ->assertSee('Open')
            ->assertSee('Closed')
            ->assertSee('Total Sales');
    }

    public function test_shift_dashboard_filters_by_branch(): void
    {
        $this->seedShifts();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard', ['branch_id' => $this->branch2->id]))
            ->assertOk()
            ->assertSee('Branch B');
    }

    public function test_shift_dashboard_filters_by_status(): void
    {
        $this->seedShifts();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard', ['status' => 'open']))
            ->assertOk();
    }

    public function test_manager_scoped_to_own_branch_on_dashboard(): void
    {
        $this->seedShifts();

        $this->actingAs($this->manager)
            ->get(route('backoffice.shifts.dashboard'))
            ->assertOk()
            ->assertDontSee('Branch B');
    }

    public function test_cashier_cannot_access_shift_dashboard(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('backoffice.shifts.dashboard'))
            ->assertForbidden();
    }

    public function test_stockman_cannot_access_shift_dashboard(): void
    {
        $this->actingAs($this->stockman)
            ->get(route('backoffice.shifts.dashboard'))
            ->assertForbidden();
    }

    // ────── VARIANCE REPORT ──────

    public function test_admin_can_access_variance_report(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.variance'))
            ->assertOk()
            ->assertSee('Shift Variance Report')
            ->assertSee('Export CSV');
    }

    public function test_variance_report_shows_closed_shifts_only(): void
    {
        $this->seedShifts();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.variance'));

        $response->assertOk();
        $response->assertDontSee('>Open<');
    }

    public function test_variance_report_shows_color_coded_status(): void
    {
        $this->seedShifts();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.variance'));

        $response->assertOk()
            ->assertSee('Short')
            ->assertSee('OK');
    }

    public function test_variance_report_filters_by_variance_type(): void
    {
        $this->seedShifts();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.variance', ['variance_type' => 'short']))
            ->assertOk()
            ->assertSee('Short');

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.variance', ['variance_type' => 'exact']))
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_variance_csv_export(): void
    {
        $this->seedShifts();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.variance', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $response->assertHeader('content-disposition');
    }

    public function test_manager_scoped_on_variance_report(): void
    {
        $this->seedShifts();

        $this->actingAs($this->manager)
            ->get(route('backoffice.shifts.variance'))
            ->assertOk()
            ->assertDontSee('Branch B');
    }

    public function test_cashier_cannot_access_variance_report(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('backoffice.shifts.variance'))
            ->assertForbidden();
    }

    // ────── AUDIT TRAIL ──────

    public function test_admin_can_access_audit_trail(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.audit'))
            ->assertOk()
            ->assertSee('Shift Audit Trail');
    }

    public function test_audit_trail_shows_shift_actions(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);
        $service->closeShift($shift, 600);

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.audit'));

        $response->assertOk()
            ->assertSee('Open')
            ->assertSee('Close');
    }

    public function test_audit_contains_metadata(): void
    {
        $service = app(ShiftService::class);
        $service->openShift($this->cashier, 500);

        $audit = PosShiftAudit::where('action', 'open')->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->cashier->id, $audit->user_id);
        $this->assertArrayHasKey('opening_cash', $audit->details);
        $this->assertEquals(500, $audit->details['opening_cash']);
    }

    public function test_close_audit_contains_variance(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);
        $service->closeShift($shift, 600);

        $audit = PosShiftAudit::where('action', 'close')->first();

        $this->assertNotNull($audit);
        $this->assertArrayHasKey('cash_variance', $audit->details);
        $this->assertArrayHasKey('closing_cash', $audit->details);
        $this->assertArrayHasKey('system_sales_total', $audit->details);
    }

    public function test_audit_trail_filters_by_action(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);
        $service->closeShift($shift, 600);

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.audit', ['action' => 'open']))
            ->assertOk();
    }

    public function test_audit_trail_search_by_shift_id(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.audit', ['search' => $shift->id]))
            ->assertOk();
    }

    public function test_manager_scoped_on_audit_trail(): void
    {
        PosShift::create([
            'branch_id' => $this->branch2->id, 'user_id' => $this->admin->id,
            'opened_at' => now(), 'opening_cash' => 1000, 'status' => 'open',
        ]);

        PosShiftAudit::create([
            'shift_id' => PosShift::latest()->first()->id,
            'user_id' => $this->admin->id,
            'action' => 'open',
            'details' => ['opening_cash' => 1000],
            'created_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->get(route('backoffice.shifts.audit'))
            ->assertOk()
            ->assertDontSee('Branch B');
    }

    public function test_cashier_cannot_access_audit_trail(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('backoffice.shifts.audit'))
            ->assertForbidden();
    }

    public function test_stockman_cannot_access_audit_trail(): void
    {
        $this->actingAs($this->stockman)
            ->get(route('backoffice.shifts.audit'))
            ->assertForbidden();
    }

    public function test_owner_can_access_all_shift_pages(): void
    {
        $this->actingAs($this->owner)
            ->get(route('backoffice.shifts.dashboard'))
            ->assertOk();

        $this->actingAs($this->owner)
            ->get(route('backoffice.shifts.variance'))
            ->assertOk();

        $this->actingAs($this->owner)
            ->get(route('backoffice.shifts.audit'))
            ->assertOk();
    }
}
