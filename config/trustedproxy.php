<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza (X-Forwarded-*)
    |--------------------------------------------------------------------------
    |
    | Detrás de nginx, Cloudflare, HAProxy, etc., Laravel debe confiar en los
    | encabezados X-Forwarded-Proto / Host para detectar HTTPS y el host real.
    | Si no, la cookie de sesión puede marcarse mal (p. ej. sin Secure) y al
    | cambiar de red o de WiFi a datos el navegador deja de enviarla → parece
    | que “pide login” otra vez.
    |
    | Valores típicos en .env:
    |   TRUSTED_PROXIES=*          (confía en el proxy que conecta directo al PHP)
    |   TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12   (solo esas redes)
    |
    | Dejar sin definir en entornos locales sin proxy inverso.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
