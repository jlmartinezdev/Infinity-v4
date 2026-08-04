<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta)
    |--------------------------------------------------------------------------
    | Propósito en Infinity: avisos SALIENTES organizados (ticket asignado,
    | factura, caída de enlace). La atención conversacional al cliente queda
    | en la IA de WhatsApp Business; no crear tickets ni auto-responder aquí.
    */

    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    'token' => env('WHATSAPP_TOKEN'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    'graph_base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),

    /** Token que Meta envía en GET hub.verify_token al suscribir el webhook */
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    /** App Secret para validar X-Hub-Signature-256 en webhooks POST */
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    /** App ID de Meta (Developers). Si vacío, whatsapp:suscribir lo infiere de subscribed_apps */
    'app_id' => env('WHATSAPP_APP_ID'),

    /** Callback público del webhook (prod). Si vacío usa APP_URL + /api/v1/webhooks/whatsapp */
    'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),

    /** Código país por defecto al normalizar números locales (Paraguay) */
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '595'),

    /** Idioma por defecto de plantillas */
    'default_template_language' => env('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', 'es'),

    /*
    |--------------------------------------------------------------------------
    | Entrada (desactivado: atención al cliente = IA WhatsApp)
    |--------------------------------------------------------------------------
    */
    'inbound_tickets_enabled' => (bool) env('WHATSAPP_INBOUND_TICKETS', false),

    'inbound_ticket_asunto_id' => env('WHATSAPP_TICKET_ASUNTO_ID'),

    'inbound_ticket_asunto_nombre' => env('WHATSAPP_TICKET_ASUNTO_NOMBRE', 'WhatsApp'),

    'inbound_ticket_prioridad' => env('WHATSAPP_TICKET_PRIORIDAD', 'media'),

    'inbound_ticket_asignado_id' => env('WHATSAPP_TICKET_ASIGNADO_ID'),

    'auto_reply_enabled' => (bool) env('WHATSAPP_AUTO_REPLY', false),

    'auto_reply_text' => env(
        'WHATSAPP_AUTO_REPLY_TEXT',
        'Recibimos tu mensaje. Un operador te contactará a la brevedad. Ticket #{ticket_id}.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Avisos salientes por evento
    |--------------------------------------------------------------------------
    | Fuera de ventana 24h hace falta plantilla Meta APPROVED.
    | Con plantilla vacía se intenta texto libre (solo útil si hay ventana).
    */
    'events' => [
        'ticket_asignado' => (bool) env('WHATSAPP_EVENT_TICKET_ASIGNADO', false),
        'factura' => (bool) env('WHATSAPP_EVENT_FACTURA', false),
        'enlace_caido' => (bool) env('WHATSAPP_EVENT_ENLACE_CAIDO', false),
        // TV se activa desde el panel (TvAvisoConfig), no solo por este flag.
        'tv_vencimiento' => (bool) env('WHATSAPP_EVENT_TV_VENCIMIENTO', true),
        /** Aviso al WhatsApp de la solicitud al aprobar/rechazar (default: activo si WA está configurado) */
        'acceso_aprobado' => (bool) env('WHATSAPP_EVENT_ACCESO_APROBADO', true),
        'acceso_rechazado' => (bool) env('WHATSAPP_EVENT_ACCESO_RECHAZADO', true),
        /** Enviar recibo de cobro al WhatsApp del cliente (auto al registrar cobro) */
        'recibo' => (bool) env('WHATSAPP_EVENT_RECIBO', false),
    ],

    /**
     * Número corporativo (solo dígitos, con país) para wa.me en la app.
     * Default: Interplus +595 971 714322
     */
    'solicitud_verificacion_destino' => env('WHATSAPP_SOLICITUD_DESTINO', '595971714322'),

    /** TTL del PIN OTP invertido (minutos) */
    'registro_otp_ttl_minutes' => (int) env('WHATSAPP_REGISTRO_OTP_TTL', 15),

    'registro_otp_text' => env(
        'WHATSAPP_REGISTRO_OTP_TEXT',
        '¡Hola! Tu código de verificación para la aplicación es: {codigo}'
    ),

    /** @deprecated Flujo anterior (código en solicitud) */
    'solicitud_verificacion_ok_text' => env(
        'WHATSAPP_SOLICITUD_VERIFICACION_OK',
        '¡Hola! Hemos verificado tu número de teléfono con éxito. Tu solicitud está siendo procesada. Pronto recibirás tus datos de acceso.'
    ),

    'templates' => [
        'ticket_asignado' => env('WHATSAPP_TEMPLATE_TICKET_ASIGNADO', ''),
        'factura' => env('WHATSAPP_TEMPLATE_FACTURA', ''),
        'enlace_caido' => env('WHATSAPP_TEMPLATE_ENLACE_CAIDO', ''),
        'tv_vencimiento' => env('WHATSAPP_TEMPLATE_TV_VENCIMIENTO', ''),
        'acceso_aprobado' => env('WHATSAPP_TEMPLATE_ACCESO_APROBADO', ''),
        /** Nombre exacto en Meta (APPROVED). Ver docs/whatsapp-plantilla-recibo.md */
        'recibo' => env('WHATSAPP_TEMPLATE_RECIBO', 'recibo_pago'),
    ],

    /**
     * Idioma por evento (opcional). Si vacío, se detecta desde Meta
     * (ej. recibo_pago está en es_AR aunque el default sea es).
     */
    'template_languages' => [
        'ticket_asignado' => env('WHATSAPP_TEMPLATE_TICKET_ASIGNADO_LANG', ''),
        'factura' => env('WHATSAPP_TEMPLATE_FACTURA_LANG', ''),
        'enlace_caido' => env('WHATSAPP_TEMPLATE_ENLACE_CAIDO_LANG', ''),
        'tv_vencimiento' => env('WHATSAPP_TEMPLATE_TV_VENCIMIENTO_LANG', ''),
        'acceso_aprobado' => env('WHATSAPP_TEMPLATE_ACCESO_APROBADO_LANG', ''),
        'recibo' => env('WHATSAPP_TEMPLATE_RECIBO_LANG', 'es_AR'),
    ],

    /**
     * Fallback de teléfonos de técnicos: "usuario_id:telefono,..."
     * Preferí users.telefono cuando exista.
     */
    'staff_phones' => env('WHATSAPP_STAFF_PHONES', ''),

];
