<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nombre DNS local del portal cautivo
    |--------------------------------------------------------------------------
    | Cada MikroTik hotspot responde este nombre hacia su propia IP
    | (10.0.20.1 en N2, 10.0.70.1 en N7). El cliente lo abre en el navegador:
    | http://wifi.interplus
    */
    'dns_name' => strtolower(trim((string) env('HOTSPOT_DNS_NAME', 'wifi.interplus'))),

    /*
    | URL pública de esta app, la que el celular alcanza antes de tener internet.
    | El portal cautivo la usa para listar planes y abrir TPago.
    | Vacío = APP_URL. Tiene que ser una IP o un dominio que el router resuelva.
    */
    'venta_api_url' => rtrim((string) env('HOTSPOT_VENTA_API_URL', env('APP_URL', '')), '/'),

    /*
    | Hosts que el cliente puede abrir sin estar logueado: la API y el checkout de TPago.
    */
    'venta_jardin_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'HOTSPOT_VENTA_JARDIN_HOSTS',
            'comercios.bancard.com.py,vpos.infonet.com.py,*.bancard.com.py,*.infonet.com.py,interplus.com.py,www.interplus.com.py'
        ))
    ))),
];
