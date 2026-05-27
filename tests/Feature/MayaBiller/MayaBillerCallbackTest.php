<?php

namespace Tests\Feature\MayaBiller;

use App\Enums\MayaBillerState;
use App\Jobs\MayaBiller\ProcessMayaBillerPostingJob;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use Database\Seeders\EPayPlusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\MayaBillerHttp;
use Tests\TestCase;

class MayaBillerCallbackTest extends TestCase
{
    use MayaBillerHttp;
    use RefreshDatabase;

    private const SECRET = 'test-maya-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMayaBillerForTests();
        config([
            'maya_biller.secret_key' => self::SECRET,
            'maya_biller.callback_api_key' => 'sandbox-callback-key',
        ]);

        $this->seed(EPayPlusSeeder::class);
        Cache::flush();
    }

    public function test_posting_job_sends_callback_to_maya_url(): void
    {
        Http::fake([
            'https://pg-sandbox.paymaya.com/*' => Http::response(['result' => ['code' => '0000']], 200),
        ]);

        $rrn = 'rrn-callback-001';
        $callbackUrl = 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback';

        $this->mayaPost('/api/maya-biller/v1/validate', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1000,
        ], $rrn);

        $this->mayaPost('/api/maya-biller/v1/post', [
            'billerCode' => 'MERALCO',
            'accountNumber' => '1234567890',
            'amount' => 1000,
            'callbackUrl' => $callbackUrl,
            'transactionId' => 'MAYA-CB-001',
        ], $rrn);

        $txn = MayaBillerTransaction::where('request_reference_no', $rrn)->first();
        $this->assertNotNull($txn);

        if (! $txn->state->isTerminal()) {
            ProcessMayaBillerPostingJob::dispatchSync($txn->id);
            $txn->refresh();
        }

        $this->assertSame(MayaBillerState::Fulfilled, $txn->state);
        $this->assertNotNull($txn->callback_sent_at);

        Http::assertSent(function ($request) use ($callbackUrl, $rrn) {
            return $request->url() === $callbackUrl
                && $request['requestReferenceNo'] === $rrn
                && ($request['result']['code'] ?? null) === '0000';
        });
    }

    public function test_callback_marks_epay_transaction_success(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $txn = MayaBillerTransaction::create([
            'request_reference_no' => 'rrn-callback-epay',
            'maya_transaction_id' => 'MAYA-EPAY-1',
            'state' => MayaBillerState::Authorized,
            'biller_code' => 'MERALCO',
            'account_number' => '1234567890',
            'amount' => 500,
            'fee' => 15,
            'currency' => 'PHP',
            'callback_url' => 'https://pg-sandbox.paymaya.com/partners/v1/billers/transactions/callback',
        ]);

        $retailer = Retailer::where('account_id', 'EPDEMO001')->firstOrFail();
        $epayTxn = Transaction::create([
            'retailer_id' => $retailer->id,
            'type' => 'BILLS',
            'reference_number' => 'MBTEST001',
            'provider_code' => 'MERALCO',
            'target_number' => '1234567890',
            'amount' => 500,
            'fee' => 15,
            'commission' => 0,
            'retailer_cost' => 515,
            'status' => 'PROCESSING',
            'payment_method' => 'MAYA_BILLER',
            'balance_before' => $retailer->balance,
            'balance_after' => $retailer->balance,
        ]);
        $txn->update(['epay_transaction_id' => $epayTxn->id, 'state' => MayaBillerState::Posting]);

        ProcessMayaBillerPostingJob::dispatchSync($txn->id);

        $txn->refresh();
        $epayTxn->refresh();

        $this->assertSame(MayaBillerState::Fulfilled, $txn->state);
        $this->assertSame('SUCCESS', $epayTxn->status);
    }
}
