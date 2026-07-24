<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
     * WispHub API - Importación desde sistema externo
     * Documentación: https://wisphub.net/api-docs/#tag/Clientes
     * Obtener Api-Key en: Lista de Personal en WispHub
     */
    'wisphub' => [
        'api_key' => env('WISPHUB_API_KEY'),
        'base_url' => env('WISPHUB_BASE_URL', 'https://api.wisphub.net'),
        'sandbox_url' => 'https://sandbox-api.wisphub.net',
    ],

    'fcm' => [
        /** Ruta al JSON de cuenta de servicio (HTTP v1). Relativa a base_path o absoluta. */
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
        'project_id' => env('FCM_PROJECT_ID'),
        'staff_topic' => env('FCM_STAFF_TOPIC', 'staff'),
        /** Canal Android staff (debe coincidir con el creado en la app staff) */
        'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'staff'),
        /** Canal Android app cliente */
        'client_android_channel_id' => env('FCM_CLIENT_ANDROID_CHANNEL_ID', 'clientes'),
        /** Obsoleto: API legacy /fcm/send ya no funciona (404). */
        'server_key' => env('FCM_SERVER_KEY'),
    ],

];
