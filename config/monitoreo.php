<?php

return [
    'habilitado' => env('MONITOREO_PING_HABILITADO', true),

    /** Intervalo del daemon (segundos) entre rondas de consulta PPPoE. */
    'intervalo_segundos' => (int) env('MONITOREO_PING_INTERVALO', 300),

    /** Timeout de ping ICMP a routers/APs (milisegundos). No se usa para clientes. */
    'timeout_ms' => (int) env('MONITOREO_PING_TIMEOUT_MS', 2000),

    /** Lote legado; la ronda de clientes agrupa por router MikroTik. */
    'lote' => (int) env('MONITOREO_PING_LOTE', 40),

    /** Refresco automático del mapa (segundos); 0 = desactivado. */
    'mapa_refresco_segundos' => (int) env('MONITOREO_PING_MAPA_REFRESCO', 60),

    /**
     * Si el último PPPoE verificado es más viejo que esto, el mapa no muestra Online
     * (evita casitas verdes con router de zona caído).
     */
    'mapa_stale_minutos' => (int) env('MONITOREO_PING_MAPA_STALE_MIN', 15),
];
