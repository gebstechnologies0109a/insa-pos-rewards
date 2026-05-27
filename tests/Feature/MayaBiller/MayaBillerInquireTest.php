<?php

namespace Tests\Feature\MayaBiller;

use App\Enums\MayaBillerState;
use App\Models\EPayPlus\MayaBillerTransaction;
use Database\Seeders\EPayPlusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MayaBillerHttp;
use Tests\TestCase;

class MayaBillerInquireTest extends TestCase
{
    use MayaBillerHttp;
    use RefreshDatabase;

    private const SECRET = 'test-maya-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMayaBillerForTests();
        config(['maya_biller.secret_key' => self::SECRET]);

        $this->seed(EPayPlusSeeder::class);
    }

    public function test_inquire_returns_transaction_by_rrn(): void
    {
        $rrn = 'rrn-inquire-001';

        MayaBillerTransaction::create([
            'request_reference_no' => $rrn,
            'maya_transaction_id' => 'MAYA-INQ-1',
            'state' => MayaBillerState::Posting,
            'biller_code' => 'MERALCO',
            'account_number' => '1234567890',
            'amount' => 1500,
            'fee' => 15,
            'currency' => 'PHP',
            'callback_url' => 'https://example.test/callback',
        ]);

        $response = $this->mayaPost('/api/maya-biller/v1/inquire', [
            'requestReferenceNo' => $rrn,
        ], 'rrn-inquire-header-001');

        $response->assertOk()
            ->assertJsonPath('result.code', '0000')
            ->assertJsonPath('requestReferenceNo', $rrn)
            ->assertJsonPath('status', MayaBillerState::Posting->value);
    }

    public function test_inquire_unknown_rrn_returns_404(): void
    {
        $response = $this->mayaPost('/api/maya-biller/v1/inquire', [
            'requestReferenceNo' => 'rrn-does-not-exist',
        ], 'rrn-inquire-miss');

        $response->assertStatus(404)
            ->assertJsonPath('result.code', '4040');
    }
}
