<?php

use App\Support\ProductMode;
use Illuminate\Http\Request;

if (! function_exists('current_product')) {
    /**
     * @return 'insa'|'epayplus'
     */
    function current_product(?Request $request = null): string
    {
        return ProductMode::currentProduct($request);
    }
}

if (! function_exists('is_epayplus_product')) {
    function is_epayplus_product(?Request $request = null): bool
    {
        return ProductMode::isEpayPlusHost($request);
    }
}

if (! function_exists('is_insa_product')) {
    function is_insa_product(?Request $request = null): bool
    {
        return ProductMode::isInsaHost($request);
    }
}

if (! function_exists('is_insa_android_app')) {
    /**
     * True when the request comes from an INSA POS Android WebView shell.
     */
    function is_insa_android_app(?Request $request = null): bool
    {
        $request ??= request();

        if ($request === null) {
            return false;
        }

        if ($request->boolean('android') || $request->query('android') === '1') {
            return true;
        }

        $ua = strtolower($request->userAgent() ?? '');

        return str_contains($ua, 'insaposv3/')
            || str_contains($ua, 'insaposv2/')
            || str_contains($ua, 'insapos/')
            || str_contains($ua, 'insaposlight/')
            || str_contains($ua, 'insabuddy/');
    }
}

if (! function_exists('login_error_message')) {
    /**
     * Human-readable login page message for ?error= codes (WebView-friendly).
     */
    function login_error_message(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            'auth_required' => 'Please sign in to continue.',
            'session_required', 'session_expired', 'session_lost' => 'Your session was not kept after sign-in. This often happens when the app switches between HTTP and HTTPS. Please sign in again. If it keeps happening, ask your administrator to use a stable HTTPS URL.',
            'forbidden_role' => 'This account is not allowed to open the POS cashier screen.',
            'license_inactive' => 'Your branch license is inactive. Contact your administrator before using POS.',
            'csrf' => 'Security token expired. Please try signing in again.',
            default => 'Unable to sign in. Please try again or contact support.',
        };
    }
}

if (! function_exists('login_redirect_params')) {
    /**
     * @return array<string, string>
     */
    function login_redirect_params(?Request $request, string $error = 'auth_required'): array
    {
        if ($request !== null && is_insa_android_app($request)) {
            return ['error' => $error];
        }

        return is_insa_android_app() ? ['error' => $error] : [];
    }
}

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
            'GCASH_PERA_OUTLET' => 'gcash',
            'ABSCBNMOB' => 'cignal',
            'BAYANTEL' => 'pldt',
            'DAVAOLIGHT' => 'meralco',
            'BENECO' => 'meralco',
            'CEPALCO' => 'meralco',
            'ANGELES_ELECTRIC' => 'meralco',
            'PENELCO' => 'meralco',
            'DANECO' => 'meralco',
            'CEBECO1' => 'veco',
            'CEBECO2' => 'veco',
            'CEBECO3' => 'veco',
            'PELCO1' => 'meralco',
            'PELCO2' => 'meralco',
            'SFELAPCO' => 'meralco',
            'FLECO' => 'meralco',
            'NEECO1' => 'meralco',
            'NEECO2_AREA1' => 'meralco',
            'QUEZELCO1' => 'meralco',
            'QUEZELCO2' => 'meralco',
            'DECORP' => 'meralco',
            'ZAMCELCO' => 'meralco',
            'LAGUNAWATER' => 'maynilad',
            'BORACAYWATER' => 'maynilad',
            'CLARKWATER' => 'manila_water',
            'LAGUNA_WATER_DISTRICT' => 'maynilad',
            'BP_WATERWORKS' => 'maynilad',
            'STA_LUCIA_WATER' => 'maynilad',
            'STREAMTECH' => 'sky',
            'CABLELINK' => 'sky',
            'GALAXY_CABLE' => 'sky',
            'NOW_CORP' => 'converge',
            'PARASAT' => 'sky',
            'DFA' => 'sss',
            'LTO' => 'nbi',
            'PSA' => 'sss',
            'BIR' => 'sss',
            'LTFRB' => 'sss',
            'MARINA' => 'sss',
            'PEZA' => 'sss',
            'TIEZA' => 'pagibig',
            'MYEG' => 'sss',
            'INSULAR_LIFE' => 'sunlife',
            'GENERALI' => 'axa',
            'COCOLIFE' => 'sunlife',
            'PARAMOUNT' => 'prulife',
            'STANDARD_INSURANCE' => 'axa',
            'PHILLIFE' => 'prulife',
            'TONIK' => 'maya',
            'CASHALO' => 'home_credit',
            'AEON' => 'home_credit',
            'TALA' => 'home_credit',
            'UNIONDIGITAL' => 'bdo',
            'SKYPAY_LOAN' => 'home_credit',
            'SB_FINANCE' => 'bpi',
            'CHINATRUST_LOAN' => 'bpi',
            'GLOBAL_DOMINION' => 'home_credit',
            'ASIALINK' => 'home_credit',
            'CHINABANK_CC' => 'bpi',
            'AUB_CC' => 'bpi',
            'SECURITYBANK_CC' => 'bpi',
            'UNIONBANK_CC' => 'bdo',
            'ROBINSONSBANK_CC' => 'metrobank_cc',
            'BOC_CC' => 'bpi',
            'BEEP' => 'gcash',
            'PAL' => 'maya',
            'CEBUPACIFIC' => 'maya',
            'AIRASIA' => 'grabpay',
            'DRAGONPAY' => 'ecpay_wallet',
            'PESOPAY' => 'ecpay_wallet',
            'MULTIPAY' => 'ecpay_wallet',
            'PHINMA_EDUCATION' => 'sss',
            'MAPUA' => 'sss',
            'BRIA_HOMES' => 'camella',
            'AVIDA' => 'camella',
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
