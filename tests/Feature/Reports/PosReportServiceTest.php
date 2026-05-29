<?php

namespace Tests\Feature\Reports;

use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\POS\PosShift;
use App\Models\POS\Product;
use App\Models\User;
use App\Services\Reports\PosReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosReportServiceTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
        $this->branch = Branch::find(1);
    }

    public function test_daily_sales_groups_by_date(): void
    {
        $shift = PosShift::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => User::factory()->create(['branch_id' => $this->branch->id])->id,
            'opened_at'    => now(),
            'opening_cash' => 1000,
            'status'       => 'closed',
            'closed_at'    => now(),
        ]);

        $sale = PosSale::create([
            'sale_number'     => 'RPT-001',
            'branch_id'       => $this->branch->id,
            'shift_id'        => $shift->id,
            'cashier_id'      => $shift->user_id,
            'subtotal'        => 100,
            'discount_total'  => 0,
            'total'           => 100,
            'payment_method'  => 'cash',
            'amount_tendered' => 100,
            'change_due'      => 0,
            'status'          => 'completed',
            'sold_at'         => Carbon::parse('2026-05-20 10:00:00'),
        ]);

        PosSaleItem::create([
            'sale_id'      => $sale->id,
            'product_id'   => Product::first()->id,
            'product_name' => 'Test',
            'qty'          => 1,
            'price'        => 100,
            'discount'     => 0,
        ]);

        $rows = app(PosReportService::class)->dailySales(
            $this->branch->id,
            Carbon::parse('2026-05-20')->startOfDay(),
            Carbon::parse('2026-05-20')->endOfDay(),
        );

        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows->first()['transaction_count']);
        $this->assertEquals(100.0, $rows->first()['revenue']);
    }

    public function test_product_performance_aggregates_items(): void
    {
        $product = Product::first();
        $shift = PosShift::create([
            'branch_id'    => $this->branch->id,
            'user_id'      => User::factory()->create(['branch_id' => $this->branch->id])->id,
            'opened_at'    => now(),
            'opening_cash' => 1000,
            'status'       => 'closed',
            'closed_at'    => now(),
        ]);

        $sale = PosSale::create([
            'sale_number'     => 'RPT-002',
            'branch_id'       => $this->branch->id,
            'shift_id'        => $shift->id,
            'cashier_id'      => $shift->user_id,
            'subtotal'        => 150,
            'discount_total'  => 0,
            'total'           => 150,
            'payment_method'  => 'cash',
            'amount_tendered' => 150,
            'change_due'      => 0,
            'status'          => 'completed',
            'sold_at'         => now(),
        ]);

        PosSaleItem::create([
            'sale_id'      => $sale->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'sku'          => $product->sku,
            'qty'          => 3,
            'price'        => 50,
            'discount'     => 0,
        ]);

        $rows = app(PosReportService::class)->productPerformance($this->branch->id);
        $row = $rows->firstWhere('product_id', $product->id);

        $this->assertNotNull($row);
        $this->assertEquals(3.0, $row['qty_sold']);
        $this->assertEquals(150.0, $row['revenue']);
    }

    public function test_backoffice_daily_sales_page_loads(): void
    {
        $manager = User::factory()->create([
            'role'      => 'manager',
            'branch_id' => 1,
        ]);
        $this->actingAs($manager);

        $this->get(route('backoffice.reports.daily-sales', ['branch_id' => 1]))
            ->assertOk()
            ->assertSee('Daily Sales');
    }

    public function test_owner_dashboard_loads_for_owner(): void
    {
        $owner = User::factory()->create([
            'role'      => 'owner',
            'branch_id' => 1,
        ]);
        $this->actingAs($owner);

        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('Owner Dashboard');
    }
}
