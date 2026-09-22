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
];
