<?php

namespace App\Services\MayaBiller;

use Illuminate\Http\Client\Response;
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
     * Send Posting Callback to Maya (Partner → Maya).
     *
     * Authorization: Basic Base64("{apiKey}:") — password empty per Maya spec.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendPostingCallback(array $payload): Response
    {
        $apiKey = (string) config('maya_biller.callback_api_key');
        $url = rtrim($this->baseUrl(), '/').config('maya_biller.callback_path');

        $response = Http::timeout((int) config('maya_biller.http_timeout', 30))
            ->withBasicAuth($apiKey, '')
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::warning('Maya Biller posting callback failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        return $response;
    }
}
