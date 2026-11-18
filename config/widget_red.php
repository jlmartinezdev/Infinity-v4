<?php

return [
    /*
    | Token del widget de Windows (tablero Widgets). Va en Authorization: Bearer.
    | Lo usa el proveedor instalado en el equipo, no la sesión del navegador.
    | Vacío = el endpoint responde 401. No commitear un token real.
    */
    'token' => env('WIDGET_RED_TOKEN', ''),
];
