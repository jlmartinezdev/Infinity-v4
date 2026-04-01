<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MikroTik RouterOS API
    |--------------------------------------------------------------------------
    | Timeout y opciones por defecto para la conexión a routers MikroTik.
    | Las credenciales se toman del modelo Router (tabla routers).
    |
    | timeout: segundos para establecer la conexión TCP.
    | socket_timeout: segundos para esperar respuesta del router (lectura).
    |   Aumentar si hay muchos usuarios PPPoE o el router responde lento.
    */
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 30),
    'socket_timeout' => (int) env('MIKROTIK_SOCKET_TIMEOUT', 60),
    'port' => (int) env('MIKROTIK_PORT', 8728),
    'ssl' => (bool) env('MIKROTIK_SSL', false),
];
