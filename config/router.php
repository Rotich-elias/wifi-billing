<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Router driver
    |--------------------------------------------------------------------------
    |
    | "mock" keeps hotspot users in the local database and is safe for
    | development. "routeros" uses the MikroTik RouterOS API.
    |
    */
    'driver' => env('ROUTER_DRIVER', 'mock'),

    'host' => env('ROUTER_HOST', env('MIKROTIK_HOST', '192.168.88.1')),
    'port' => (int) env('ROUTER_API_PORT', env('MIKROTIK_PORT', 8728)),
    'user' => env('ROUTER_USER', env('MIKROTIK_USER', 'admin')),
    'password' => env('ROUTER_PASSWORD', env('MIKROTIK_PASSWORD', '')),
    'tls' => (bool) env('ROUTER_API_TLS', false),
    'tls_verify' => (bool) env('ROUTER_API_TLS_VERIFY', true),

    'hotspot_server' => env('ROUTER_HOTSPOT_SERVER', env('MIKROTIK_HOTSPOT_SERVER', 'hotspot1')),

    /*
    |--------------------------------------------------------------------------
    | Trusted hotspot login hosts
    |--------------------------------------------------------------------------
    |
    | MikroTik passes link-login-only to the portal. Only redirect customers to
    | hosts in this list (plus ROUTER_HOST) so a crafted portal link cannot
    | send a voucher code to an attacker-controlled site.
    |
    */
    'allowed_login_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ROUTER_ALLOWED_LOGIN_HOSTS', ''))
    ))),
];
