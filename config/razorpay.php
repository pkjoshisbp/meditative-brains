<?php

return [
    'key_id'         => env('RAZORPAY_KEY_ID'),
    'key_secret'     => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

    'currency' => 'INR',

    'return_url' => env('APP_URL') . '/payment/success',
    'cancel_url' => env('APP_URL') . '/payment/cancel',
    'webhook_url'=> env('APP_URL') . '/api/razorpay/webhook',
];
