<?php

namespace Tests\Feature\MayaBiller;

use App\Enums\MayaBillerState;
use App\Jobs\MayaBiller\ProcessMayaBillerPostingJob;
use App\Models\EPayPlus\MayaBillerTransaction;
use Database\Seeders\EPayPlusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MayaBillerHttp;
use Tests\TestCase;

class MayaBillerPostTest extends TestCase
{
    use MayaBillerHttp;
    use RefreshDatabase;

    protected const MAYA_SECRET = 'test-maya-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMayaBillerForTests();
        config(['maya_biller.secret_key' => self::MAYA_SECRET]);

        $this->seed(EPayPlusSeeder::class);
    }

    public function test_post_after_validate_returns_202_without_outbound_http(): void
    {
        Queue::fake();
        Http::fake();

        $rrn = 'rrn-post-202-001';

        $this->mayaPost('/api/maya-biller/v1/validate', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
        ], $rrn)->assertOk()->assertJsonPath('result.code', '0000');

        $response = $this->mayaPost('/api/maya-biller/v1/post', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
            'callbackUrl' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
            'transactionId' => 'MAYA-TXN-001',
        ], $rrn);

        $response->assertStatus(202)
            ->assertJsonPath('resultCode', '0000')
            ->assertJsonPath('resultMessage', 'ACCEPTED')
            ->assertJsonPath('status', MayaBillerState::Authorized->value)
            ->assertJsonPath('queued', true);

        $this->assertDatabaseHas('epay_maya_biller_transactions', [
            'request_reference_no' => $rrn,
            'state' => MayaBillerState::Authorized->value,
        ]);

        Queue::assertPushed(ProcessMayaBillerPostingJob::class);
        Http::assertNothingSent();
    }

    public function test_duplicate_post_is_idempotent(): void
    {
        Queue::fake();

        $rrn = 'rrn-post-idempotent-001';
        $payload = [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 500,
            'callbackUrl' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
        ];

        $this->mayaPost('/api/maya-biller/v1/validate', $payload, $rrn);
        $first = $this->mayaPost('/api/maya-biller/v1/post', $payload, $rrn);
        $second = $this->mayaPost('/api/maya-biller/v1/post', $payload, $rrn);

        $first->assertStatus(202);
        $second->assertStatus(202)
            ->assertJsonPath('resultMessage', 'ALREADY_ACCEPTED')
            ->assertJsonPath('queued', false);

        $this->assertSame(1, MayaBillerTransaction::where('request_reference_no', $rrn)->count());
        Queue::assertPushed(ProcessMayaBillerPostingJob::class, 1);
    }

    public function test_post_without_prior_validate_returns_acq018(): void
    {
        $response = $this->mayaPost('/api/maya-biller/v1/post', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
            'callbackUrl' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
        ], 'rrn-no-validate');

        $response->assertStatus(400)
            ->assertJsonPath('result.code', 'ACQ018');
    }

    public function test_post_with_invalid_amount_returns_2596(): void
    {
        $rrn = 'rrn-post-bad-amount';

        $this->mayaPost('/api/maya-biller/v1/validate', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
        ], $rrn);

        $response = $this->mayaPost('/api/maya-biller/v1/post', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 0,
            'callbackUrl' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
        ], $rrn);

        $response->assertStatus(400)
            ->assertJsonPath('result.code', '2596');
    }

    public function test_post_with_invalid_account_returns_2559(): void
    {
        $rrn = 'rrn-post-invalid-account';

        $this->mayaPost('/api/maya-biller/v1/validate', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
        ], $rrn);

        $response = $this->mayaPost('/api/maya-biller/v1/post', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '12',
            'amount' => 1500,
            'callbackUrl' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
        ], $rrn);

        $response->assertStatus(400)
            ->assertJsonPath('result.code', '2559');

        $this->assertDatabaseMissing('epay_maya_biller_transactions', [
            'request_reference_no' => $rrn,
        ]);
    }

    public function test_bad_signature_returns_acq018(): void
    {
        $body = json_encode([
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1500,
            'callbackUrl' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
        ]);

        $response = $this->call(
            'POST',
            '/api/maya-biller/v1/post',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Request-Reference-No' => 'rrn-post-bad-sig',
                'HTTP_paymaya-signature' => 'invalid-signature',
            ],
            $body
        );

        $response->assertOk()
            ->assertJsonPath('result.code', 'ACQ018');
    }
}
