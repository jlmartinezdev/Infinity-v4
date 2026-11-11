<?php

/*
|--------------------------------------------------------------------------
| Eventos PPPoE: clientes que requieren atención por desconexión continua
|--------------------------------------------------------------------------
|
| Cantidad mínima de desconexiones (pppoe_down) en la última hora (h1),
| las últimas 6 horas (h6) y las últimas 24 horas (h24) para entrar en cada
| nivel. Alcanza con cumplir una de las tres. null = esa ventana no cuenta.
| Los niveles se evalúan de más grave a menos grave.
|
*/

return [
    'atencion' => [
        'critico' => ['h1' => 5, 'h6' => 10, 'h24' => 20],
        'alto' => ['h1' => 3, 'h6' => 6, 'h24' => 10],
        'observar' => ['h1' => null, 'h6' => 3, 'h24' => 5],
    ],
];
