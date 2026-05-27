<?php

namespace Tests\Unit;

use App\Enums\MayaBillerState;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Services\MayaBiller\MayaBillerCallbackClient;
use App\Services\MayaBiller\MayaBillerTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MayaBillerCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_callback_uses_basic_auth_and_rrn_header(): void
    {
        config([
            'maya_biller.enabled' => true,
            'maya_biller.callback_api_key' => 'test-callback-key',
            'maya_biller.environment' => 'sandbox',
            'maya_biller.sandbox_base_url' => 'https://pg-sandbox.paymaya.com',
            'maya_biller.callback_path' => '/partners/v1/billers/transactions/callback',
        ]);

        $callbackUrl = 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback';

        Http::fake([
            $callbackUrl => Http::response(['result' => ['code' => '0000']], 200),
        ]);

        $txn = MayaBillerTransaction::create([
            'request_reference_no' => 'RRN-CALLBACK-001',
            'maya_transaction_id' => 'MAYA-TXN-001',
            'state' => MayaBillerState::Posting,
            'biller_code' => 'MERALCO',
            'account_number' => '1234567890',
            'amount' => 1500,
            'fee' => 5,
            'currency' => 'PHP',
            'callback_url' => $callbackUrl,
        ]);

        $client = app(MayaBillerCallbackClient::class);
        $result = $client->sendPostingCallback($txn, fulfilled: true);

        $this->assertTrue($result->fulfilled);
        $this->assertSame('0000', $result->resultCode);
        $this->assertTrue($result->httpSuccessful);

        Http::assertSent(function ($request) use ($callbackUrl) {
            $expectedAuth = 'Basic '.base64_encode('test-callback-key:');

            return $request->url() === $callbackUrl
                && $request->hasHeader('Authorization', $expectedAuth)
                && $request->hasHeader('Request-Reference-No', 'RRN-CALLBACK-001')
                && $request['result']['code'] === '0000';
        });

        $service = app(MayaBillerTransactionService::class);
        $service->applyCallbackResult($txn, $result);

        $txn->refresh();
        $this->assertSame(MayaBillerState::Fulfilled, $txn->state);
        $this->assertNotNull($txn->callback_sent_at);
        $this->assertSame('0000', $txn->callback_response['resultCode'] ?? null);
    }

    public function test_non_zero_callback_marks_posting_failed(): void
    {
        config([
            'maya_biller.enabled' => true,
            'maya_biller.callback_api_key' => 'test-callback-key',
        ]);

        $callbackUrl = 'https://pg-sandbox.paymaya.com/callback';

        Http::fake([
            $callbackUrl => Http::response(['result' => ['code' => '0000']], 200),
        ]);

        $txn = MayaBillerTransaction::create([
            'request_reference_no' => 'RRN-CALLBACK-002',
            'state' => MayaBillerState::Posting,
            'biller_code' => 'MERALCO',
            'account_number' => '1234567890',
            'amount' => 500,
            'fee' => 0,
            'currency' => 'PHP',
            'callback_url' => $callbackUrl,
        ]);

        $client = app(MayaBillerCallbackClient::class);
        $result = $client->sendPostingCallback($txn, fulfilled: false);

        $this->assertFalse($result->fulfilled);
        $this->assertSame('9999', $result->resultCode);

        app(MayaBillerTransactionService::class)->applyCallbackResult($txn, $result);

        $this->assertSame(MayaBillerState::PostingFailed, $txn->fresh()->state);
    }
}
