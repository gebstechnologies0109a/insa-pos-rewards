<?php

namespace App\Services\MayaBiller;

class MayaBillerSignatureVerifier
{
    /**
     * Verify inbound Maya `paymaya-signature` header.
     *
     * Expected: Base64(SHA256(raw request body concatenated with secret key))
     * Align exact stringification with Maya RM documentation during go-live.
     */
    public function verify(string $rawBody, ?string $signature, ?string $secretKey): bool
    {
        if ($signature === null || $signature === '' || $secretKey === null || $secretKey === '') {
            return false;
        }

        $expected = $this->generate($rawBody, $secretKey);

        return hash_equals($expected, $signature);
    }

    public function generate(string $rawBody, string $secretKey): string
    {
        return base64_encode(hash('sha256', $rawBody.$secretKey, true));
    }
}
