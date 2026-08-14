<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vencimiento de puntos (FIFO)
    |--------------------------------------------------------------------------
    |
    | Cada crédito genera un lote. Al debitar (canje/ajuste) se consumen
    | primero los lotes que vencen antes. Null en días = sin vencimiento.
    |
    */
    'dias_vencimiento_default' => env('LOYALTY_DIAS_VENCIMIENTO') !== null && env('LOYALTY_DIAS_VENCIMIENTO') !== ''
        ? (int) env('LOYALTY_DIAS_VENCIMIENTO')
        : 90,

    'dias_vencimiento_bienvenida' => env('LOYALTY_DIAS_VENCIMIENTO_BIENVENIDA') !== null && env('LOYALTY_DIAS_VENCIMIENTO_BIENVENIDA') !== ''
        ? (int) env('LOYALTY_DIAS_VENCIMIENTO_BIENVENIDA')
        : 30,

    /*
    | Días hacia adelante para informar "puntos por vencer" en GET portal/puntos.
    | 0 = informar el próximo vencimiento aunque esté lejos.
    */
    'ventana_alerta_vencimiento_dias' => (int) env('LOYALTY_ALERTA_VENCIMIENTO_DIAS', 30),

    'limite_canjes_mes' => (int) env('LOYALTY_LIMITE_CANJES_MES', 1),
];
