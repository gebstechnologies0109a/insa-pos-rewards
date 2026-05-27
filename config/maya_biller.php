<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maya Partner Biller API (scaffolding)
    |--------------------------------------------------------------------------
    | ePayPlus acts as Partner Biller: Maya sends Validate/Post/Inquire;
    | partner sends Posting Callback when internal posting completes.
    */

    'enabled' => (bool) env('MAYA_BILLER_ENABLED', false),

    /*
    | Skip paymaya-signature verification (local/testing only).
    */
    'skip_signature' => (bool) env('MAYA_BILLER_SKIP_SIGNATURE', false),

    'secret_key' => env('MAYA_BILLER_SECRET_KEY'),

    /*
    | Map Maya billerCode values to epay_providers.code (BILLS).
    | Example: 'EPAY-MERALCO' => 'MERALCO'
    */
    'biller_code_map' => [],

    'min_amount' => (float) env('MAYA_BILLER_MIN_AMOUNT', 1),

    'max_amount' => (float) env('MAYA_BILLER_MAX_AMOUNT', 50000),

    'account_min_length' => (int) env('MAYA_BILLER_ACCOUNT_MIN_LENGTH', 4),

    'account_max_length' => (int) env('MAYA_BILLER_ACCOUNT_MAX_LENGTH', 128),

    'callback_api_key' => env('MAYA_BILLER_CALLBACK_API_KEY'),

    'environment' => env('MAYA_BILLER_ENVIRONMENT', 'sandbox'),

    'sandbox_base_url' => env(
        'MAYA_BILLER_SANDBOX_BASE_URL',
        'https://pg-sandbox.paymaya.com'
    ),

    'production_base_url' => env(
        'MAYA_BILLER_PRODUCTION_BASE_URL',
        'https://pg.paymaya.com'
    ),

    /*
    | Relative path for outbound Send Posting Callback (adjust per Maya RM docs).
    */
    'callback_path' => env(
        'MAYA_BILLER_CALLBACK_PATH',
        '/partners/v1/billers/transactions/callback'
    ),

    'default_currency' => env('MAYA_BILLER_DEFAULT_CURRENCY', 'PHP'),

    'http_timeout' => (int) env('MAYA_BILLER_HTTP_TIMEOUT', 30),

];
