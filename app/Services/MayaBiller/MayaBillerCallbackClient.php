<?php

namespace App\Services\MayaBiller;

use App\Models\EPayPlus\MayaBillerTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MayaBillerCallbackClient
{
    public function baseUrl(): string
    {
        $env = config('maya_biller.environment', 'sandbox');

        return $env === 'production'
            ? (string) config('maya_biller.production_base_url')
            : (string) config('maya_biller.sandbox_base_url');
    }

    /**
     * Step 3: Send Posting Callback (Partner → Maya).
     *
     * Auth: Basic Base64(apiKey + ":") — password empty.
     * Header: Request-Reference-No
     * Body: result.code 0000 = fulfilled, other = posting failed (Maya refunds).
     */
    public function sendPostingCallback(
        MayaBillerTransaction $txn,
        bool $fulfilled = true
    ): MayaCallbackResult {
        $resultCode = $fulfilled ? '0000' : '9999';
        $url = $this->resolveCallbackUrl($txn);

        $payload = [
            'requestReferenceNo' => $txn->request_reference_no,
            'transactionId' => $txn->maya_transaction_id,
            'result' => [
                'code' => $resultCode,
            ],
        ];

        if (! config('maya_biller.enabled')) {
            return new MayaCallbackResult(
                fulfilled: $fulfilled,
                resultCode: $resultCode,
                httpStatus: 0,
                responseBody: ['skipped' => true, 'reason' => 'integration_disabled'],
                httpSuccessful: false,
                callbackUrl: $url,
            );
        }

        $apiKey = (string) config('maya_biller.callback_api_key');

        $response = Http::timeout((int) config('maya_biller.http_timeout', 30))
            ->withBasicAuth($apiKey, '')
            ->withHeaders([
                'Request-Reference-No' => $txn->request_reference_no,
            ])
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $body = $response->json();
        if (! is_array($body)) {
            $body = ['raw' => $response->body()];
        }

        if ($response->failed()) {
            Log::warning('Maya Biller posting callback failed', [
                'url' => $url,
                'request_reference_no' => $txn->request_reference_no,
                'status' => $response->status(),
                'body' => $body,
            ]);
        }

        return new MayaCallbackResult(
            fulfilled: $fulfilled,
            resultCode: $resultCode,
            httpStatus: $response->status(),
            responseBody: $body,
            httpSuccessful: $response->successful(),
            callbackUrl: $url,
        );
    }

    protected function resolveCallbackUrl(MayaBillerTransaction $txn): string
    {
        if ($txn->callback_url) {
            return $txn->callback_url;
        }

        return rtrim($this->baseUrl(), '/').config('maya_biller.callback_path');
    }
}
