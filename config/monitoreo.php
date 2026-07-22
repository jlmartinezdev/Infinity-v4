<?php

return [
    'habilitado' => env('MONITOREO_PING_HABILITADO', true),

    /** Intervalo del daemon (segundos) entre rondas completas de ping. */
    'intervalo_segundos' => (int) env('MONITOREO_PING_INTERVALO', 300),

    /** Timeout de cada ping ICMP (milisegundos). */
    'timeout_ms' => (int) env('MONITOREO_PING_TIMEOUT_MS', 2000),

    /** Servicios procesados por lote en cada ronda. */
    'lote' => (int) env('MONITOREO_PING_LOTE', 40),

    /** Refresco automático del mapa (segundos); 0 = desactivado. */
    'mapa_refresco_segundos' => (int) env('MONITOREO_PING_MAPA_REFRESCO', 60),
];
