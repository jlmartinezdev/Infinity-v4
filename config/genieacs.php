<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GenieACS (TR-069 / CWMP)
    |--------------------------------------------------------------------------
    | Infinity no habla CWMP: consulta la API NBI del ACS.
    | Ver docs/genieacs-tr069.md
    */
    'enabled' => (bool) env('GENIEACS_ENABLED', false),
    'nbi_url' => rtrim((string) env('GENIEACS_NBI_URL', 'http://127.0.0.1:7557'), '/'),
    'nbi_user' => env('GENIEACS_NBI_USER', ''),
    'nbi_password' => env('GENIEACS_NBI_PASSWORD', ''),
    'timeout' => (int) env('GENIEACS_TIMEOUT', 20),
    'online_grace_seconds' => (int) env('GENIEACS_ONLINE_GRACE_SECONDS', 900),
];
