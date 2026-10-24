<?php

return [
    'env' => env('MPESA_ENV', env('MPESA_ENVIRONMENT', 'sandbox')),
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'shortcode' => env('MPESA_SHORTCODE', '174379'),
    'passkey' => env('MPESA_PASSKEY'),

    /*
    |--------------------------------------------------------------------------
    | Callback
    |--------------------------------------------------------------------------
    |
    | The callback secret is part of the URL path, not a query string:
    | POST /api/mpesa/callback/{secret}
    |
    */
    'callback_base_url' => rtrim((string) env('MPESA_CALLBACK_BASE_URL', env('APP_URL')), '/'),
    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MPESA_ALLOWED_IPS', ''))
    ))),
];
