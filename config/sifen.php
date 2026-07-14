<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente SIFEN (test | production)
    | Acepta también el alias "prod" → se normaliza a "production".
    |--------------------------------------------------------------------------
    */
    'ambiente' => in_array(strtolower((string) env('SIFEN_AMBIENTE', 'test')), ['production', 'prod'], true)
        ? 'production'
        : 'test',

    'debug_soap' => (bool) env('SIFEN_DEBUG_SOAP', false),

    // Firma con facturacionelectronicapy-xmlsign (TIPS) vía Node.js — recomendado.
    'firma_node' => filter_var(env('SIFEN_FIRMA_NODE', true), FILTER_VALIDATE_BOOL),

    // Envío SOAP: Node.js usa el mismo P12 que la firma (más fiable en Windows).
    'envio_node' => filter_var(env('SIFEN_ENVIO_NODE', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Modo de envío local a SIFEN: sync | async
    | async = recibe-lote + consulta-lote (útil si el RUC no tiene servicio síncrono / 1264).
    | En modo API este valor lo define sifen-api (SIFEN_ENVIO_MODO allí).
    |--------------------------------------------------------------------------
    */
    'envio_modo' => in_array(strtolower((string) env('SIFEN_ENVIO_MODO', 'sync')), ['async', 'asincrono', 'lote'], true)
        ? 'async'
        : 'sync',

    'lote_espera_inicial' => max(5, (int) env('SIFEN_LOTE_ESPERA_INICIAL', 30)),
    'lote_intervalo' => max(5, (int) env('SIFEN_LOTE_INTERVALO', 20)),
    'lote_max_intentos' => max(1, (int) env('SIFEN_LOTE_MAX_INTENTOS', 30)),

    'node_path' => env('SIFEN_NODE_PATH'),

    'version_formato' => 150,

    'timezone' => env('SIFEN_TIMEZONE', 'America/Asuncion'),

    // Margen de seguridad si el reloj del servidor va adelantado respecto a SIFEN (error 1004).
    'clock_skew_seconds' => max(0, (int) env('SIFEN_CLOCK_SKEW', 120)),

    'namespace' => 'http://ekuatia.set.gov.py/sifen/xsd',

    'xmlns_xsi' => 'http://www.w3.org/2001/XMLSchema-instance',

    /*
    |--------------------------------------------------------------------------
    | URLs Web Services
    |--------------------------------------------------------------------------
    */
    'ws' => [
        'test' => [
            'recepcion_de' => 'https://sifen-test.set.gov.py/de/ws/sync/recibe.wsdl',
            'recepcion_de_endpoint' => 'https://sifen-test.set.gov.py/de/ws/sync/recibe',
            'recepcion_lote' => 'https://sifen-test.set.gov.py/de/ws/async/recibe-lote.wsdl',
            'recepcion_lote_endpoint' => 'https://sifen-test.set.gov.py/de/ws/async/recibe-lote',
            'eventos' => 'https://sifen-test.set.gov.py/de/ws/eventos/evento.wsdl',
            'eventos_endpoint' => 'https://sifen-test.set.gov.py/de/ws/eventos/evento',
            'consulta_de' => 'https://sifen-test.set.gov.py/de/ws/consultas/consulta.wsdl',
            'consulta_de_endpoint' => 'https://sifen-test.set.gov.py/de/ws/consultas/consulta',
            'consulta_lote' => 'https://sifen-test.set.gov.py/de/ws/consultas/consulta-lote.wsdl',
            'consulta_lote_endpoint' => 'https://sifen-test.set.gov.py/de/ws/consultas/consulta-lote',
            'consulta_ruc' => 'https://sifen-test.set.gov.py/de/ws/consultas/consulta-ruc.wsdl',
            'qr_base' => 'https://ekuatia.set.gov.py/consultas-test/qr',
        ],
        'production' => [
            'recepcion_de' => 'https://sifen.set.gov.py/de/ws/sync/recibe.wsdl',
            'recepcion_de_endpoint' => 'https://sifen.set.gov.py/de/ws/sync/recibe',
            'recepcion_lote' => 'https://sifen.set.gov.py/de/ws/async/recibe-lote.wsdl',
            'recepcion_lote_endpoint' => 'https://sifen.set.gov.py/de/ws/async/recibe-lote',
            'eventos' => 'https://sifen.set.gov.py/de/ws/eventos/evento.wsdl',
            'eventos_endpoint' => 'https://sifen.set.gov.py/de/ws/eventos/evento',
            'consulta_de' => 'https://sifen.set.gov.py/de/ws/consultas/consulta.wsdl',
            'consulta_de_endpoint' => 'https://sifen.set.gov.py/de/ws/consultas/consulta',
            'consulta_lote' => 'https://sifen.set.gov.py/de/ws/consultas/consulta-lote.wsdl',
            'consulta_lote_endpoint' => 'https://sifen.set.gov.py/de/ws/consultas/consulta-lote',
            'consulta_ruc' => 'https://sifen.set.gov.py/de/ws/consultas/consulta-ruc.wsdl',
            'qr_base' => 'https://ekuatia.set.gov.py/consultas/qr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificado digital (.p12 / .pfx)
    |--------------------------------------------------------------------------
    */
    'certificado' => [
        'path' => env('SIFEN_CERT_PATH', storage_path('sifen/cert/certificado.p12')),
        'password' => env('SIFEN_CERT_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rutas de almacenamiento
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'xsd' => storage_path('sifen/xsd/v150'),
        'xml' => storage_path('sifen/xml'),
        'pdf' => storage_path('sifen/pdf'),
        'cert' => storage_path('sifen/cert'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CSC — Código Secreto del Contribuyente (QR KuDE)
    |--------------------------------------------------------------------------
    */
    'csc' => [
        'id' => env('SIFEN_CSC_ID', '0001'),
        'token' => env('SIFEN_CSC_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto emisor (fallback si faltan en BD)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'departamento' => 1,
        'departamento_descripcion' => 'CAPITAL',
        'distrito' => 1,
        'distrito_descripcion' => 'ASUNCION (DISTRITO)',
        'ciudad' => 1,
        'ciudad_descripcion' => 'ASUNCION (DISTRITO)',
        'numero_casa' => '0',
        'pais' => 'PRY',
        'pais_descripcion' => 'Paraguay',
        'tipo_emision' => 1,
        'sistema_facturacion' => 1,
        'indicador_presencia' => 2,
        'tipo_transaccion' => 2,
        'unidad_medida' => 77,
        'unidad_medida_descripcion' => 'UNI',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo tipo documento interno → iTiDE SIFEN
    |--------------------------------------------------------------------------
    */
    'tipos_documento' => [
        'factura_contado' => 1,
        'factura_credito' => 1,
        'autofactura' => 4,
        'nota_credito' => 5,
        'nota_debito' => 6,
        'nota_remision' => 7,
    ],

    'descripciones_tipo_documento' => [
        1 => 'Factura electrónica',
        4 => 'Autofactura electrónica',
        5 => 'Nota de crédito electrónica',
        6 => 'Nota de débito electrónica',
        7 => 'Nota de remisión electrónica',
    ],

    'motivos_emision_nc_nd' => [
        1 => 'Devolución y Ajuste de precios',
        2 => 'Devolución',
        3 => 'Descuento',
        4 => 'Bonificación',
        5 => 'Crédito incobrable',
        6 => 'Recupero de costo',
        7 => 'Recupero de gasto',
        8 => 'Ajuste de precio',
    ],

    'motivos_traslado_remision' => [
        1 => 'Traslado por ventas',
        7 => 'Traslado entre locales de la empresa',
        99 => 'Otro',
    ],

    /*
    |--------------------------------------------------------------------------
    | Microservicio sifen-api (emisión remota)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => filter_var(env('SIFEN_API_ENABLED', false), FILTER_VALIDATE_BOOL),
        'url' => rtrim((string) env('SIFEN_API_URL', ''), '/'),
        'token' => env('SIFEN_API_TOKEN'),
        'timeout' => max(30, (int) env('SIFEN_API_TIMEOUT', 900)),
    ],

    // En modo API: solicitar a sifen-api el envío de XML + KuDE al email del cliente.
    'enviar_correo_emision' => filter_var(env('SIFEN_ENVIAR_CORREO', true), FILTER_VALIDATE_BOOL),

];
