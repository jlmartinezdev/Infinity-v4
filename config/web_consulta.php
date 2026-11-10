<?php

return [
    /*
    | Token del sitio web de consulta y pago. Va en Authorization: Bearer.
    | Lo usa el servidor de la página, no el navegador del cliente.
    */
    'token' => env('WEB_CONSULTA_TOKEN', ''),
];
