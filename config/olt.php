<?php

return [
    'vsol' => [
        'default_user' => env('OLT_VSOL_USER', 'admin'),
        'default_telnet_port' => (int) env('OLT_VSOL_TELNET_PORT', 23),
        'default_ssh_port' => (int) env('OLT_VSOL_SSH_PORT', 22),
        'connect_timeout' => (int) env('OLT_CONNECT_TIMEOUT', 15),
        'command_timeout' => (int) env('OLT_COMMAND_TIMEOUT', 90),
        'read_chunk_us' => 100000,
        /** Segundos mínimos entre consultas automáticas al abrir la vista del OLT (0 = siempre). */
        'show_sync_min_interval' => (int) env('OLT_SHOW_SYNC_MIN_INTERVAL', 0),
        /** Timeout por comando show onu N desc / optical_info. */
        'onu_detail_timeout' => (int) env('OLT_ONU_DETAIL_TIMEOUT', 12),
        /** Máximo de ONUs a consultar individualmente en cada sync automático (0 = desactivado). */
        'onu_detail_max_per_sync' => (int) env('OLT_ONU_DETAIL_MAX_PER_SYNC', 48),
    ],
];
