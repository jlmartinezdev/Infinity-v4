<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FreeRADIUS (hotspot MikroTik)
    |--------------------------------------------------------------------------
    | El contenedor lee las tablas rad* de la conexión `radius`.
    | Ver docker-compose.freeradius.yml
    */
    'enabled' => (bool) env('RADIUS_ENABLED', false),
    'connection' => env('RADIUS_DB_CONNECTION', 'radius'),
    'secret' => env('RADIUS_SECRET', 'cambiar-este-secreto-radius'),
    'host' => env('RADIUS_HOST', '10.200.1.2'),
    'auth_port' => (int) env('RADIUS_AUTH_PORT', 1812),
    'acct_port' => (int) env('RADIUS_ACCT_PORT', 1813),
    'also_sync_mikrotik' => filter_var(env('RADIUS_ALSO_SYNC_MIKROTIK', true), FILTER_VALIDATE_BOOLEAN),
];
