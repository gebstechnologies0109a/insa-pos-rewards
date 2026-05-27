<?php

namespace Tests\Unit;

use App\Services\MayaBiller\MayaBillerSignatureVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MayaBillerSignatureVerifierTest extends TestCase
{
    #[Test]
    public function it_verifies_paymaya_signature_from_raw_body_and_secret(): void
    {
        $verifier = new MayaBillerSignatureVerifier;
        $body = '{"billerCode":"MERALCO","accountNumber":"1234567890","amount":500}';
        $secret = 'test-secret-key-do-not-use-in-production';

        $signature = base64_encode(hash('sha256', $body.$secret, true));

        $this->assertTrue($verifier->verify($body, $signature, $secret));
    }

    #[Test]
    public function it_rejects_invalid_signature(): void
    {
        $verifier = new MayaBillerSignatureVerifier;
        $body = '{"amount":100}';
        $secret = 'secret';

        $this->assertFalse($verifier->verify($body, 'invalid-signature', $secret));
        $this->assertFalse($verifier->verify($body, null, $secret));
        $this->assertFalse($verifier->verify($body, 'abc', null));
    }
}
