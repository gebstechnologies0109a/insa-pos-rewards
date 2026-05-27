<?php

namespace App\Services\MayaBiller;

use App\Models\EPayPlus\Blacklist;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;
use Carbon\Carbon;

class MayaBillerValidatePaymentService
{
    /**
     * Stateless bill payment validation (no database writes).
     *
     * @return array{code: string, message?: string}
     */
    public function validate(
        string $billerCode,
        string $accountNumber,
        float $amount,
        ?string $mobileNo = null,
        ?string $billExpiry = null,
        ?string $referenceExpiry = null,
        ?string $referenceNo = null,
        array $billingData = []
    ): array {
        $provider = $this->resolveBillerProvider($billerCode);

        if ($provider === null) {
            return $this->error('2559', 'Account Number is invalid');
        }

        if (! $this->isValidAccountNumber($accountNumber)) {
            return $this->error('2559', 'Account Number is invalid');
        }

        if (Blacklist::isBlocked('account', $accountNumber)) {
            return $this->error('2559', 'Account Number is invalid');
        }

        $amountError = $this->validateAmount($amount, $provider, $billerCode);
        if ($amountError !== null) {
            return $amountError;
        }

        $mobileError = $this->validateMobile($mobileNo, $provider);
        if ($mobileError !== null) {
            return $mobileError;
        }

        if ($billExpiry !== null && $this->isExpired($billExpiry)) {
            return $this->error('2596', 'Bill is expired');
        }

        if ($referenceExpiry !== null && $this->isExpired($referenceExpiry)) {
            return $this->error('2596', 'ReferenceNo is expired');
        }

        if ($this->requiresBillingData($provider) && $billingData === []) {
            return $this->error('2596', 'Billing data does not exist');
        }

        if ($referenceNo !== null && $this->isReferenceExpired($referenceNo, $provider)) {
            return $this->error('2596', 'ReferenceNo is expired');
        }

        return ['code' => '0000'];
    }

    protected function resolveBillerProvider(string $billerCode): ?Provider
    {
        $normalized = strtoupper(trim($billerCode));

        $map = config('maya_biller.biller_code_map', []);
        if (isset($map[$normalized])) {
            $normalized = strtoupper((string) $map[$normalized]);
        }

        $provider = Provider::query()
            ->active()
            ->ofType('BILLS')
            ->where('code', $normalized)
            ->first();

        if ($provider !== null) {
            return $provider;
        }

        $product = Product::query()
            ->active()
            ->ofType('BILLS')
            ->where(function ($query) use ($normalized) {
                $query->where('code', $normalized)
                    ->orWhere('code', $normalized.'_PAY');
            })
            ->first();

        return $product?->provider;
    }

    protected function isValidAccountNumber(string $accountNumber): bool
    {
        $min = (int) config('maya_biller.account_min_length', 4);
        $max = (int) config('maya_biller.account_max_length', 128);
        $length = strlen($accountNumber);

        if ($length < $min || $length > $max) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9\-]+$/', $accountNumber);
    }

    /**
     * @return array{code: string, message: string}|null
     */
    protected function validateAmount(float $amount, Provider $provider, string $billerCode): ?array
    {
        $min = (float) config('maya_biller.min_amount', 1);
        $max = (float) config('maya_biller.max_amount', 50000);

        if ($amount < $min || $amount > $max) {
            return $this->error('2596', 'Amount is invalid');
        }

        $product = Product::query()
            ->active()
            ->ofType('BILLS')
            ->whereHas('provider', fn ($q) => $q->where('id', $provider->id))
            ->where(function ($query) use ($billerCode) {
                $code = strtoupper(trim($billerCode));
                $query->where('code', $code)
                    ->orWhere('code', $code.'_PAY');
            })
            ->first();

        if ($product !== null && (float) $product->amount > 0) {
            if (abs($amount - (float) $product->amount) > 0.009) {
                return $this->error('2596', 'Amount is invalid');
            }
        }

        return null;
    }

    /**
     * @return array{code: string, message: string}|null
     */
    protected function validateMobile(?string $mobileNo, Provider $provider): ?array
    {
        $requiresMobile = (bool) data_get($provider->config, 'maya_requires_mobile', false);

        if ($mobileNo === null || $mobileNo === '') {
            return $requiresMobile
                ? $this->error('2596', 'MobileNo is invalid / required')
                : null;
        }

        if (! $this->isValidPhilippineMobile($mobileNo)) {
            return $this->error('2596', 'MobileNo is invalid / required');
        }

        if (Blacklist::isBlocked('phone', $this->normalizePhone($mobileNo))) {
            return $this->error('2596', 'MobileNo is invalid / required');
        }

        return null;
    }

    protected function isValidPhilippineMobile(string $mobile): bool
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '63')) {
            $digits = '0'.substr($digits, 2);
        }

        return (bool) preg_match('/^09\d{9}$/', $digits);
    }

    protected function normalizePhone(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '63')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    protected function isExpired(string $date): bool
    {
        try {
            return Carbon::parse($date)->endOfDay()->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    protected function requiresBillingData(Provider $provider): bool
    {
        return (bool) data_get($provider->config, 'maya_requires_billing_data', false);
    }

    protected function isReferenceExpired(string $referenceNo, Provider $provider): bool
    {
        $days = (int) data_get($provider->config, 'maya_reference_validity_days', 0);
        if ($days <= 0) {
            return false;
        }

        $product = $provider->products()
            ->active()
            ->ofType('BILLS')
            ->whereNotNull('validity_days')
            ->orderByDesc('validity_days')
            ->first();

        $validityDays = $product?->validity_days ?? $days;

        if (preg_match('/^(\d{8})(\d+)?$/', $referenceNo, $matches)) {
            try {
                $issuedAt = Carbon::createFromFormat('Ymd', $matches[1])->startOfDay();

                return $issuedAt->addDays($validityDays)->endOfDay()->isPast();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return array{code: string, message: string}
     */
    protected function error(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
