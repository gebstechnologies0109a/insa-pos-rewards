<?php

namespace App\Support;

use Illuminate\Http\Request;

class ProductMode
{
    public const PRODUCT_INSA = 'insa';

    public const PRODUCT_EPAYPLUS = 'epayplus';

    public static function configuredProduct(): string
    {
        return strtolower((string) config('product.name', 'auto'));
    }

    /**
     * @return self::PRODUCT_INSA|self::PRODUCT_EPAYPLUS
     */
    public static function currentProduct(?Request $request = null): string
    {
        $configured = self::configuredProduct();

        if ($configured === self::PRODUCT_INSA || $configured === self::PRODUCT_EPAYPLUS) {
            return $configured;
        }

        $request ??= request();

        if ($request !== null) {
            $host = self::normalizeHost($request->getHost());

            if (self::hostMatchesList($host, config('product.epayplus_hosts', []))) {
                return self::PRODUCT_EPAYPLUS;
            }

            if (self::hostMatchesList($host, config('product.insa_hosts', []))) {
                return self::PRODUCT_INSA;
            }

            if (str_contains($host, 'epayplus')) {
                return self::PRODUCT_EPAYPLUS;
            }
        }

        return self::PRODUCT_INSA;
    }

    public static function isEpayPlusHost(?Request $request = null): bool
    {
        return self::currentProduct($request) === self::PRODUCT_EPAYPLUS;
    }

    public static function isInsaHost(?Request $request = null): bool
    {
        return self::currentProduct($request) === self::PRODUCT_INSA;
    }

    /**
     * @param  list<string>  $hosts
     */
    private static function hostMatchesList(string $host, array $hosts): bool
    {
        foreach ($hosts as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }

            if ($host === $pattern || str_ends_with($host, '.'.$pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(explode(':', $host)[0]);
    }
}
