<?php

namespace Tests\Support;

use App\Services\MayaBiller\MayaBillerSignatureVerifier;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

trait MayaBillerHttp
{
    protected const MAYA_SECRET = 'test-maya-secret';

    protected function mayaSecret(): string
    {
        return static::MAYA_SECRET;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function mayaPost(string $path, array $payload, string $requestReferenceNo): TestResponse
    {
        $body = json_encode($payload);
        $signature = (new MayaBillerSignatureVerifier)->generate($body, $this->mayaSecret());

        return $this->call(
            'POST',
            $path,
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

    protected function configureMayaBillerForTests(): void
    {
        config([
            'maya_biller.enabled' => true,
            'maya_biller.maintenance' => false,
            'maya_biller.secret_key' => $this->mayaSecret(),
            'maya_biller.skip_signature' => false,
            'maya_biller.require_validate_proof' => true,
            'maya_biller.min_amount' => 1,
            'maya_biller.max_amount' => 50000,
            'maya_biller.post_accept_status' => 202,
            'maya_biller.system_retailer_account_id' => 'EPDEMO001',
            'maya_biller.fees.default' => [
                'convenience_fee' => 0,
                'service_fee' => 5,
            ],
            'maya_biller.fees.biller_overrides' => [],
        ]);
    }
}
