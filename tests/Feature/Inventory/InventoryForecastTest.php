<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\StockMovement;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Models\User;
use App\Services\Inventory\InventoryForecastService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class InventoryForecastTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected Branch $branch;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
        $this->branch = Branch::find(1);
        $this->product = Product::first();
    }

    public function test_forecast_service_suggests_reorder_from_sales_velocity(): void
    {
        app(InventoryService::class)->stockIn($this->branch->id, [
            ['product_id' => $this->product->id, 'qty' => 5],
        ]);

        StockMovement::create([
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
            'type'       => 'sale',
            'qty'        => -30,
            'created_at' => now()->subDays(10),
        ]);

        $rows = app(InventoryForecastService::class)->forecastReport($this->branch->id, 30, 14);
        $row = collect($rows)->firstWhere('product_id', $this->product->id);

        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row['sold_period']);
        $this->assertGreaterThan($row['current_stock'], $row['suggested_reorder']);
    }

    public function test_slow_moving_lists_stock_without_recent_sales(): void
    {
        app(InventoryService::class)->stockIn($this->branch->id, [
            ['product_id' => $this->product->id, 'qty' => 12],
        ]);

        $slow = app(InventoryForecastService::class)->slowMovingProducts($this->branch->id, 60);
        $match = collect($slow)->first(fn ($r) => $r['product']->id === $this->product->id);

        $this->assertNotNull($match);
        $this->assertEquals(12, $match['stock']);
    }

    public function test_backoffice_forecast_page_loads_for_manager(): void
    {
        $manager = User::factory()->create([
            'role'      => 'manager',
            'branch_id' => 1,
        ]);
        $this->actingAs($manager);

        $this->get(route('backoffice.inventory.forecast', ['branch_id' => 1]))
            ->assertOk()
            ->assertSee('Reorder Forecast');
    }
}
