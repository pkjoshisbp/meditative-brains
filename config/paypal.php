<?php

return [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    
    'currency' => 'USD',
    'locale' => 'en_US',
    
    // URLs
    'return_url' => env('APP_URL') . '/payment/success',
    'cancel_url' => env('APP_URL') . '/payment/cancel',
    'webhook_url' => env('APP_URL') . '/api/paypal/webhook',
    
    // Subscription settings
    'trial_billing_cycles' => 1,
    'default_plan_setup_fee' => 0,
    
    // API URLs based on mode
    'api_url' => [
        'sandbox' => 'https://api.sandbox.paypal.com',
        'live' => 'https://api.paypal.com'
    ]
];
