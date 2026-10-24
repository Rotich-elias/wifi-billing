<?php

return [
    'host' => env('ROUTER_HOST', env('MIKROTIK_HOST', '192.168.88.1')),
    'port' => (int) env('ROUTER_API_PORT', env('MIKROTIK_PORT', 8728)),
    'user' => env('ROUTER_USER', env('MIKROTIK_USER', 'admin')),
    'password' => env('ROUTER_PASSWORD', env('MIKROTIK_PASSWORD', '')),
    'hotspot_server' => env('ROUTER_HOTSPOT_SERVER', env('MIKROTIK_HOTSPOT_SERVER', 'hotspot1')),
];
