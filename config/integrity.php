<?php

return [

    /** Un solo flag global para staff y clientes */
    'enforce' => (bool) env('INTEGRITY_ENFORCE', false),

    /**
     * Número de proyecto Google Cloud (ISP Staff Panel / mismo Firebase).
     * Clientes y Staff comparten el mismo Cloud.
     */
    'cloud_project_number' => env('INTEGRITY_CLOUD_PROJECT_NUMBER', '166400319630'),

    /**
     * Service account del proyecto 166400319630 (scope playintegrity).
     * Misma cuenta para Staff y Clientes.
     */
    'credentials' => env('INTEGRITY_CREDENTIALS', ''),

    'nonce_ttl_seconds' => (int) env('INTEGRITY_NONCE_TTL', 120),

    /** Packages esperados por tipo de login */
    'packages' => [
        'staff' => env('INTEGRITY_PACKAGE_NAME', 'com.isp.staff'),
        'cliente' => env('INTEGRITY_PACKAGE_CLIENTES', 'com.isp.clientes'),
    ],

    /**
     * device_name que dispara verificación obligatoria (cuando enforce=true).
     * Staff: solo la app Android Staff.
     * Clientes: si viene token/nonce se verifica siempre (log-only);
     * con enforce + este device_name sin token → 401.
     */
    'device_names' => [
        'staff' => env('INTEGRITY_STAFF_DEVICE_NAME', 'android_staff_app'),
        'cliente' => env('INTEGRITY_CLIENTES_DEVICE_NAME', 'android_clientes_app'),
    ],

    /**
     * Certificados SHA-256 (Play App Signing) por package.
     * Vacío = no validar cert (solo package + verdicts).
     * Staff: INTEGRITY_ALLOWED_CERT_SHA256
     * Clientes: INTEGRITY_ALLOWED_CERT_SHA256_CLIENTES (dejar vacío hasta anotar App Signing)
     */
    'allowed_cert_sha256_by_package' => [
        'com.isp.staff' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INTEGRITY_ALLOWED_CERT_SHA256', ''))
        ))),
        'com.isp.clientes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INTEGRITY_ALLOWED_CERT_SHA256_CLIENTES', ''))
        ))),
    ],

    // Compat: package staff legacy
    'package_name' => env('INTEGRITY_PACKAGE_NAME', 'com.isp.staff'),
    'staff_device_name' => env('INTEGRITY_STAFF_DEVICE_NAME', 'android_staff_app'),
    'allowed_cert_sha256' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INTEGRITY_ALLOWED_CERT_SHA256', ''))
    ))),
];
