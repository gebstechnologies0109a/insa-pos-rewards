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
    | Return HTTP 503 on Post when true (Maya will retry).
    */
    'maintenance' => (bool) env('MAYA_BILLER_MAINTENANCE', false),

    /*
    | HTTP status for successful Post acceptance (200 or 202).
    */
    'post_accept_status' => (int) env('MAYA_BILLER_POST_ACCEPT_STATUS', 202),

    /*
    | Minutes to remember a successful Validate RRN for Post revalidation gate.
    */
    'validate_proof_ttl_minutes' => (int) env('MAYA_BILLER_VALIDATE_PROOF_TTL', 30),

    /*
    | Require a prior successful Validate (RRN proof in cache) before Post.
    */
    'require_validate_proof' => (bool) env('MAYA_BILLER_REQUIRE_VALIDATE_PROOF', true),

    /*
    | Retailer account_id used for Maya-originated bill payment ledger rows.
    */
    'system_retailer_account_id' => env('MAYA_BILLER_SYSTEM_RETAILER', 'EPDEMO001'),

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

    /*
    | Maya Business Manager — settlement reports (partner reconciles FULFILLED txns).
    */
    'settlement_portal_url' => env(
        'MAYA_BILLER_SETTLEMENT_URL',
        'https://business.maya.ph'
    ),

    'system_retailer_account_id' => env('MAYA_BILLER_SYSTEM_RETAILER_ACCOUNT_ID', 'EPDEMO001'),

    'validate_proof_ttl_minutes' => (int) env('MAYA_BILLER_VALIDATE_PROOF_TTL_MINUTES', 30),

    'require_validate_proof' => (bool) env('MAYA_BILLER_REQUIRE_VALIDATE_PROOF', true),

    'post_accept_status' => (int) env('MAYA_BILLER_POST_ACCEPT_STATUS', 202),

    'public_base_url' => env(
        'MAYA_BILLER_PUBLIC_BASE_URL',
        'https://epayplus.diybizrewards.com'
    ),

    /*
    |--------------------------------------------------------------------------
    | Fee contract (Validate + optional Get Fee)
    |--------------------------------------------------------------------------
    | Maya requires fees on successful Validate so the app can build the payment
    | slip. Values must match the commercial contract signed with Maya RM.
    | Per-biller overrides take precedence, then epay_products.fee (BILLS), then default.
    */
    'fees' => [
        'contract_note' => env(
            'MAYA_BILLER_FEE_CONTRACT_NOTE',
            'Fees returned on Validate must match the Maya Partner Biller commercial agreement. Update biller_overrides or epay_products.fee before go-live.'
        ),

        'default' => [
            'convenience_fee' => (float) env('MAYA_BILLER_DEFAULT_CONVENIENCE_FEE', 0),
            'service_fee' => (float) env('MAYA_BILLER_DEFAULT_SERVICE_FEE', 5),
        ],

        /*
        | Keys: Maya billerCode or mapped epay provider code (uppercase).
        | Example: 'MERALCO' => ['convenience_fee' => 0, 'service_fee' => 15]
        */
        'biller_overrides' => [],
    ],

];
