<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta)
    |--------------------------------------------------------------------------
    | Avisos salientes (plantillas) + webhook oficial. El agente IA es un
    | plugin opcional (N8N): ver clave `agent` más abajo.
    | No duplicar webhook en Meta.
    */

    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    /** Token Graph API. Alias: WHATSAPP_ACCESS_TOKEN */
    'token' => env('WHATSAPP_TOKEN', env('WHATSAPP_ACCESS_TOKEN')),

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
    | Agente IA (plugin N8N) — no reemplaza el webhook Meta
    |--------------------------------------------------------------------------
    | Si enabled: mensajes de texto entrantes se reenvían a N8N.
        | El job no envía solo: el borrador queda en revisión en el panel.
    | auto_send se ignora. Solo sale si un humano pide el envío.
    |
    | n8n Interplus corre en la VM infinity (LAN 10.200.1.4:5678). Desde esta PC
    | se alcanza por proxy user-space 127.0.0.1:5678 → 10.200.1.4:5678.
    | NUNCA usar 200.59.9.117: esa IP no es el n8n de Interplus.
    */
    'agent' => [
        'enabled' => (bool) env('WA_AGENT_ENABLED', false),
        'url' => env('N8N_WA_AGENT_URL', 'http://127.0.0.1:5678/webhook/interplus-wa-agent'),
        'secret' => env('N8N_WA_AGENT_SECRET'),
        'timeout_ms' => (int) env('N8N_WA_AGENT_TIMEOUT_MS', 25000),
        'fallback_message' => env(
            'WA_AGENT_FALLBACK_MESSAGE',
            'Recibimos su mensaje. Un asesor le responde en breve por este mismo WhatsApp.'
        ),
        'escalate_message' => env(
            'WA_AGENT_ESCALATE_MESSAGE',
            'Recibimos su aviso. De inmediato estaremos verificando y monitoreando su servicio para poder ayudarle.'
        ),
        /** Si un humano (panel o app Business) escribió en las últimas N horas, no llamar a N8N. Solo aplica con auto_send. */
        'humano_silencio_horas' => (int) env('WA_AGENT_HUMANO_SILENCIO_HORAS', 2),
        /** Enviar el reply de N8N aunque escalate=true (además de crear ticket). */
        'enviar_reply_en_escalado' => (bool) env('WA_AGENT_ENVIAR_REPLY_ESCALADO', true),
        /**
         * El automático no envía. El borrador queda en el panel hasta que una persona lo mande.
         * La clave se conserva por compatibilidad y no dispara el envío.
         */
        'auto_send' => false,
        /** Segundos de espera para juntar varios WhatsApp del cliente en una sola respuesta. */
        'rafaga_espera' => (int) env('WA_AGENT_RAFAGA_ESPERA', 12),
        /** Crear ticket si escalate=true. En entrenamiento conviene false. */
        'auto_ticket' => (bool) env('WA_AGENT_AUTO_TICKET', false),
        /** WhatsApp al administrador con el resumen técnico. Apagado en prueba. */
        'aviso_admin' => (bool) env('WA_AGENT_AVISO_ADMIN', false),
        /** Avisa por WhatsApp a quienes reclamaron cuando se cierra el incidente de zona. */
        'zona_aviso' => (bool) env('WA_AGENT_ZONA_AVISO', true),
        /** Cambia la clave WiFi solo si el número es el del cliente y esto está en true. */
        'wifi_auto' => (bool) env('WA_AGENT_WIFI_AUTO', false),
        /** Deja fijo el lease DHCP de una impresora en el MikroTik. No toca la antena. */
        'impresora_auto' => (bool) env('WA_AGENT_IMPRESORA_AUTO', false),
        'zabbix_secret' => env('ZABBIX_WEBHOOK_SECRET'),
        /** Últimos N mensajes del hilo que se mandan a N8N (no incluye el actual). */
        'historial_limite' => max(0, (int) env('WA_AGENT_HISTORIAL_LIMITE', 12)),
        /** true = solo el chat de hoy (zona horaria de la app). Días anteriores no entran al prompt. */
        'historial_solo_hoy' => filter_var(env('WA_AGENT_HISTORIAL_SOLO_HOY', true), FILTER_VALIDATE_BOOLEAN),
        /** Si historial_solo_hoy es false: mensajes de los últimos N días. 0 = sin recorte por fecha. */
        'historial_dias' => max(0, (int) env('WA_AGENT_HISTORIAL_DIAS', 1)),
        /** Lunes a viernes, hora local de APP_TIMEZONE. */
        'horario_desde' => env('WA_AGENT_HORARIO_DESDE', '09:00'),
        'horario_hasta' => env('WA_AGENT_HORARIO_HASTA', '18:00'),
        /** Visión para clasificar fotos (mapa vs comprobante). Misma API que n8n Gemini. */
        'gemini_api_key' => env('WA_AGENT_GEMINI_API_KEY'),
        'gemini_model' => env('WA_AGENT_GEMINI_MODEL', 'gemini-3.6-flash'),
        /** KPI / ROI del agente */
        'kpi' => [
            'venta_ventana_horas' => max(1, (int) env('WA_AGENT_KPI_VENTA_VENTANA_H', 72)),
            'margen_meses_plan' => max(0.1, (float) env('WA_AGENT_KPI_MARGEN_MESES', 1)),
            'gemini_usd_por_1m_in' => (float) env('WA_AGENT_KPI_GEMINI_USD_1M_IN', 0.10),
            'gemini_usd_por_1m_out' => (float) env('WA_AGENT_KPI_GEMINI_USD_1M_OUT', 0.40),
            'n8n_usd_por_llamada' => (float) env('WA_AGENT_KPI_N8N_USD', 0.002),
            'vision_tokens_estimados' => max(100, (int) env('WA_AGENT_KPI_VISION_TOKENS', 1200)),
            'csat_enabled' => (bool) env('WA_AGENT_KPI_CSAT', true),
            'csat_pregunta' => env(
                'WA_AGENT_KPI_CSAT_PREGUNTA',
                '¿Cómo calificaría la atención? Responda solo con un número del 1 al 5 (5 = excelente).'
            ),
            /** Meta de saludo / primera respuesta (humano o agente), en segundos. */
            'sla_primera_respuesta_seg' => max(30, (int) env('WA_AGENT_KPI_SLA_SEG', 120)),
        ],

        /**
         * Respuestas fijas que el staff ya usa en WhatsApp. El agente las copia tal cual
         * (sobre todo la cuenta: no dejar que Gemini invente números).
         */
        'canned' => [
            'beneficios' => env('WA_AGENT_TEXTO_BENEFICIOS') ?: <<<'TXT'
Todos nuestros planes son buenos y válidos. La diferencia no es “internet sí o no”, sino la experiencia:
• Tráfico prioritario en planes superiores: su navegación sale primero cuando hay mucha demanda.
• Cambios de contraseña WiFi: en planes superiores suelen ir bonificados; en el básico hay uno gratis y luego puede tener costo.
• Soporte técnico: si ocurre un inconveniente, los planes superiores tienen prioridad de atención.
TXT,
            'transferencia' => env('WA_AGENT_TEXTO_TRANSFERENCIA') ?: <<<'TXT'
Puede transferir a estas cuentas. Titular: Jose Luis Martinez Martinez. CI: 5263934.

Banco Nacional de Fomento
Cuenta: 016-21-0378077
Alias: 0983333696

Banco Familiar
Cuenta: 0-8214902
Alias: jose_mart289@live.com

Tigo Money: +595 983 742897

Cuando realice el pago, envíenos el comprobante por aquí.
TXT,
            'condiciones' => env('WA_AGENT_TEXTO_CONDICIONES') ?: <<<'TXT'
📌 CONDICIONES DEL SERVICIO – InterPlus+

Estimado cliente, le recordamos las condiciones de nuestro servicio:

🔹 Planes
• Todos los planes son ilimitados y sin contrato de permanencia.
• La velocidad puede variar según la cantidad de dispositivos conectados y condiciones técnicas del lugar.
• El uso del servicio es responsabilidad del cliente.
• Es fundamental mantener segura la contraseña WiFi para evitar accesos no autorizados.

💳 Pagos y Suspensión
• Fecha de pago: del 1 al 5 de cada mes.
• Si no se registra el pago dentro del plazo, el servicio se suspende automáticamente.
• No se realizan descuentos por días suspendidos por falta de pago.
• La reactivación es automática una vez acreditado el pago.
• No se aplican descuentos por cortes de energía, problemas eléctricos internos, falta de poda u otros factores ajenos a la empresa.

📦 Equipos
• Los equipos (antena, router, cables, etc.) son entregados en comodato y son propiedad de InterPlus+.
• Cualquier daño, manipulación o pérdida deberá ser abonado por el cliente.

🛠 Soporte Técnico
• Los reportes se atienden en un plazo estimado de 24 a 72 horas hábiles (según el caso).
• El cambio de contraseña WiFi es sin costo una vez; posteriores solicitudes tienen costo administrativo.

❌ Cancelación
• Para dar de baja el servicio se debe abonar el último mes utilizado.
• Se procederá al retiro de los equipos instalados.

La utilización del servicio implica la aceptación de estas condiciones.
TXT,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Avisos salientes por evento
    |--------------------------------------------------------------------------
    | Fuera de ventana 24h hace falta plantilla Meta APPROVED.
    | Con plantilla vacía se intenta texto libre (solo útil si hay ventana).
    */
    'events' => [
        'ticket_asignado' => (bool) env('WHATSAPP_EVENT_TICKET_ASIGNADO', false),
        /** Cliente: ticket marcado como resuelto. Ver docs/whatsapp-plantilla-ticket-resuelto.md */
        'ticket_resuelto' => (bool) env('WHATSAPP_EVENT_TICKET_RESUELTO', false),
        'factura' => (bool) env('WHATSAPP_EVENT_FACTURA', false),
        /** Cliente: reclamo de mora desde Pendiente de pago. Ver docs/whatsapp-plantilla-factura-reclamo.md */
        'factura_reclamo' => (bool) env('WHATSAPP_EVENT_FACTURA_RECLAMO', true),
        'enlace_caido' => (bool) env('WHATSAPP_EVENT_ENLACE_CAIDO', false),
        // TV se activa desde el panel (TvAvisoConfig), no solo por este flag.
        'tv_vencimiento' => (bool) env('WHATSAPP_EVENT_TV_VENCIMIENTO', true),
        /** Aviso al WhatsApp de la solicitud al aprobar/rechazar (default: activo si WA está configurado) */
        'acceso_aprobado' => (bool) env('WHATSAPP_EVENT_ACCESO_APROBADO', true),
        'acceso_rechazado' => (bool) env('WHATSAPP_EVENT_ACCESO_RECHAZADO', true),
        /** Enviar recibo de cobro al WhatsApp del cliente (auto al registrar cobro) */
        'recibo' => (bool) env('WHATSAPP_EVENT_RECIBO', false),
        /** Aviso staff "técnico en camino" (endpoint /staff/avisos/en-camino; siempre exige plantilla) */
        'en_camino' => (bool) env('WHATSAPP_EVENT_EN_CAMINO', true),
        /** Cliente: servicio suspendido por falta de pago. Ver docs/whatsapp-plantilla-servicio-suspendido.md */
        'servicio_suspendido' => (bool) env('WHATSAPP_EVENT_SERVICIO_SUSPENDIDO', false),
        /** Staff: router sin respuesta al ping (N fallos). Ver docs/whatsapp-plantilla-router-caido.md */
        'router_caido' => (bool) env('WHATSAPP_EVENT_ROUTER_CAIDO', true),
        /** Staff: AP wireless airOS sin ping. Reutiliza plantilla router_caido_ping si no hay una propia. */
        'ap_wireless_caido' => (bool) env('WHATSAPP_EVENT_AP_WIRELESS_CAIDO', true),
        /** Staff: salida ISP 1 caída / recuperada. Ver docs/whatsapp-plantilla-isp-failover.md */
        'isp_failover' => (bool) env('WHATSAPP_EVENT_ISP_FAILOVER', true),
    ],

    /**
     * Botón URL de seguimiento en plantilla en_camino (solo si hay URL pública con token temporal).
     * No usar maps.google.com/?q=<GPS técnico> desde el número oficial.
     */
    'en_camino_tracking_enabled' => (bool) env('WHATSAPP_EN_CAMINO_TRACKING', false),

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
        /** Ver docs/whatsapp-plantilla-ticket-resuelto.md */
        'ticket_resuelto' => env('WHATSAPP_TEMPLATE_TICKET_RESUELTO', 'ticket_resuelto'),
        'factura' => env('WHATSAPP_TEMPLATE_FACTURA', ''),
        /** Ver docs/whatsapp-plantilla-factura-reclamo.md — vacío hasta que la plantilla esté APPROVED */
        'factura_reclamo' => env('WHATSAPP_TEMPLATE_FACTURA_RECLAMO', ''),
        'enlace_caido' => env('WHATSAPP_TEMPLATE_ENLACE_CAIDO', ''),
        'tv_vencimiento' => env('WHATSAPP_TEMPLATE_TV_VENCIMIENTO', 'tv_cuenta_por_vencer'),
        'acceso_aprobado' => env('WHATSAPP_TEMPLATE_ACCESO_APROBADO', ''),
        /** Nombre exacto en Meta (APPROVED). Ver docs/whatsapp-plantilla-recibo.md */
        'recibo' => env('WHATSAPP_TEMPLATE_RECIBO', 'recibo_pago'),
        /** Ver docs/whatsapp-plantilla-en-camino.md */
        'en_camino' => env('WHATSAPP_TEMPLATE_EN_CAMINO', 'staff_tecnico_en_camino_v1'),
        /** Ver docs/whatsapp-plantilla-servicio-suspendido.md */
        'servicio_suspendido' => env('WHATSAPP_TEMPLATE_SERVICIO_SUSPENDIDO', 'servicio_suspendido_falta_pago'),
        /** Ver docs/whatsapp-plantilla-router-caido.md */
        'router_caido' => env('WHATSAPP_TEMPLATE_ROUTER_CAIDO', 'router_caido_ping'),
        'ap_wireless_caido' => env('WHATSAPP_TEMPLATE_AP_WIRELESS_CAIDO', env('WHATSAPP_TEMPLATE_ROUTER_CAIDO', 'router_caido_ping')),
        /** Ver docs/whatsapp-plantilla-isp-failover.md */
        'isp_failover' => env('WHATSAPP_TEMPLATE_ISP_FAILOVER', 'isp_failover_salida'),
    ],

    /**
     * Idioma por evento (opcional). Si vacío, se detecta desde Meta
     * (ej. recibo_pago está en es_AR aunque el default sea es).
     */
    'template_languages' => [
        'ticket_asignado' => env('WHATSAPP_TEMPLATE_TICKET_ASIGNADO_LANG', ''),
        'ticket_resuelto' => env('WHATSAPP_TEMPLATE_TICKET_RESUELTO_LANG', ''),
        'factura' => env('WHATSAPP_TEMPLATE_FACTURA_LANG', ''),
        'factura_reclamo' => env('WHATSAPP_TEMPLATE_FACTURA_RECLAMO_LANG', ''),
        'enlace_caido' => env('WHATSAPP_TEMPLATE_ENLACE_CAIDO_LANG', ''),
        'tv_vencimiento' => env('WHATSAPP_TEMPLATE_TV_VENCIMIENTO_LANG', ''),
        'acceso_aprobado' => env('WHATSAPP_TEMPLATE_ACCESO_APROBADO_LANG', ''),
        'recibo' => env('WHATSAPP_TEMPLATE_RECIBO_LANG', 'es_AR'),
        /** Alias: WHATSAPP_TEMPLATE_LANGUAGE */
        'en_camino' => env('WHATSAPP_TEMPLATE_EN_CAMINO_LANG', env('WHATSAPP_TEMPLATE_LANGUAGE', '')),
        'servicio_suspendido' => env('WHATSAPP_TEMPLATE_SERVICIO_SUSPENDIDO_LANG', ''),
        'router_caido' => env('WHATSAPP_TEMPLATE_ROUTER_CAIDO_LANG', ''),
        'ap_wireless_caido' => env('WHATSAPP_TEMPLATE_AP_WIRELESS_CAIDO_LANG', env('WHATSAPP_TEMPLATE_ROUTER_CAIDO_LANG', '')),
        'isp_failover' => env('WHATSAPP_TEMPLATE_ISP_FAILOVER_LANG', ''),
    ],

    /**
     * Fallback de teléfonos de técnicos: "usuario_id:telefono,..."
     * Preferí users.telefono cuando exista.
     */
    'staff_phones' => env('WHATSAPP_STAFF_PHONES', ''),

];
