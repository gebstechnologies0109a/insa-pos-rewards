<?php

if (! function_exists('provider_code_to_slug')) {
    /**
     * Map epay provider code to ic_provider_{slug} filename slug.
     */
    function provider_code_to_slug(string $code): string
    {
        $map = [
            'GLOBE_BILL' => 'globe_bill',
            'GLOBE_POSTPAID' => 'globe_bill',
            'SMART_BILL' => 'smart_bill',
            'SMART_BRO' => 'smartbro',
            'SMARTBRO' => 'smartbro',
            'TALK_N_TEXT' => 'tnt',
            'COINSPH' => 'coinsph',
            'COINS' => 'coinsph',
            'PAYMAYA' => 'maya',
            'MWATER' => 'manila_water',
            'MANILA_WATER' => 'manila_water',
            'HCREDIT' => 'home_credit',
            'HOME_CREDIT' => 'home_credit',
            'GRABPAY' => 'grabpay',
            'SHOPEEPAY' => 'shopeepay',
            'RFID_ECARD' => 'rfid_ecard',
            'TAPNGO' => 'tapngo',
            'TAP_N_GO' => 'tapngo',
            'ECPAY_WALLET' => 'ecpay_wallet',
            'CCLEX_RFID' => 'cclex_rfid',
            'DITO_BILL' => 'dito',
            'INNOVE' => 'globe_bill',
            'ALING_PURING' => 'aling_puring_credits',
            'CIGNAL_BILL' => 'cignal',
        ];

        $upper = strtoupper(str_replace([' ', '-', '.'], '_', trim($code)));

        return $map[$upper] ?? strtolower($upper);
    }
}

if (! function_exists('provider_icon_url')) {
    /**
     * Public URL for a provider icon asset, or null if none on disk.
     */
    function provider_icon_url(?string $code, ?string $logoUrl = null): ?string
    {
        if ($logoUrl && str_starts_with($logoUrl, 'http')) {
            return $logoUrl;
        }

        if ($logoUrl && str_starts_with($logoUrl, '/')) {
            return $logoUrl;
        }

        if (! $code) {
            return null;
        }

        $slug = provider_code_to_slug($code);
        foreach (['webp', 'png'] as $ext) {
            $relative = "images/providers/ic_provider_{$slug}.{$ext}";
            if (file_exists(public_path($relative))) {
                return asset($relative);
            }
        }

        return null;
    }
}
