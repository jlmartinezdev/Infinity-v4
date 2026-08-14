<?php

return [

    'ciudad' => env('LIQUIDACION_CIUDAD', 'Yuty, Departamento de Caazapá'),

    'empleador_nombre' => env('LIQUIDACION_EMPLEADOR_NOMBRE', 'Jose Luis Martinez Martinez'),

    'empleador_ci' => env('LIQUIDACION_EMPLEADOR_CI', '5.263.934'),

    'forma_pago' => env('LIQUIDACION_FORMA_PAGO', 'Transferencia bancaria'),

    'banco_default' => env('LIQUIDACION_BANCO_DEFAULT', 'Ueno'),

    /** Salario mínimo legal de referencia (Gs.) si el staff no tiene salario_basico. */
    'salario_minimo' => (int) env('LIQUIDACION_SALARIO_MINIMO', 2899048),

    /** Porcentaje aporte IPS trabajador (0–100). */
    'ips_porcentaje' => (float) env('LIQUIDACION_IPS_PORCENTAJE', 9),

    /** Jornadas trabajadas por defecto. */
    'jornadas_default' => (float) env('LIQUIDACION_JORNADAS_DEFAULT', 24),

];
