<?php

/**
 * Feature flags y ajustes de la app Interplus Clientes (portal v1 / Home 3.2).
 *
 * state: enabled | coming_soon | hidden
 */
return [
    'flags' => [
        'plan_card' => env('PORTAL_FLAG_PLAN_CARD', 'enabled'),
        'dark_mode' => env('PORTAL_FLAG_DARK_MODE', 'enabled'),
        'tickets' => env('PORTAL_FLAG_TICKETS', 'enabled'),
        'push_notifications' => env('PORTAL_FLAG_PUSH', 'enabled'),
        'interplus_ia' => env('PORTAL_FLAG_INTERPLUS_IA', 'enabled'),
        'referidos' => env('PORTAL_FLAG_REFERIDOS', 'enabled'),
        // Si no hay checkout URL, el servicio fuerza coming_soon.
        'pago_online' => env('PORTAL_FLAG_PAGO_ONLINE', 'auto'),
        'speed_test_screen' => env('PORTAL_FLAG_SPEED_TEST', 'coming_soon'),
        'chat_ia' => env('PORTAL_FLAG_CHAT_IA', 'hidden'),
        'iptv' => env('PORTAL_FLAG_IPTV', 'hidden'),
        'camaras' => env('PORTAL_FLAG_CAMARAS', 'hidden'),
        'control_parental' => env('PORTAL_FLAG_CONTROL_PARENTAL', 'hidden'),
        'vpn' => env('PORTAL_FLAG_VPN', 'hidden'),
        'tecnico_geolocation' => env('PORTAL_FLAG_TECNICO_GEO', 'hidden'),
        'firma_digital' => env('PORTAL_FLAG_FIRMA', 'hidden'),
        'router_realtime_monitoring' => env('PORTAL_FLAG_ROUTER_RT', 'hidden'),
        'coverage_map' => env('PORTAL_FLAG_COVERAGE', 'hidden'),
    ],

    'flag_labels' => [
        'pago_online' => 'Pago con tarjeta en camino',
        'speed_test_screen' => 'Test de velocidad mejorado en camino',
        'referidos' => null,
        'interplus_ia' => null,
    ],

    'pago_online' => [
        /** URL de checkout (http…). Placeholders: {cliente_id} {cedula} {token} */
        'checkout_url' => env('PORTAL_PAGO_ONLINE_CHECKOUT_URL'),
        'provider' => env('PORTAL_PAGO_ONLINE_PROVIDER', 'bancard'),
    ],

    'referidos' => [
        'puntos_por_alta' => (int) env('PORTAL_REFERIDOS_PUNTOS_ALTA', 50),
        'link_base' => env('PORTAL_REFERIDOS_LINK_BASE', rtrim((string) env('APP_URL', 'https://infinityisppro.net'), '/').'/r'),
    ],

    'resumen' => [
        /** Si null, se estima según servicios activos. */
        'disponibilidad_pct' => env('PORTAL_DISPONIBILIDAD_PCT'),
    ],

    'whatsapp' => [
        'pagos' => env('PORTAL_WHATSAPP_PAGOS', '595971714322'),
        'soporte' => env('PORTAL_WHATSAPP_SOPORTE', '595971714322'),
    ],
];
