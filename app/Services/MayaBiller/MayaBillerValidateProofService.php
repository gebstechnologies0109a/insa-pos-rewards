<?php

namespace App\Services\MayaBiller;

use Illuminate\Support\Facades\Cache;

class MayaBillerValidateProofService
{
    protected function cacheKey(string $requestReferenceNo): string
    {
        return 'maya_biller:validate:'.strtoupper(trim($requestReferenceNo));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function remember(string $requestReferenceNo, array $snapshot): void
    {
        $ttl = (int) config('maya_biller.validate_proof_ttl_minutes', 30);

        Cache::put(
            $this->cacheKey($requestReferenceNo),
            $snapshot,
            now()->addMinutes($ttl)
        );
    }

    public function hasProof(string $requestReferenceNo): bool
    {
        return Cache::has($this->cacheKey($requestReferenceNo));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProof(string $requestReferenceNo): ?array
    {
        $proof = Cache::get($this->cacheKey($requestReferenceNo));

        return is_array($proof) ? $proof : null;
    }

    public function forget(string $requestReferenceNo): void
    {
        Cache::forget($this->cacheKey($requestReferenceNo));
    }
}
