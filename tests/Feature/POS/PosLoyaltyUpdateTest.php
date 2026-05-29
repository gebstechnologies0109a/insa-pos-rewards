<?php

namespace Tests\Feature\POS;

use App\Models\POS\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesPosApi;
use Tests\TestCase;

class PosLoyaltyUpdateTest extends TestCase
{
    use AuthenticatesPosApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticatePosApi();
    }

    public function test_loyalty_update_adjusts_customer_points(): void
    {
        $customer = Customer::create([
            'uuid' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'card_number' => 'CARD-LOYALTY-1',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'loyalty_points' => 100,
            'status' => 'active',
        ]);

        $this->postJson('/api/pos/loyalty/update', [
            'customer_id' => $customer->id,
            'points_delta' => 25,
            'reason' => 'test_adjustment',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(125.0, (float) $customer->fresh()->loyalty_points);
    }
}
