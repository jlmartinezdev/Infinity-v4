<?php

/**
 * Catálogo de modelos MikroTik RouterOS (RB, CCR, hAP, CHR).
 * Clave = slug guardado en routers.modelo
 */
return [
    'rb750gr3' => [
        'nombre' => 'RB750Gr3',
        'serie' => 'RB',
        'imagen' => 'images/routers/rb-soho.svg',
        'descripcion' => 'Router SOHO 5 puertos Gigabit',
    ],
    'rb760igs' => [
        'nombre' => 'RB760iGS',
        'serie' => 'RB',
        'imagen' => 'images/routers/rb-soho.svg',
        'descripcion' => 'Router SOHO con SFP',
    ],
    'rb4011' => [
        'nombre' => 'RB4011iGS+RM',
        'serie' => 'RB',
        'imagen' => 'images/routers/rb-rack.svg',
        'descripcion' => 'Router rack 10 puertos + SFP+',
    ],
    'rb5009' => [
        'nombre' => 'RB5009UG+S+IN',
        'serie' => 'RB',
        'imagen' => 'images/routers/rb-desktop.svg',
        'descripcion' => 'Router desktop alto rendimiento',
    ],
    'rb1100' => [
        'nombre' => 'RB1100AHx4',
        'serie' => 'RB',
        'imagen' => 'images/routers/rb-rack.svg',
        'descripcion' => 'Router rack 1U',
    ],
    'ccr1009' => [
        'nombre' => 'CCR1009-7G-1C-1S+',
        'serie' => 'CCR',
        'imagen' => 'images/routers/ccr-rack.svg',
        'descripcion' => 'Cloud Core Router 9 núcleos',
    ],
    'ccr1016' => [
        'nombre' => 'CCR1016-12G',
        'serie' => 'CCR',
        'imagen' => 'images/routers/ccr-rack.svg',
        'descripcion' => 'Cloud Core Router 16 núcleos',
    ],
    'ccr1036' => [
        'nombre' => 'CCR1036-8G-2S+',
        'serie' => 'CCR',
        'imagen' => 'images/routers/ccr-rack.svg',
        'descripcion' => 'Cloud Core Router 36 núcleos',
    ],
    'ccr2004' => [
        'nombre' => 'CCR2004-16G-2S+',
        'serie' => 'CCR',
        'imagen' => 'images/routers/ccr-rack.svg',
        'descripcion' => 'Cloud Core Router ARM 4 núcleos',
    ],
    'ccr2116' => [
        'nombre' => 'CCR2116-12G-4S+',
        'serie' => 'CCR',
        'imagen' => 'images/routers/ccr-rack.svg',
        'descripcion' => 'Cloud Core Router 16 núcleos',
    ],
    'ccr2216' => [
        'nombre' => 'CCR2216-1G-12XS-2XQ',
        'serie' => 'CCR',
        'imagen' => 'images/routers/ccr-rack.svg',
        'descripcion' => 'Cloud Core Router 16 núcleos 25G',
    ],
    'hap-ac2' => [
        'nombre' => 'hAP ac²',
        'serie' => 'hAP',
        'imagen' => 'images/routers/hap-wifi.svg',
        'descripcion' => 'Access point Wi-Fi dual band',
    ],
    'hap-ax3' => [
        'nombre' => 'hAP ax³',
        'serie' => 'hAP',
        'imagen' => 'images/routers/hap-wifi.svg',
        'descripcion' => 'Access point Wi-Fi 6',
    ],
    'chr' => [
        'nombre' => 'CHR (Cloud Hosted Router)',
        'serie' => 'CHR',
        'imagen' => 'images/routers/chr.svg',
        'descripcion' => 'RouterOS virtual / VM',
    ],
    'otro' => [
        'nombre' => 'Otro / genérico',
        'serie' => 'MikroTik',
        'imagen' => 'images/routers/mikrotik-generic.svg',
        'descripcion' => 'Modelo no listado',
    ],
];
