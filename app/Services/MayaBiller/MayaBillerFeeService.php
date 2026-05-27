<?php

namespace App\Services\MayaBiller;

use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;

class MayaBillerFeeService
{
    /**
     * Compute Maya Validate / Get Fee response fees.
     *
     * Priority: per-biller config override → epay_products.fee → config default.
     * epay_product_pricing is retailer-specific; Maya consumer flow uses product fee only.
     *
     * @return array{convenienceFee: float, serviceFee: float, totalFee: float}
     */
    public function compute(string $billerCode, float $amount = 0): array
    {
        $normalized = strtoupper(trim($billerCode));
        $map = config('maya_biller.biller_code_map', []);
        if (isset($map[$normalized])) {
            $normalized = strtoupper((string) $map[$normalized]);
        }

        $override = config("maya_biller.fees.biller_overrides.{$normalized}");
        if (is_array($override)) {
            return $this->formatFees(
                (float) ($override['convenience_fee'] ?? $override['convenienceFee'] ?? 0),
                (float) ($override['service_fee'] ?? $override['serviceFee'] ?? 0),
                $amount
            );
        }

        $productFee = $this->resolveProductServiceFee($normalized, $billerCode);
        if ($productFee !== null) {
            $convenience = (float) config('maya_biller.fees.default.convenience_fee', 0);

            return $this->formatFees($convenience, $productFee, $amount);
        }

        $default = config('maya_biller.fees.default', []);

        return $this->formatFees(
            (float) ($default['convenience_fee'] ?? 0),
            (float) ($default['service_fee'] ?? 0),
            $amount
        );
    }

    /**
     * @return array{convenienceFee: float, serviceFee: float, totalFee: float}
     */
    protected function formatFees(float $convenienceFee, float $serviceFee, float $amount = 0): array
    {
        unset($amount);

        $convenienceFee = round(max(0, $convenienceFee), 2);
        $serviceFee = round(max(0, $serviceFee), 2);
        $totalFee = round($convenienceFee + $serviceFee, 2);

        return [
            'convenienceFee' => $convenienceFee,
            'serviceFee' => $serviceFee,
            'totalFee' => $totalFee,
        ];
    }

    protected function resolveProductServiceFee(string $normalizedProviderCode, string $originalBillerCode): ?float
    {
        $product = Product::query()
            ->active()
            ->ofType('BILLS')
            ->where(function ($query) use ($normalizedProviderCode, $originalBillerCode) {
                $codes = array_unique([
                    strtoupper(trim($originalBillerCode)),
                    $normalizedProviderCode,
                    $normalizedProviderCode.'_PAY',
                ]);
                $query->whereIn('code', $codes);
            })
            ->whereHas('provider', fn ($q) => $q->active()->ofType('BILLS'))
            ->orderByDesc('fee')
            ->first();

        if ($product === null) {
            $provider = Provider::query()
                ->active()
                ->ofType('BILLS')
                ->where('code', $normalizedProviderCode)
                ->first();

            if ($provider === null) {
                return null;
            }

            $product = $provider->products()
                ->active()
                ->ofType('BILLS')
                ->orderByDesc('fee')
                ->first();
        }

        if ($product === null || (float) $product->fee <= 0) {
            return null;
        }

        return (float) $product->fee;
    }
}
