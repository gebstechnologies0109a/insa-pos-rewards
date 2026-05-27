<?php

namespace Tests\Feature\MayaBiller;

use App\Models\EPayPlus\Blacklist;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Services\MayaBiller\MayaBillerSignatureVerifier;
use Database\Seeders\EPayPlusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MayaBillerValidateTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-maya-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maya_biller.enabled' => true,
            'maya_biller.secret_key' => self::SECRET,
            'maya_biller.skip_signature' => false,
            'maya_biller.min_amount' => 1,
            'maya_biller.max_amount' => 50000,
            'maya_biller.fees.default' => [
                'convenience_fee' => 0,
                'service_fee' => 5,
            ],
            'maya_biller.fees.biller_overrides' => [],
        ]);

        $this->seed(EPayPlusSeeder::class);
    }

    public function test_valid_payload_returns_0000_without_db_write(): void
    {
        $before = MayaBillerTransaction::count();

        $payload = [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
            'currency' => 'PHP',
        ];

        $response = $this->postValidate($payload, 'rrn-valid-001');

        $response->assertOk()
            ->assertJsonPath('result.code', '0000')
            ->assertJsonPath('fees.serviceFee', 15)
            ->assertJsonPath('fees.totalFee', 15);

        $this->assertSame($before, MayaBillerTransaction::count());
    }

    public function test_get_fee_returns_fees_in_maya_shape(): void
    {
        $body = json_encode([
            'billerCode' => 'MERALCO',
            'amount' => 500,
        ]);
        $verifier = new MayaBillerSignatureVerifier;
        $signature = $verifier->generate($body, self::SECRET);

        $response = $this->call(
            'POST',
            '/api/maya-biller/v1/fee',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Request-Reference-No' => 'rrn-get-fee-001',
                'HTTP_paymaya-signature' => $signature,
            ],
            $body
        );

        $response->assertOk()
            ->assertJsonPath('result.code', '0000')
            ->assertJsonPath('fees.serviceFee', 15);
    }

    public function test_invalid_account_returns_2559(): void
    {
        $response = $this->postValidate([
            'billerCode' => 'MERALCO',
            'accountNumber' => '12',
            'amount' => 1500,
        ], 'rrn-invalid-account');

        $response->assertOk()
            ->assertJson([
                'result' => [
                    'code' => '2559',
                    'message' => 'Account Number is invalid',
                ],
            ]);
    }

    public function test_unknown_biller_returns_2559(): void
    {
        $response = $this->postValidate([
            'billerCode' => 'UNKNOWN_BILLER_XYZ',
            'accountNumber' => '1234567890',
            'amount' => 1500,
        ], 'rrn-unknown-biller');

        $response->assertOk()
            ->assertJsonPath('result.code', '2559');
    }

    public function test_invalid_amount_returns_2596(): void
    {
        $response = $this->postValidate([
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 0,
        ], 'rrn-invalid-amount');

        $response->assertOk()
            ->assertJson([
                'result' => [
                    'code' => '2596',
                    'message' => 'Amount is invalid',
                ],
            ]);
    }

    public function test_bad_signature_returns_acq018(): void
    {
        $body = json_encode([
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
        ]);

        $response = $this->call(
            'POST',
            '/api/maya-biller/v1/validate',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Request-Reference-No' => 'rrn-bad-sig',
                'HTTP_paymaya-signature' => 'invalid-signature',
            ],
            $body
        );

        $response->assertOk()
            ->assertJson([
                'result' => [
                    'code' => 'ACQ018',
                    'message' => 'Maya signature mismatch.',
                ],
            ]);
    }

    public function test_disabled_integration_returns_acq018(): void
    {
        config(['maya_biller.enabled' => false]);

        $response = $this->postValidate([
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
        ], 'rrn-disabled');

        $response->assertOk()
            ->assertJsonPath('result.code', 'ACQ018');
    }

    public function test_blacklisted_account_returns_2559(): void
    {
        Blacklist::create([
            'type' => 'account',
            'value' => '9999999999',
            'reason' => 'Test block',
            'is_active' => true,
            'blocked_at' => now(),
        ]);

        $response = $this->postValidate([
            'billerCode' => 'MERALCO',
            'accountNumber' => '9999999999',
            'amount' => 1500,
        ], 'rrn-blacklisted');

        $response->assertOk()->assertJsonPath('result.code', '2559');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postValidate(array $payload, string $requestReferenceNo): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $verifier = new MayaBillerSignatureVerifier;
        $signature = $verifier->generate($body, self::SECRET);

        return $this->call(
            'POST',
            '/api/maya-biller/v1/validate',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Request-Reference-No' => $requestReferenceNo,
                'HTTP_paymaya-signature' => $signature,
            ],
            $body
        );
    }
}
