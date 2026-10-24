<?php

return [
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'shortcode' => env('MPESA_SHORTCODE', '174379'),
    'passkey' => env('MPESA_PASSKEY'),
    'callback_url' => env('MPESA_CALLBACK_URL'),
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Callback authentication
    |--------------------------------------------------------------------------
    |
    | M-Pesa does not sign callback requests. Set MPESA_CALLBACK_SECRET to a
    | long random value (it is sent as a query parameter on the callback URL)
    | and optionally restrict callbacks to Safaricom's IP ranges via
    | MPESA_ALLOWED_IPS (comma separated CIDRs).
    |
    */
    'callback_secret' => env('MPESA_CALLBACK_SECRET'),
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MPESA_ALLOWED_IPS', ''))
    ))),
];
