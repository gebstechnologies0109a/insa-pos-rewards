<?php

namespace App\Services\MayaBiller;

/**
 * Generates paymaya-signature values for local/testing outbound payloads.
 */
class MayaBillerSignatureGenerator
{
    public function __construct(
        private readonly MayaBillerSignatureVerifier $verifier
    ) {}

    public function forBody(string $rawBody, ?string $secretKey = null): string
    {
        $secretKey ??= (string) config('maya_biller.secret_key');

        return $this->verifier->generate($rawBody, $secretKey);
    }
}
