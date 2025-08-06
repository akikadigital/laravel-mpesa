<?php

return [
    // Define mpesa environment
    'env' => env('MPESA_ENV', 'sandbox'),
    'debug' => env('MPESA_APP_DEBUG', true),
    'url' => [
        'sandbox' => env('MPESA_SANDBOX_URL', 'https://sandbox.safaricom.co.ke'),
        'production' => env('MPESA_PRODUCTION_URL', 'https://api.safaricom.co.ke'),
    ],

    // Define mpesa shortcode
    'shortcode' => env('MPESA_SHORTCODE', '174379'),

    // Define mpesa credentials
    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    // ------------- c2b configs -------------
    'stk_passkey' => env('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),
    'initiator_name' => env('MPESA_INITIATOR_NAME', 'testapi'),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),
    'stk_validation_url' => env('MPESA_STK_VALIDATION_URL'),
    'stk_confirmation_url' => env('MPESA_STK_CONFIRMATION_URL'),
    'stk_callback_url' => env('MPESA_STK_CALLBACK_URL'),

    // ------------- c2b reversal urls -------------
    'reversal_timeout_url' => env('MPESA_REVERSAL_TIMEOUT_URL'),
    'reversal_result_url' => env('MPESA_REVERSAL_RESULT_URL'),

    // ------------- balance -------------
    'balance_result_url' => env('MPESA_BALANCE_RESULT_URL'),
    'balance_timeout_url' => env('MPESA_BALANCE_TIMEOUT_URL'),

    // ------------- Transaction status urls -------------
    'transaction_status_result_url' => env('MPESA_TRANSACTION_STATUS_RESULT_URL'),
    'transaction_status_timeout_url' => env('MPESA_TRANSACTION_STATUS_TIMEOUT_URL'),

    // ------------- b2c urls -------------
    'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
    'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),
    'b2c_topup_timeout_url' => env('MPESA_B2C_TOPUP_TIMEOUT_URL'),
    'b2c_topup_result_url' => env('MPESA_B2C_TOPUP_RESULT_URL'),

    // ------------- b2b urls -------------
    'b2b_timeout_url' => env('MPESA_B2B_TIMEOUT_URL'),
    'b2b_result_url' => env('MPESA_B2B_RESULT_URL'),
    'b2b_express_checkout_url' => env('MPESA_B2B_EXPRESS_CHECKOUT_URL'),

    // ------------- bill manager optin urls -------------
    'logo_url' => env('MPESA_BILL_MANAGER_LOGO_URL'),
    'bill_optin_callback_url' => env('MPESA_BILL_MANAGER_OPTIN_CALLBACK_URL'),

    // ------------- tax remmitance urls -------------
    'tax_remittance_timeout_url' => env('MPESA_TAX_REMITTANCE_TIMEOUT_URL'),
    'tax_remittance_result_url' => env('MPESA_TAX_REMITTANCE_RESULT_URL'),

    // ------------- mpesa ratiba urls -------------
    'ratiba_callback_url' => env('MPESA_RATIBA_CALLBACK_URL'),
];
