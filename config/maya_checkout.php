<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maya Checkout (Pay with Maya / QR) — scaffold
    |--------------------------------------------------------------------------
    | Keys from Maya Business Manager (pbm.paymaya.com). Do not call negosyo-api.
    */

    'enabled' => (bool) env('MAYA_CHECKOUT_ENABLED', false),

    'demo_mode' => (bool) env('MAYA_CHECKOUT_DEMO_MODE', true),

    'environment' => env('MAYA_CHECKOUT_ENVIRONMENT', 'sandbox'),

    'public_key' => env('MAYA_CHECKOUT_PUBLIC_KEY', 'pk-placeholder-sandbox'),

    'secret_key' => env('MAYA_CHECKOUT_SECRET_KEY', 'sk-placeholder-sandbox'),

    'sandbox_base_url' => env(
        'MAYA_CHECKOUT_SANDBOX_BASE_URL',
        'https://pg-sandbox.paymaya.com'
    ),

    'production_base_url' => env(
        'MAYA_CHECKOUT_PRODUCTION_BASE_URL',
        'https://pg.paymaya.com'
    ),

    'checkout_path' => env('MAYA_CHECKOUT_PATH', '/checkout/v1/checkouts'),

    'redirect_success_url' => env('MAYA_CHECKOUT_SUCCESS_URL'),

    'redirect_failure_url' => env('MAYA_CHECKOUT_FAILURE_URL'),

    'redirect_cancel_url' => env('MAYA_CHECKOUT_CANCEL_URL'),

    'webhook_secret' => env('MAYA_CHECKOUT_WEBHOOK_SECRET'),

    'negosyo_package' => 'com.paymaya.negosyo',

    'business_package' => 'ph.maya.business.android',

    'deep_link_uri' => 'negosyo://',

    'business_deep_link' => 'https://www.maya.ph/business/app/',

    'negosyo_play_store' => 'https://play.google.com/store/apps/details?id=com.paymaya.negosyo',

    'business_play_store' => 'https://play.google.com/store/apps/details?id=ph.maya.business.android',

    'http_timeout' => (int) env('MAYA_CHECKOUT_HTTP_TIMEOUT', 30),

];
