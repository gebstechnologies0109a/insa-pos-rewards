<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use App\Models\POS\PosShiftAudit;
use App\Models\POS\ShiftAuditLog;
use App\Models\User;
use App\Services\POS\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main Branch']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('pw'), 'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier = User::create([
            'name' => 'Cashier', 'email' => 'cashier@test.com',
            'password' => bcrypt('pw'), 'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function createShiftWithSales(): PosShift
    {
        $shift = PosShift::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now()->subHours(4),
            'closed_at' => now(),
            'opening_cash' => 500,
            'closing_cash' => 1500,
            'system_sales_total' => 1000,
            'cash_variance' => 0,
            'status' => 'closed',
        ]);

        PosSale::create([
            'sale_number' => 'TEST001',
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 500, 'discount_total' => 0, 'total' => 500,
            'payment_method' => 'cash', 'amount_tendered' => 500,
            'change_due' => 0, 'status' => 'completed', 'sold_at' => now(),
        ]);

        PosShiftAudit::create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'action' => 'open',
            'details' => ['opening_cash' => 500, 'branch_id' => $this->branch->id],
            'created_at' => now()->subHours(4),
        ]);

        PosShiftAudit::create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'action' => 'close',
            'details' => ['closing_cash' => 1500, 'system_sales_total' => 1000, 'cash_variance' => 0],
            'created_at' => now(),
        ]);

        return $shift;
    }

    // ────── SHIFT SHOW PAGE ──────

    public function test_shift_show_page_loads(): void
    {
        $shift = $this->createShiftWithSales();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.show', $shift))
            ->assertOk()
            ->assertSee('Shift #' . $shift->id)
            ->assertSee('Cash Summary')
            ->assertSee('Sales During This Shift')
            ->assertSee('Audit History')
            ->assertSee('TEST001');
    }

    public function test_shift_show_displays_variance(): void
    {
        $shift = $this->createShiftWithSales();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.show', $shift))
            ->assertOk()
            ->assertSee('Variance')
            ->assertSee('Expected Cash');
    }

    public function test_shift_show_has_export_buttons(): void
    {
        $shift = $this->createShiftWithSales();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.show', $shift))
            ->assertOk()
            ->assertSee('Export CSV')
            ->assertSee('Export PDF')
            ->assertSee('View Audit Trail');
    }

    public function test_cashier_cannot_access_shift_show(): void
    {
        $shift = $this->createShiftWithSales();

        $this->actingAs($this->cashier)
            ->get(route('backoffice.shifts.show', $shift))
            ->assertForbidden();
    }

    // ────── PER-SHIFT CSV EXPORT ──────

    public function test_shift_csv_export(): void
    {
        $shift = $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.export.csv', $shift));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('shift-' . $shift->id, $response->headers->get('content-disposition'));
    }

    // ────── PER-SHIFT PDF EXPORT ──────

    public function test_shift_pdf_export(): void
    {
        $shift = $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.export.pdf', $shift));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    // ────── DASHBOARD BULK CSV EXPORT ──────

    public function test_dashboard_csv_export(): void
    {
        $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    // ────── DASHBOARD BULK PDF EXPORT ──────

    public function test_dashboard_pdf_export(): void
    {
        $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard', ['export' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    // ────── DASHBOARD SHIFT ID COLUMN ──────

    public function test_dashboard_shows_shift_id(): void
    {
        $shift = $this->createShiftWithSales();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.dashboard'))
            ->assertOk()
            ->assertSee('>' . $shift->id . '<', false);
    }

    // ────── AUDIT DOWNLOAD JSON ──────

    public function test_audit_page_has_download_json_button(): void
    {
        $this->createShiftWithSales();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts.audit'))
            ->assertOk()
            ->assertSee('Download JSON');
    }

    // ────── SHIFT SUMMARY WIDGET ON DASHBOARD ──────

    public function test_dashboard_shows_shift_summary_widget(): void
    {
        $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.dashboard'));

        $response->assertOk()
            ->assertSee('View All');

        $content = $response->getContent();
        $this->assertStringContainsString('Shifts', $content);
        $this->assertStringContainsString('backoffice/shifts/dashboard', $content);
    }

    // ────── AUDIT LOG VIA SERVICE ──────

    public function test_shift_service_creates_audit_on_open(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);

        $this->assertDatabaseHas('pos_shift_audits', [
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'action' => 'open',
        ]);
    }

    public function test_shift_service_creates_audit_on_close(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);
        $service->closeShift($shift, 600);

        $this->assertDatabaseHas('pos_shift_audits', [
            'shift_id' => $shift->id,
            'action' => 'close',
        ]);

        $audit = PosShiftAudit::where('shift_id', $shift->id)->where('action', 'close')->first();
        $this->assertEquals(600.0, $audit->details['closing_cash']);
    }

    // ────── SHIFT AUDIT LOG (old_values / new_values) ──────

    public function test_shift_service_creates_audit_log_on_open(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);

        $this->assertDatabaseHas('shift_audit_logs', [
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'action' => 'open_shift',
        ]);

        $log = ShiftAuditLog::where('shift_id', $shift->id)->where('action', 'open_shift')->first();
        $this->assertNull($log->old_values);
        $this->assertEquals(500.0, $log->new_values['opening_cash']);
        $this->assertEquals('open', $log->new_values['status']);
    }

    public function test_shift_service_creates_audit_log_on_close(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);
        $service->closeShift($shift, 600);

        $this->assertDatabaseHas('shift_audit_logs', [
            'shift_id' => $shift->id,
            'action' => 'close_shift',
        ]);

        $log = ShiftAuditLog::where('shift_id', $shift->id)->where('action', 'close_shift')->first();
        $this->assertEquals('open', $log->old_values['status']);
        $this->assertEquals('closed', $log->new_values['status']);
        $this->assertEquals(600.0, $log->new_values['closing_cash']);
    }

    public function test_shift_audit_log_has_foreign_keys(): void
    {
        $service = app(ShiftService::class);
        $shift = $service->openShift($this->cashier, 500);

        $log = ShiftAuditLog::where('shift_id', $shift->id)->first();
        $this->assertNotNull($log);
        $this->assertInstanceOf(PosShift::class, $log->shift);
        $this->assertInstanceOf(User::class, $log->user);
    }

    // ────── INDEX PAGE (SHIFT MANAGEMENT) ──────

    public function test_index_page_shows_shift_id_column(): void
    {
        $shift = $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts'));

        $response->assertOk()
            ->assertSee('Shift ID')
            ->assertSee('>' . $shift->id . '<', false);
    }

    public function test_index_page_shows_action_buttons(): void
    {
        $shift = $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts'));

        $response->assertOk()
            ->assertSee('View')
            ->assertSee('Audit')
            ->assertSee('CSV')
            ->assertSee('PDF');
    }

    public function test_index_page_has_export_buttons(): void
    {
        $this->createShiftWithSales();

        $this->actingAs($this->admin)
            ->get(route('backoffice.shifts'))
            ->assertOk()
            ->assertSee('Export CSV')
            ->assertSee('Export PDF');
    }

    public function test_index_page_bulk_csv_export(): void
    {
        $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_index_page_bulk_pdf_export(): void
    {
        $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('backoffice.shifts', ['export' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    // ────── ROUTE ALIASES ──────

    public function test_shift_export_csv_alias_route(): void
    {
        $shift = $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('shift.export.csv', $shift));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_shift_export_pdf_alias_route(): void
    {
        $shift = $this->createShiftWithSales();

        $response = $this->actingAs($this->admin)
            ->get(route('shift.export.pdf', $shift));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }
}
