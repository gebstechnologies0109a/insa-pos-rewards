<?php

namespace Tests\Feature\EPayPlus;

use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use App\Models\User;
use App\Support\ManilaDateRange;
use Carbon\Carbon;
use Database\Seeders\EPayPlusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EPayPlusSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'epay-admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_dashboard_shows_today_and_pending_transactions(): void
    {
        $retailer = Retailer::where('account_id', 'EPDEMO001')->firstOrFail();

        $todayTxn = Transaction::create([
            'retailer_id' => $retailer->id,
            'type' => 'ELOAD',
            'reference_number' => 'DASH-TODAY-001',
            'provider_code' => 'GLOBE',
            'target_number' => '09171234567',
            'amount' => 100,
            'commission' => 5,
            'retailer_cost' => 95,
            'status' => 'SUCCESS',
            'completed_at' => now(),
        ]);

        $pendingTxn = Transaction::create([
            'retailer_id' => $retailer->id,
            'type' => 'BILLS',
            'reference_number' => 'DASH-PENDING-001',
            'provider_code' => 'MERALCO',
            'target_number' => '1234567890',
            'amount' => 500,
            'commission' => 0,
            'retailer_cost' => 500,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)->get(route('epayplus.dashboard'));

        $response->assertOk()
            ->assertSee('DASH-TODAY-001')
            ->assertSee('DASH-PENDING-001')
            ->assertSee('Demo ePayPlus Store');
    }

    public function test_transactions_index_includes_old_and_pending_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-28 12:00:00', ManilaDateRange::timezone()));

        $retailer = Retailer::where('account_id', 'EPDEMO001')->firstOrFail();

        $oldTxn = Transaction::create([
            'retailer_id' => $retailer->id,
            'type' => 'ELOAD',
            'reference_number' => 'IDX-OLD-001',
            'provider_code' => 'GLOBE',
            'target_number' => '09171234567',
            'amount' => 50,
            'commission' => 2,
            'retailer_cost' => 48,
            'status' => 'SUCCESS',
            'completed_at' => now()->subDays(120),
        ]);
        $oldTxn->created_at = now()->subDays(120);
        $oldTxn->saveQuietly();

        Transaction::create([
            'retailer_id' => $retailer->id,
            'type' => 'ECASH',
            'reference_number' => 'IDX-TODAY-001',
            'provider_code' => 'GCASH',
            'target_number' => '09171234567',
            'amount' => 200,
            'commission' => 10,
            'retailer_cost' => 190,
            'status' => 'PROCESSING',
            'created_at' => now(),
        ]);

        $defaultResponse = $this->actingAs($this->admin)->get(route('epayplus.transactions'));
        $defaultResponse->assertOk()
            ->assertSee('IDX-TODAY-001')
            ->assertDontSee('IDX-OLD-001');

        $allTimeResponse = $this->actingAs($this->admin)->get(route('epayplus.transactions', ['all' => 1]));
        $allTimeResponse->assertOk()
            ->assertSee('IDX-TODAY-001')
            ->assertSee('IDX-OLD-001');

        Carbon::setTestNow();
    }
}
