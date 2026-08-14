<?php

/**
 * Feature flags y ajustes de la app Interplus Clientes (portal v1 / Home 3.2).
 *
 * state: enabled | coming_soon | hidden | auto
 * Keys de pago con metadata: pago_online, pago_tigo_money, pago_transferencia, pago_qr
 */
return [
    'flags' => [
        'plan_card' => env('PORTAL_FLAG_PLAN_CARD', 'enabled'),
        'dark_mode' => env('PORTAL_FLAG_DARK_MODE', 'enabled'),
        'tickets' => env('PORTAL_FLAG_TICKETS', 'enabled'),
        'push_notifications' => env('PORTAL_FLAG_PUSH', 'enabled'),
        'interplus_ia' => env('PORTAL_FLAG_INTERPLUS_IA', 'enabled'),
        'referidos' => env('PORTAL_FLAG_REFERIDOS', 'enabled'),
        // auto → enabled si TPago/URL listo.
        'pago_online' => env('PORTAL_FLAG_PAGO_ONLINE', 'auto'),
        'pago_tigo_money' => env('PORTAL_FLAG_PAGO_TIGO', 'coming_soon'),
        'pago_transferencia' => env('PORTAL_FLAG_PAGO_TRANSFERENCIA', 'coming_soon'),
        'pago_qr' => env('PORTAL_FLAG_PAGO_QR', 'hidden'),
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

    /** Keys canónicas de métodos de pago (metadata en feature-flags). */
    'pago_metodos_keys' => [
        'pago_online',
        'pago_tigo_money',
        'pago_transferencia',
        'pago_qr',
    ],

    'flag_labels' => [
        'pago_online' => 'Pago con tarjeta en camino',
        'pago_tigo_money' => 'Tigo Money en camino',
        'pago_transferencia' => 'Transferencia en camino',
        'pago_qr' => 'Pago QR en camino',
        'speed_test_screen' => 'Test de velocidad mejorado en camino',
        'referidos' => null,
        'interplus_ia' => null,
    ],

    'pago_online' => [
        /** URL de checkout (http…). Placeholders: {cliente_id} {cedula} {token} */
        'checkout_url' => env('PORTAL_PAGO_ONLINE_CHECKOUT_URL'),
        'provider' => env('PORTAL_PAGO_ONLINE_PROVIDER', 'bancard'),
    ],

    /**
     * Metadata por método (panel + GET feature-flags).
     * WhatsApp cobranzas del panel se inyecta en metadata.whatsapp si el método no lo define.
     */
    'metodos_pago' => [
        'pago_online' => [
            'title' => 'Tarjeta / TPago',
            'subtitle' => 'Pagar con tarjeta en línea',
            'badge' => null,
            'sort_order' => 10,
            'icon' => 'card',
            'instructions' => 'Confirmá el monto y se abrirá el checkout seguro de TPago.',
            'provider' => env('PORTAL_PAGO_ONLINE_PROVIDER', 'TPago'),
            'note' => null,
            'whatsapp_template' => null,
            'whatsapp_cta_label' => null,
            'show_whatsapp_cta' => false,
        ],
        'pago_tigo_money' => [
            'title' => 'Tigo Money',
            'subtitle' => 'Ver datos y pagar por billetera',
            'badge' => 'Datos',
            'sort_order' => 20,
            'icon' => 'tigo',
            'instructions' => 'Transferí el monto a este número. En el concepto poné tu cédula. Después avisá por WhatsApp con el comprobante.',
            'tigo_phone' => env('PORTAL_TIGO_PHONE', '0981714322'),
            'tigo_alias' => env('PORTAL_TIGO_ALIAS', 'Interplus'),
            'tigo_ci' => env('PORTAL_TIGO_CI'),
            'whatsapp_template' => 'Hola Interplus, pagué {monto} con Tigo Money. Te mando el comprobante.',
            'whatsapp_cta_label' => 'Avisar por WhatsApp',
            'show_whatsapp_cta' => true,
        ],
        'pago_transferencia' => [
            'title' => 'Transferencia bancaria',
            'subtitle' => 'Ver datos de cuenta',
            'badge' => 'Datos',
            'sort_order' => 40,
            'icon' => 'transfer',
            'instructions' => 'Transferí y avisá con el comprobante.',
            'bank' => env('PORTAL_BANK_NAME'),
            'account_type' => env('PORTAL_BANK_ACCOUNT_TYPE'),
            'account_number' => env('PORTAL_BANK_ACCOUNT_NUMBER'),
            'account_holder' => env('PORTAL_BANK_ACCOUNT_HOLDER'),
            'account_ci_ruc' => env('PORTAL_BANK_RUC'),
            'bank_alias' => env('PORTAL_BANK_ALIAS'),
            'whatsapp_template' => 'Hola Interplus, transferí {monto}. Te mando el comprobante.',
            'whatsapp_cta_label' => 'Avisar por WhatsApp',
            'show_whatsapp_cta' => true,
        ],
        'pago_qr' => [
            'title' => 'Pago con QR',
            'subtitle' => 'Escanear y pagar',
            'badge' => 'Pronto',
            'sort_order' => 30,
            'icon' => 'qr',
            'instructions' => 'Cuando esté disponible, vas a poder pagar escaneando un QR.',
            'qr_alias' => env('PORTAL_QR_ALIAS'),
            'qr_link' => env('PORTAL_QR_LINK'),
            'qr_id' => env('PORTAL_QR_ID'),
            'whatsapp_template' => 'Hola Interplus, pagué {monto} con QR. Te mando el comprobante.',
            'whatsapp_cta_label' => 'Avisar por WhatsApp',
            'show_whatsapp_cta' => true,
        ],
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
