<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosZReading;
use App\Models\User;
use App\Services\ReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZReadingUiTest extends TestCase
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
            'name' => 'Admin',
            'email' => 'admin-zreading@test.com',
            'password' => bcrypt('pw'),
            'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier-zreading@test.com',
            'password' => bcrypt('pw'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function createUntaggedSale(): PosSale
    {
        return PosSale::create([
            'sale_number' => 'ZRD' . uniqid(),
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
            'payment_method' => 'cash',
            'amount_tendered' => 100,
            'change_due' => 0,
            'status' => 'completed',
            'sold_at' => now(),
        ]);
    }

    public function test_z_reading_index_shows_record_when_filtered_by_branch_and_date(): void
    {
        $generatedAt = now()->startOfMinute();

        PosZReading::create([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'z_count' => 1,
            'generated_at' => $generatedAt,
            'total_sales' => 500,
            'transaction_count' => 2,
            'void_total' => 0,
            'discount_total' => 0,
            'payment_breakdown' => ['cash' => 500],
        ]);

        $date = $generatedAt->toDateString();

        $this->actingAs($this->admin)
            ->get(route('readings.z', ['branch_id' => $this->branch->id, 'date' => $date]))
            ->assertOk()
            ->assertSee('Z-1')
            ->assertSee('Main Branch')
            ->assertDontSee('No Z-readings found');
    }

    public function test_backoffice_can_generate_z_reading_from_untagged_sales(): void
    {
        $this->createUntaggedSale();
        $date = now()->toDateString();

        $this->actingAs($this->admin)
            ->post(route('readings.z.generate'), [
                'branch_id' => $this->branch->id,
                'date' => $date,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('pos_z_readings', 1);
        $this->assertDatabaseHas('pos_sales', [
            'branch_id' => $this->branch->id,
            'z_reading_id' => PosZReading::first()->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('readings.z', ['branch_id' => $this->branch->id, 'date' => $date]))
            ->assertOk()
            ->assertSee('Z-1');
    }

    public function test_pos_api_generates_z_reading(): void
    {
        $this->createUntaggedSale();

        $this->actingAs($this->cashier)
            ->postJson('/api/pos/z-reading')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reading.z_count', 1);

        $this->assertDatabaseCount('pos_z_readings', 1);
    }

    public function test_reading_service_backfill_uses_sales_date(): void
    {
        $saleDate = '2026-05-28';
        PosSale::create([
            'sale_number' => 'ZBF001',
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 200,
            'discount_total' => 0,
            'total' => 200,
            'payment_method' => 'cash',
            'amount_tendered' => 200,
            'change_due' => 0,
            'status' => 'completed',
            'sold_at' => $saleDate . ' 14:00:00',
        ]);

        $reading = app(ReadingService::class)->generateZReadingForBranch(
            $this->branch->id,
            $this->cashier->id,
            null,
            \Carbon\Carbon::parse($saleDate, config('app.timezone'))->endOfDay(),
            $saleDate,
        );

        $this->assertSame(1, $reading->z_count);
        $this->assertSame('2026-05-28', $reading->generated_at->timezone(config('app.timezone'))->toDateString());

        $this->actingAs($this->admin)
            ->get(route('readings.z', ['branch_id' => $this->branch->id, 'date' => $saleDate]))
            ->assertOk()
            ->assertSee('Z-1');
    }
}
