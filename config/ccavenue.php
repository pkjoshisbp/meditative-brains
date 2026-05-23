<?php

return [
    'merchant_id' => env('CCAVENUE_MERCHANT_ID'),
    'access_code' => env('CCAVENUE_ACCESS_CODE'),
    'working_key' => env('CCAVENUE_WORKING_KEY'),
    'mode' => env('CCAVENUE_MODE', 'live'),

    'gateway_urls' => [
        'test' => 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
        'live' => 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
    ],
];