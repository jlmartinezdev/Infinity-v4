<?php

/**
 * Permisos de la app móvil para clientes (portal).
 * Se gestionan de forma global en Usuarios → Clientes app.
 */
return [
    'grupos' => [
        [
            'label' => 'App del cliente',
            'items' => [
                [
                    'label' => 'Mi cuenta / resumen',
                    'base' => 'portal.cuenta',
                    'acciones' => ['ver'],
                ],
                [
                    'label' => 'Facturas',
                    'base' => 'portal.facturas',
                    'acciones' => ['ver'],
                ],
                [
                    'label' => 'Historial de pagos',
                    'base' => 'portal.cobros',
                    'acciones' => ['ver'],
                ],
                [
                    'label' => 'Tickets de soporte',
                    'base' => 'portal.tickets',
                    'acciones' => ['ver', 'crear'],
                ],
                [
                    'label' => 'Loyalty / novedades / puntos',
                    'base' => 'portal.loyalty',
                    'acciones' => ['ver', 'canjear', 'upsell'],
                ],
            ],
        ],
    ],
];
