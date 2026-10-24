<?php

return [
    'host' => env('ROUTER_HOST', env('MIKROTIK_HOST', '192.168.88.1')),
    'port' => (int) env('ROUTER_API_PORT', env('MIKROTIK_PORT', 8728)),
    'user' => env('ROUTER_USER', env('MIKROTIK_USER', 'admin')),
    'password' => env('ROUTER_PASSWORD', env('MIKROTIK_PASSWORD', '')),
    'hotspot_server' => env('ROUTER_HOTSPOT_SERVER', env('MIKROTIK_HOTSPOT_SERVER', 'hotspot1')),

    // API-SSL (port 8729) protects the password on the wire. Set
    // ROUTER_API_TLS_VERIFY=false only for a self-signed CHR/lab certificate.
    'tls' => (bool) env('ROUTER_API_TLS', false),
    'tls_verify' => (bool) env('ROUTER_API_TLS_VERIFY', true),
];
