<?php

return [
    'enabled' => (bool) env('TPAGO_ENABLED', false),

    /** production | sandbox */
    'env' => env('TPAGO_ENV', 'sandbox'),

    'base_url' => env('TPAGO_BASE_URL') ?: (
        env('TPAGO_ENV', 'sandbox') === 'production'
            ? 'https://comercios.bancard.com.py'
            : 'https://comercios.bancard.com.py:8888'
    ),

    'public_key' => env('TPAGO_PUBLIC_KEY'),
    'private_key' => env('TPAGO_PRIVATE_KEY'),
    'commerce_code' => env('TPAGO_COMMERCE_CODE'),
    'branch_code' => env('TPAGO_BRANCH_CODE'),

    /**
     * URL que se configura en el portal TPago como confirmación de pagos.
     * Default: {APP_URL}/api/v1/webhooks/tpago
     */
    'callback_url' => env('TPAGO_CALLBACK_URL'),

    /**
     * Basic Auth del webhook (Bancard pide usuario/contraseña del callback).
     * Si usuario y password están vacíos, no se exige auth.
     */
    'callback_user' => env('TPAGO_CALLBACK_USER'),
    'callback_password' => env('TPAGO_CALLBACK_PASSWORD'),

    /** Si true, solo acepta callbacks desde IPs documentadas por TPago */
    'verify_ip' => (bool) env('TPAGO_VERIFY_IP', false),

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TPAGO_ALLOWED_IPS', '190.128.218.209,190.128.232.10,190.104.129.98,200.85.46.226'))
    ))),

    'http_timeout' => (int) env('TPAGO_HTTP_TIMEOUT', 30),
];