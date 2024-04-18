<?php

return [
    // Define mpesa environment
    'env' => env('MPESA_ENV', 'sandbox'),
    'debug' => env('APP_DEBUG', true),

    // Define mpesa shortcode
    'shortcode' => env('MPESA_SHORTCODE', '174379'),

    // Define mpesa credentials
    'consumer_key' => env('MPESA_CONSUMER_KEY', 'Y2YTrWs5H3aShqYP74BBXOS3L5eBVbt4'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', 'UNLJuzkZnciQonQE'),

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

    // ------------- b2b urls -------------
    'b2b_timeout_url' => env('MPESA_B2B_TIMEOUT_URL'),
    'b2b_result_url' => env('MPESA_B2B_RESULT_URL'),

    // ------------- bill manager optin urls -------------
    'bill_optin_callback_url' => env('MPESA_BILL_OPTIN_CALLBACK_URL'),

    // ------------- tax remmitance urls -------------
    'tax_remittance_timeout_url' => env('MPESA_TAX_REMITTANCE_TIMEOUT_URL'),
    'tax_remittance_result_url' => env('MPESA_TAX_REMITTANCE_RESULT_URL'),
];
