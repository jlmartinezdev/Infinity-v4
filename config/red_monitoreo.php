<?php

/**
 * Topología lógica de routers MikroTik para el monitor de red.
 * Los nombres deben coincidir con routers.nombre (sin distinguir mayúsculas).
 */
return [
    'titulo' => 'Monitoreo de red',
    'subtitulo' => 'Topología lógica — núcleo y nodos',

    /**
     * Posiciones en canvas SVG (viewBox 1200×720). Centro de cada tarjeta.
     */
    'layout' => [
        'MK-N2-BORDE' => ['x' => 600, 'y' => 90, 'rol' => 'core'],
        'MK-N2-R2' => ['x' => 300, 'y' => 260, 'rol' => 'agregacion'],
        'MK-N3-FTTH' => ['x' => 900, 'y' => 260, 'rol' => 'agregacion'],
        'MK-N6-R1' => ['x' => 140, 'y' => 450, 'rol' => 'acceso'],
        'MK-N1-R1' => ['x' => 400, 'y' => 450, 'rol' => 'acceso'],
        'MK-N3-R1' => ['x' => 760, 'y' => 450, 'rol' => 'acceso'],
        'MK-N7-01' => ['x' => 1020, 'y' => 450, 'rol' => 'acceso'],
        'MK-N4-01' => ['x' => 400, 'y' => 620, 'rol' => 'acceso'],
        'Mk-N4-02' => ['x' => 650, 'y' => 620, 'rol' => 'acceso'],
    ],

    /**
     * Enlaces: from → to (nombres de router).
     */
    'enlaces' => [
        ['from' => 'MK-N2-BORDE', 'to' => 'MK-N2-R2'],
        ['from' => 'MK-N2-BORDE', 'to' => 'MK-N3-FTTH'],
        ['from' => 'MK-N2-R2', 'to' => 'MK-N6-R1'],
        ['from' => 'MK-N2-R2', 'to' => 'MK-N1-R1'],
        ['from' => 'MK-N1-R1', 'to' => 'MK-N4-01'],
        ['from' => 'MK-N3-FTTH', 'to' => 'MK-N3-R1'],
        ['from' => 'MK-N3-FTTH', 'to' => 'MK-N7-01'],
    ],
];
