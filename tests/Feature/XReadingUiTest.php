<?php

namespace Tests\Feature;

use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosXReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XReadingUiTest extends TestCase
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
            'email' => 'admin-xreading@test.com',
            'password' => bcrypt('pw'),
            'role' => 'admin',
            'branch_id' => $this->branch->id,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier-xreading@test.com',
            'password' => bcrypt('pw'),
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function createXReadingWithSales(): PosXReading
    {
        $generatedAt = now()->startOfMinute();

        PosSale::create([
            'sale_number' => 'XRD001',
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 500,
            'discount_total' => 50,
            'total' => 450,
            'payment_method' => 'cash',
            'amount_tendered' => 500,
            'change_due' => 50,
            'status' => 'completed',
            'sold_at' => $generatedAt->copy()->subHour(),
        ]);

        PosSale::create([
            'sale_number' => 'XRD002',
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 200,
            'discount_total' => 0,
            'total' => 200,
            'payment_method' => 'gcash',
            'amount_tendered' => 200,
            'change_due' => 0,
            'status' => 'voided',
            'sold_at' => $generatedAt->copy()->subMinutes(30),
        ]);

        return PosXReading::create([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'generated_at' => $generatedAt,
            'total_sales' => 450,
            'transaction_count' => 1,
            'void_total' => 200,
            'discount_total' => 50,
            'payment_breakdown' => [
                'cash' => 450,
                'gcash' => 0,
                'maya' => 0,
                'debit_card' => 0,
                'credit_card' => 0,
                'palawanpay' => 0,
                'other' => 0,
            ],
        ]);
    }

    public function test_x_reading_index_has_view_links(): void
    {
        $reading = $this->createXReadingWithSales();

        $this->actingAs($this->admin)
            ->get(route('readings.x'))
            ->assertOk()
            ->assertSee(route('readings.x.show', $reading), false)
            ->assertSee('View');
    }

    public function test_x_reading_show_page_loads(): void
    {
        $reading = $this->createXReadingWithSales();

        $this->actingAs($this->admin)
            ->get(route('readings.x.show', $reading))
            ->assertOk()
            ->assertSee('X-Reading #' . $reading->id)
            ->assertSee('Reading Details')
            ->assertSee('Payment Breakdown')
            ->assertSee('Completed Sales')
            ->assertSee('Main Branch')
            ->assertSee('Cashier')
            ->assertSee('XRD001')
            ->assertSee('Voided Sales')
            ->assertSee('XRD002');
    }

    public function test_cashier_cannot_access_x_reading_show(): void
    {
        $reading = $this->createXReadingWithSales();

        $this->actingAs($this->cashier)
            ->get(route('readings.x.show', $reading))
            ->assertForbidden();
    }
}
