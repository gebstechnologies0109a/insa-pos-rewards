<?php

namespace App\Services\MayaBiller;

readonly class MayaCallbackResult
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        public bool $fulfilled,
        public string $resultCode,
        public int $httpStatus,
        public ?array $responseBody,
        public bool $httpSuccessful,
        public string $callbackUrl,
    ) {}

    public function terminalState(): string
    {
        return $this->fulfilled ? 'FULFILLED' : 'POSTING_FAILED';
    }
}
