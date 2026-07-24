<?php

return [
    'vsol' => [
        'default_user' => env('OLT_VSOL_USER', 'admin'),
        'default_telnet_port' => (int) env('OLT_VSOL_TELNET_PORT', 23),
        'default_ssh_port' => (int) env('OLT_VSOL_SSH_PORT', 22),
        'connect_timeout' => (int) env('OLT_CONNECT_TIMEOUT', 15),
        'command_timeout' => (int) env('OLT_COMMAND_TIMEOUT', 90),
        'read_chunk_us' => 100000,
        /** Pausa entre comandos Telnet (ms). Ayuda a evitar cortes 10053 en Windows. */
        'command_pause_ms' => (int) env('OLT_COMMAND_PAUSE_MS', 120),
        /** Segundos mínimos entre consultas automáticas al abrir la vista del OLT (0 = siempre). */
        'show_sync_min_interval' => (int) env('OLT_SHOW_SYNC_MIN_INTERVAL', 120),
        /** Timeout por comando show onu N desc / optical_info. */
        'onu_detail_timeout' => (int) env('OLT_ONU_DETAIL_TIMEOUT', 12),
        /** Máximo de ONUs a consultar individualmente en cada sync automático (0 = desactivado). */
        'onu_detail_max_per_sync' => (int) env('OLT_ONU_DETAIL_MAX_PER_SYNC', 0),
        /** Reconectar Telnet cada N ONUs al consultar detalle (0 = solo por puerto). */
        'onu_detail_reconnect_every' => (int) env('OLT_ONU_DETAIL_RECONNECT_EVERY', 8),

        /**
         * Comandos CLI para localizar MAC (defaults si el OLT no tiene mac_cli_comandos).
         * Placeholders: {mac} {mac_colon} {mac_vsol} {mac_dot} {pon} {pon2}
         *
         * @var array{address: list<string>, tabla: list<string>, pon: list<string>, interface: list<string>}
         */
        'mac_cli_comandos' => [
            // Lookup directo (opcional; el barrido por PON es la estrategia principal)
            'address' => [
                'show mac address-table address {mac_vsol}',
            ],
            'tabla' => [],
            // Principal: barrer PON 1..N (probar variantes de firmware)
            'pon' => [
                'show address-table gpon 0/{pon}',
                'show mac address-table gpon 0/{pon}',
            ],
            'interface' => [],
        ],
    ],
];
