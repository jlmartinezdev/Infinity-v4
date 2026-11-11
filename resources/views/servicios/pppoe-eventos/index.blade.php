@extends('layouts.app')

@section('title', 'Eventos PPPoE')

@php
    $tipoUp = \App\Models\ServicioConexionEvento::TIPO_PPPOE_UP;
    $tipoDown = \App\Models\ServicioConexionEvento::TIPO_PPPOE_DOWN;
    $periodoDefecto = \App\Http\Controllers\ServicioPppoeEventoController::PERIODO_DEFECTO;
    $parametros = request()->except('page');
    $enlace = function (array $cambios) use ($parametros) {
        return route('servicios.pppoe-eventos.index', array_filter(
            array_merge($parametros, $cambios),
            fn ($valor) => $valor !== null && $valor !== ''
        ));
    };
    // El detalle de un cliente usa el mismo período y la misma fuente que la pantalla.
    $urlDetalle = fn (array $destino) => route('servicios.pppoe-eventos.detalle', array_filter(
        array_merge(request()->only(['periodo', 'fecha_desde', 'fecha_hasta', 'fuente']), $destino),
        fn ($valor) => $valor !== null && $valor !== ''
    ));
    $miles = fn ($valor) => number_format((int) $valor, 0, ',', '.');
    $cuantos = fn (int $n, string $uno, string $varios) => $miles($n).' '.($n === 1 ? $uno : $varios);

    $tipoActual = (string) request('tipo', '');
    $tarjetaActiva = match (true) {
        $tipoActual === $tipoDown && $orden === 'cortes' => 'clientes',
        $tipoActual === $tipoDown => 'desconexiones',
        $tipoActual === $tipoUp => 'conexiones',
        default => 'todos',
    };
    $tarjetas = [
        [
            'clave' => 'todos',
            'titulo' => 'Eventos registrados',
            'valor' => $resumen['total'],
            'detalle' => ucfirst($periodo['etiqueta']),
            'tono' => '',
            'cambios' => ['tipo' => null, 'orden' => null],
        ],
        [
            'clave' => 'desconexiones',
            'titulo' => 'Desconexiones',
            'valor' => $resumen['desconexiones'],
            'detalle' => 'Veces que se cortó una sesión',
            'tono' => $resumen['desconexiones'] > 0 ? 'is-down' : '',
            'cambios' => ['tipo' => $tipoDown, 'orden' => null],
        ],
        [
            'clave' => 'conexiones',
            'titulo' => 'Conexiones',
            'valor' => $resumen['conexiones'],
            'detalle' => 'Veces que se levantó una sesión',
            'tono' => '',
            'cambios' => ['tipo' => $tipoUp, 'orden' => null],
        ],
        [
            'clave' => 'clientes',
            'titulo' => 'Clientes afectados',
            'valor' => $resumen['clientes_afectados'],
            'detalle' => 'Con al menos un corte · ordena por cortes',
            'tono' => '',
            'cambios' => ['tipo' => $tipoDown, 'orden' => 'cortes'],
        ],
    ];
    $routerActual = (string) request('router_id', '');
    $hayFiltros = request()->hasAny(['buscar', 'tipo', 'router_id', 'fecha_desde', 'fecha_hasta', 'orden'])
        || request('fuente', 'mikrotik') !== 'mikrotik'
        || $periodo['clave'] !== $periodoDefecto;
    $campo = 'w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none';
    $etiquetaCampo = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5';
@endphp

@push('styles')
<style>
    .ppe-page > * + * { margin-top: 1rem; }
    .ppe-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
    }
    .ppe-periodos {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 2px;
        padding: 2px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
    }
    .ppe-periodo {
        padding: 0.375rem 0.75rem;
        border-radius: 0.375rem;
        color: #4b5563;
        font-size: 0.8125rem;
        text-decoration: none;
        white-space: nowrap;
    }
    .ppe-periodo:hover { background: #f3f4f6; color: #111827; }
    .ppe-periodo[aria-current="true"] {
        background: #0d9488;
        color: #fff;
        font-weight: 600;
    }
    .ppe-kpis {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    @media (min-width: 1024px) {
        .ppe-kpis { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    .ppe-kpi {
        display: block;
        padding: 0.75rem 1rem;
        color: inherit;
        text-decoration: none;
    }
    .ppe-kpi:hover { border-color: #2dd4bf; }
    .ppe-kpi:focus-visible,
    .ppe-periodo:focus-visible,
    .ppe-router:focus-visible {
        outline: 2px solid #2dd4bf;
        outline-offset: 2px;
    }
    .ppe-kpi[aria-current="true"] {
        border-color: #0d9488;
        background: #f0fdfa;
    }
    .ppe-kpi-titulo {
        display: block;
        font-size: 0.875rem;
        color: #4b5563;
    }
    .ppe-kpi[aria-current="true"] .ppe-kpi-titulo { color: #115e59; font-weight: 600; }
    .ppe-kpi-valor {
        display: block;
        margin-top: 0.25rem;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 2rem;
        color: #111827;
        font-variant-numeric: tabular-nums;
    }
    .ppe-kpi-valor.is-down { color: #c2410c; }
    .ppe-kpi-detalle {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-seccion-titulo {
        font-size: 0.875rem;
        font-weight: 600;
        color: #111827;
    }
    .ppe-seccion-ayuda {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-routers {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.25rem 1.5rem;
        margin-top: 0.5rem;
    }
    @media (min-width: 640px) {
        .ppe-routers { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .ppe-routers { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    .ppe-router {
        display: block;
        padding: 0.375rem 0.5rem;
        border-radius: 0.5rem;
        color: inherit;
        text-decoration: none;
    }
    a.ppe-router:hover { background: #f3f4f6; }
    .ppe-router[aria-current="true"] { background: #f0fdfa; box-shadow: inset 0 0 0 1px #0d9488; }
    .ppe-router-fila {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.8125rem;
    }
    .ppe-router-nombre {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 600;
        color: #111827;
    }
    .ppe-router-num {
        flex: none;
        color: #4b5563;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .ppe-router-num strong { color: #c2410c; font-weight: 700; }
    .ppe-bar {
        display: block;
        height: 6px;
        margin-top: 0.25rem;
        border-radius: 3px;
        background: #e5e7eb;
        overflow: hidden;
    }
    .ppe-bar-fill {
        display: block;
        height: 100%;
        border-radius: 3px;
        background: #ea580c;
    }
    .ppe-conteo {
        display: block;
        margin-top: 0.125rem;
        font-size: 0.75rem;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .ppe-conteo .is-down { color: #c2410c; font-weight: 600; }
    .pppoe-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        border-radius: 9999px;
        font-weight: 600;
        line-height: 1.2;
    }
    .pppoe-badge--sm {
        padding: 0.15rem 0.55rem;
        font-size: 11px;
    }
    .pppoe-badge--up {
        background: #e0f2fe;
        color: #0c4a6e;
        border: 1px solid #7dd3fc;
    }
    .pppoe-badge--down {
        background: #ffedd5;
        color: #7c2d12;
        border: 1px solid #fdba74;
    }
    .ppe-cliente-btn {
        padding: 0;
        border: 0;
        background: transparent;
        color: #0f766e;
        font: inherit;
        font-weight: 600;
        text-align: left;
        text-decoration: underline;
        text-decoration-color: rgba(15, 118, 110, 0.35);
        text-underline-offset: 3px;
        cursor: pointer;
    }
    .ppe-cliente-btn:hover { text-decoration-color: currentColor; }
    .ppe-cliente-btn:focus-visible,
    .ppe-btn:focus-visible {
        outline: 2px solid #2dd4bf;
        outline-offset: 2px;
    }
    .ppe-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2.25rem;
        padding: 0 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: transparent;
        color: #1f2937;
        font-size: 0.8125rem;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }
    .ppe-btn:hover { background: #f3f4f6; }
    .ppe-btn-icono { width: 2.25rem; padding: 0; flex: none; }
    .ppe-fondo {
        position: fixed;
        inset: 0;
        z-index: 60;
        background: rgba(17, 24, 39, 0.55);
    }
    .ppe-panel {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        z-index: 61;
        width: 100%;
        max-width: 34rem;
        overflow-y: auto;
        background: #fff;
        color: #111827;
        border-left: 1px solid #e5e7eb;
        box-shadow: -12px 0 30px rgba(0, 0, 0, 0.2);
    }
    .ppe-panel:focus { outline: none; }
    .ppe-fondo[hidden],
    .ppe-panel[hidden] { display: none; }
    .ppe-panel-cabecera {
        position: sticky;
        top: 0;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        background: inherit;
    }
    .ppe-panel-titulos { min-width: 0; }
    .ppe-panel-titulo {
        font-size: 1.125rem;
        font-weight: 700;
        line-height: 1.5rem;
    }
    .ppe-panel-sub,
    .ppe-linea-meta,
    .ppe-evento-meta {
        display: block;
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-panel-cuerpo { padding: 1rem; }
    .ppe-panel-cuerpo > * + * { margin-top: 1rem; }
    .ppe-linea {
        padding: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
    }
    .ppe-linea-cabecera {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .ppe-linea-nombre { min-width: 0; }
    .ppe-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.8125rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .ppe-linea-estado {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.625rem;
        font-size: 0.875rem;
    }
    .ppe-punto {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
        flex: none;
        background: #9ca3af;
    }
    .ppe-punto.is-up { background: #0d9488; }
    .ppe-punto.is-down { background: #ea580c; }
    .ppe-nivel {
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
        white-space: nowrap;
    }
    .ppe-nivel.is-critico { background: #fee2e2; color: #991b1b; }
    .ppe-nivel.is-alto { background: #ffedd5; color: #9a3412; }
    .ppe-nivel.is-observar { background: #fef3c7; color: #854d0e; }
    .ppe-ventanas {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin-top: 0.625rem;
    }
    .ppe-ventanas dt {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .ppe-ventanas dd {
        font-size: 1.125rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .ppe-tl {
        display: flex;
        height: 0.875rem;
        margin-top: 0.75rem;
        border-radius: 4px;
        background: #e5e7eb;
        overflow: hidden;
    }
    .ppe-tl-seg { display: block; height: 100%; min-width: 1px; }
    .ppe-tl-seg.is-up,
    .ppe-tl-clave.is-up { background: #14b8a6; }
    .ppe-tl-seg.is-down,
    .ppe-tl-clave.is-down { background: #ea580c; }
    .ppe-tl-seg.is-unknown,
    .ppe-tl-clave.is-unknown { background: #9ca3af; }
    .ppe-tl-marcas {
        display: flex;
        justify-content: space-between;
        margin-top: 0.125rem;
        font-size: 11px;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }
    .ppe-tl-leyenda {
        margin-top: 0.375rem;
        font-size: 0.75rem;
        color: #4b5563;
    }
    .ppe-tl-clave {
        display: inline-block;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 2px;
    }
    .ppe-eventos {
        margin-top: 0.5rem;
        list-style: none;
        padding: 0;
    }
    .ppe-evento {
        display: flex;
        gap: 0.75rem;
        padding: 0.5rem 0 0.5rem 0.75rem;
        border-left: 3px solid #14b8a6;
        font-size: 0.8125rem;
    }
    .ppe-evento + .ppe-evento { margin-top: 0.25rem; }
    .ppe-evento.is-down { border-left-color: #ea580c; }
    .ppe-evento-hora {
        flex: none;
        width: 5.75rem;
        color: #4b5563;
        font-variant-numeric: tabular-nums;
    }
    .ppe-evento-texto { min-width: 0; }
    .ppe-evento.is-down strong { color: #c2410c; }
    .ppe-evento-dura { color: #4b5563; }
    .ppe-evento-meta { overflow-wrap: anywhere; }
    .ppe-kpi-valor.is-critico { color: #b91c1c; }
    .ppe-contador {
        margin-left: 0.25rem;
        padding: 1px 8px;
        border-radius: 9999px;
        background: #f3f4f6;
        color: #4b5563;
        font-size: 0.75rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    #ppe-atencion { scroll-margin-top: 5rem; }
    .ppe-atencion-cabecera {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
    }
    .ppe-atencion-vacio {
        padding: 0 1rem 0.75rem;
        font-size: 0.8125rem;
        color: #4b5563;
    }
    .ppe-niveles {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 2px;
        padding: 2px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
    }
    .ppe-nivel-btn {
        padding: 0.25rem 0.625rem;
        border: 0;
        border-radius: 0.375rem;
        background: transparent;
        color: #4b5563;
        font-size: 0.8125rem;
        white-space: nowrap;
        cursor: pointer;
    }
    .ppe-nivel-btn:hover { background: #f3f4f6; }
    .ppe-nivel-btn[aria-pressed="true"] {
        background: #e5e7eb;
        color: #111827;
        font-weight: 600;
    }
    .ppe-nivel-btn:focus-visible,
    .ppe-menu-item:focus-visible {
        outline: 2px solid #2dd4bf;
        outline-offset: 1px;
    }
    .ppe-atencion-scroll {
        max-height: 24rem;
        overflow: auto;
        border-top: 1px solid #e5e7eb;
    }
    .ppe-tabla {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .ppe-tabla th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 0.5rem 1rem;
        background: #f9fafb;
        color: #4b5563;
        font-weight: 500;
        text-align: left;
        white-space: nowrap;
    }
    .ppe-tabla td {
        padding: 0.5rem 1rem;
        border-top: 1px solid #e5e7eb;
        color: #374151;
        vertical-align: middle;
        white-space: nowrap;
    }
    .ppe-tabla .is-num { text-align: right; }
    .ppe-tabla tr[hidden] { display: none; }
    .ppe-cortes {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }
    .ppe-cortes.is-observar { color: #a16207; }
    .ppe-cortes.is-alto { color: #c2410c; }
    .ppe-cortes.is-critico { color: #b91c1c; font-weight: 700; }
    .ppe-acciones {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .ppe-menu-wrap { display: inline-block; }
    .ppe-menu {
        position: fixed;
        z-index: 50;
        min-width: 11rem;
        padding: 0.25rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        text-align: left;
    }
    .ppe-menu[hidden] { display: none; }
    .ppe-menu-item {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 0;
        border-radius: 0.375rem;
        background: transparent;
        color: #111827;
        font-size: 0.875rem;
        text-align: left;
        text-decoration: none;
        cursor: pointer;
    }
    .ppe-menu-item:hover { background: #f3f4f6; }
    .ppe-umbrales {
        padding: 0.5rem 1rem 0.75rem;
        border-top: 1px solid #e5e7eb;
        font-size: 0.75rem;
        color: #4b5563;
    }
    .ppe-umbrales summary { cursor: pointer; width: fit-content; }
    .ppe-umbrales ul { margin: 0.375rem 0; padding-left: 1rem; list-style: disc; }
    .ppe-umbrales .ppe-mono { font-size: 0.75rem; font-weight: 400; }
    html.dark .ppe-kpi-valor.is-critico { color: #fca5a5; }
    html.dark .ppe-contador { background: #374151; color: #d1d5db; }
    html.dark .ppe-atencion-vacio,
    html.dark .ppe-umbrales { color: #d1d5db; }
    html.dark .ppe-niveles { border-color: #4b5563; }
    html.dark .ppe-nivel-btn { color: #d1d5db; }
    html.dark .ppe-nivel-btn:hover { background: #374151; }
    html.dark .ppe-nivel-btn[aria-pressed="true"] { background: #4b5563; color: #fff; }
    html.dark .ppe-atencion-scroll,
    html.dark .ppe-tabla td,
    html.dark .ppe-umbrales { border-color: #374151; }
    html.dark .ppe-tabla th { background: #111827; color: #d1d5db; }
    html.dark .ppe-tabla td { color: #e5e7eb; }
    html.dark .ppe-cortes { color: #9ca3af; }
    html.dark .ppe-cortes.is-observar { color: #fde047; }
    html.dark .ppe-cortes.is-alto { color: #fdba74; }
    html.dark .ppe-cortes.is-critico { color: #fca5a5; }
    html.dark .ppe-menu { background: #1f2937; border-color: #4b5563; }
    html.dark .ppe-menu-item { color: #f3f4f6; }
    html.dark .ppe-menu-item:hover { background: #374151; }
    html.dark .ppe-cliente-btn {
        color: #5eead4;
        text-decoration-color: rgba(94, 234, 212, 0.35);
    }
    html.dark .ppe-btn {
        border-color: #4b5563;
        color: #f3f4f6;
    }
    html.dark .ppe-btn:hover { background: #374151; }
    html.dark .ppe-panel {
        background: #111827;
        color: #f3f4f6;
        border-left-color: #374151;
    }
    html.dark .ppe-panel-cabecera,
    html.dark .ppe-linea { border-color: #374151; }
    html.dark .ppe-panel-sub,
    html.dark .ppe-linea-meta,
    html.dark .ppe-evento-meta,
    html.dark .ppe-ventanas dt,
    html.dark .ppe-tl-marcas { color: #9ca3af; }
    html.dark .ppe-tl { background: #374151; }
    html.dark .ppe-tl-leyenda,
    html.dark .ppe-evento-hora,
    html.dark .ppe-evento-dura { color: #d1d5db; }
    html.dark .ppe-evento.is-down strong { color: #fdba74; }
    html.dark .ppe-nivel.is-critico { background: #7f1d1d; color: #fee2e2; }
    html.dark .ppe-nivel.is-alto { background: #7c2d12; color: #ffedd5; }
    html.dark .ppe-nivel.is-observar { background: #713f12; color: #fef3c7; }
    html.dark .ppe-card {
        border-color: #374151;
        background: #1f2937;
    }
    html.dark .ppe-periodos {
        border-color: #4b5563;
        background: #1f2937;
    }
    html.dark .ppe-periodo { color: #d1d5db; }
    html.dark .ppe-periodo:hover { background: #374151; color: #fff; }
    html.dark .ppe-periodo[aria-current="true"] { background: #0d9488; color: #fff; }
    html.dark .ppe-kpi:hover { border-color: #14b8a6; }
    html.dark .ppe-kpi[aria-current="true"] {
        border-color: #14b8a6;
        background: rgba(13, 148, 136, 0.18);
    }
    html.dark .ppe-kpi-titulo { color: #d1d5db; }
    html.dark .ppe-kpi[aria-current="true"] .ppe-kpi-titulo { color: #99f6e4; }
    html.dark .ppe-kpi-valor { color: #f3f4f6; }
    html.dark .ppe-kpi-valor.is-down { color: #fdba74; }
    html.dark .ppe-kpi-detalle,
    html.dark .ppe-seccion-ayuda,
    html.dark .ppe-conteo { color: #9ca3af; }
    html.dark .ppe-seccion-titulo,
    html.dark .ppe-router-nombre { color: #f3f4f6; }
    html.dark a.ppe-router:hover { background: #374151; }
    html.dark .ppe-router[aria-current="true"] {
        background: rgba(13, 148, 136, 0.18);
        box-shadow: inset 0 0 0 1px #14b8a6;
    }
    html.dark .ppe-router-num { color: #d1d5db; }
    html.dark .ppe-router-num strong,
    html.dark .ppe-conteo .is-down { color: #fdba74; }
    html.dark .ppe-bar { background: #374151; }
    html.dark .ppe-bar-fill { background: #f97316; }
    html.dark .pppoe-badge--up {
        background: #0c4a6e;
        color: #e0f2fe;
        border-color: #0284c7;
    }
    html.dark .pppoe-badge--down {
        background: #7c2d12;
        color: #ffedd5;
        border-color: #ea580c;
    }
</style>
@endpush

@section('content')
<div id="pppoe-eventos-page" class="ppe-page max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Eventos PPPoE</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Conexiones y desconexiones que avisa el MikroTik, agrupadas por cliente.
            </p>
        </div>
        <div>
            <nav class="ppe-periodos" aria-label="Período">
                @foreach($periodos as $clave => $etiqueta)
                    <a href="{{ $enlace(['periodo' => $clave === $periodoDefecto ? null : $clave, 'fecha_desde' => null, 'fecha_hasta' => null]) }}"
                       class="ppe-periodo" @if($periodo['clave'] === $clave) aria-current="true" @endif>{{ $etiqueta }}</a>
                @endforeach
            </nav>
            @if($periodo['clave'] === 'fechas')
                <p class="ppe-seccion-ayuda mt-1">Mostrando {{ $periodo['etiqueta'] }}.</p>
            @endif
        </div>
    </div>

    <div class="ppe-kpis">
        @foreach($tarjetas as $tarjeta)
            @php
                $activa = $tarjetaActiva === $tarjeta['clave'];
                $destino = $activa ? ['tipo' => null, 'orden' => null] : $tarjeta['cambios'];
            @endphp
            <a href="{{ $enlace($destino) }}" class="ppe-card ppe-kpi" @if($activa) aria-current="true" @endif>
                <span class="ppe-kpi-titulo">{{ $tarjeta['titulo'] }}</span>
                <span class="ppe-kpi-valor {{ $tarjeta['tono'] }}">{{ $miles($tarjeta['valor']) }}</span>
                <span class="ppe-kpi-detalle">{{ $tarjeta['detalle'] }}</span>
            </a>
        @endforeach
        @php
            $nAtencion = $atencion->count();
            $porNivel = $atencion->countBy('nivel');
            $nCriticos = (int) ($porNivel['critico'] ?? 0);
            $nAltos = (int) ($porNivel['alto'] ?? 0);
            $nObservar = (int) ($porNivel['observar'] ?? 0);
        @endphp
        <a href="#ppe-atencion" class="ppe-card ppe-kpi">
            <span class="ppe-kpi-titulo">Requieren atención</span>
            <span class="ppe-kpi-valor {{ $nCriticos > 0 ? 'is-critico' : ($nAtencion > 0 ? 'is-down' : '') }}">{{ $miles($nAtencion) }}</span>
            <span class="ppe-kpi-detalle">
                @if($nAtencion === 0)
                    Sin desconexión continua en 24 h
                @else
                    {{ $cuantos($nCriticos, 'crítico', 'críticos') }} · {{ $cuantos($nAltos, 'alto', 'altos') }} · {{ $nObservar }} a observar
                @endif
            </span>
        </a>
    </div>

    <section id="ppe-atencion" class="ppe-card" aria-labelledby="ppe-atencion-titulo">
        <div class="ppe-atencion-cabecera">
            <div>
                <h2 id="ppe-atencion-titulo" class="ppe-seccion-titulo">
                    Requieren atención por desconexión continua
                    <span class="ppe-contador">{{ $miles($nAtencion) }}</span>
                </h2>
                <p class="ppe-seccion-ayuda">
                    Líneas que se cortaron varias veces en las últimas 24 h, de la más grave a la menos grave. No depende del período elegido.
                </p>
            </div>
            @if($nAtencion > 0)
                <div class="ppe-niveles" role="group" aria-label="Filtrar por gravedad">
                    <button type="button" class="ppe-nivel-btn" data-ppe-nivel="" aria-pressed="true">Todos</button>
                    @foreach(['critico' => ['Crítico', $nCriticos], 'alto' => ['Alto', $nAltos], 'observar' => ['A observar', $nObservar]] as $claveNivel => [$textoNivel, $cuenta])
                        @if($cuenta > 0)
                            <button type="button" class="ppe-nivel-btn" data-ppe-nivel="{{ $claveNivel }}" aria-pressed="false">{{ $textoNivel }} · {{ $cuenta }}</button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if($nAtencion === 0)
            <p class="ppe-atencion-vacio">Ninguna línea tuvo desconexión continua en las últimas 24 h.</p>
        @else
            <div class="ppe-atencion-scroll">
                <table class="ppe-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Gravedad</th>
                            <th scope="col">Cliente</th>
                            <th scope="col" class="is-num">Última hora</th>
                            <th scope="col" class="is-num">6 h</th>
                            <th scope="col" class="is-num">24 h</th>
                            <th scope="col">Ahora</th>
                            <th scope="col">Último corte</th>
                            <th scope="col" class="is-num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($atencion as $fila)
                            @php
                                $detalleFila = $urlDetalle(['servicio_id' => $fila->servicio_id]);
                            @endphp
                            <tr data-ppe-fila-nivel="{{ $fila->nivel }}">
                                <td><span class="ppe-nivel is-{{ $fila->nivel }}">{{ $fila->etiqueta }}</span></td>
                                <td>
                                    <button type="button" class="ppe-cliente-btn" aria-haspopup="dialog"
                                            data-ppe-detalle="{{ $detalleFila }}"
                                            title="Ver los eventos de {{ $fila->nombre }}">{{ $fila->nombre }}</button>
                                    <span class="ppe-conteo">{{ $fila->usuario }} · {{ $fila->router ?? 'Sin router' }}</span>
                                </td>
                                @foreach(['h1', 'h6', 'h24'] as $ventana)
                                    <td class="is-num">
                                        <span class="ppe-cortes {{ $fila->ventanas[$ventana] ? 'is-'.$fila->ventanas[$ventana] : '' }}">{{ $miles($fila->$ventana) }}</span>
                                    </td>
                                @endforeach
                                <td>
                                    @if($fila->conectado === null)
                                        <span class="ppe-seccion-ayuda">Sin datos</span>
                                    @elseif($fila->conectado)
                                        <span class="pppoe-badge pppoe-badge--sm pppoe-badge--up">Conectado</span>
                                    @else
                                        <span class="pppoe-badge pppoe-badge--sm pppoe-badge--down">Desconectado</span>
                                    @endif
                                </td>
                                <td>
                                    @if($fila->ultimo_corte)
                                        <span title="{{ $fila->ultimo_corte->format('d/m/Y H:i:s') }}">hace {{ $fila->ultimo_corte_hace }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="is-num">
                                    <div class="ppe-acciones">
                                        <a href="{{ route('servicios.herramientas-red', $fila->servicio_id) }}" class="ppe-btn">Herramientas</a>
                                        <div class="ppe-menu-wrap">
                                            <button type="button" class="ppe-btn ppe-btn-icono" data-ppe-menu aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de {{ $fila->nombre }}">⋯</button>
                                            <div class="ppe-menu" role="menu" hidden>
                                                <button type="button" role="menuitem" class="ppe-menu-item" data-ppe-detalle="{{ $detalleFila }}">Ver eventos</button>
                                                @if($fila->cliente)
                                                    <a role="menuitem" class="ppe-menu-item" href="{{ route('clientes.edit', $fila->cliente) }}">Ficha del cliente</a>
                                                @endif
                                                <button type="button" role="menuitem" class="ppe-menu-item" data-ppe-copiar="{{ $fila->usuario }}">Copiar usuario PPPoE</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($nAtencion >= \App\Http\Controllers\ServicioPppoeEventoController::ATENCION_MAX_FILAS)
                <p class="ppe-atencion-vacio">Se muestran las {{ $nAtencion }} líneas más graves.</p>
            @endif
        @endif

        <details class="ppe-umbrales">
            <summary>Cómo se clasifica</summary>
            <ul>
                @foreach($umbrales as $claveNivel => $minimos)
                    @php
                        $partes = [];
                        foreach (['h1' => 'la última hora', 'h6' => '6 h', 'h24' => '24 h'] as $ventana => $textoVentana) {
                            if ($minimos[$ventana] !== null) {
                                $partes[] = $minimos[$ventana].' o más en '.$textoVentana;
                            }
                        }
                    @endphp
                    @if($partes !== [])
                        <li><strong>{{ \App\Support\PppoeDesconexionContinua::etiqueta($claveNivel) }}:</strong> {{ implode(', o ', $partes) }}.</li>
                    @endif
                @endforeach
            </ul>
            <p>Solo cuentan las desconexiones. Los valores se cambian en <span class="ppe-mono">config/pppoe_eventos.php</span>.</p>
        </details>
    </section>

    @if(count($porRouter) > 0)
        <section class="ppe-card px-4 py-3" aria-labelledby="ppe-routers-titulo">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="ppe-routers-titulo" class="ppe-seccion-titulo">Por router</h2>
                <p class="ppe-seccion-ayuda">Desconexiones {{ $periodo['etiqueta'] }}. Tocá un router para ver solo sus clientes.</p>
            </div>
            <div class="ppe-routers">
                @foreach($porRouter as $fila)
                    @php
                        $esActual = $fila['router_id'] !== null && $routerActual === (string) $fila['router_id'];
                    @endphp
                    @if($fila['router_id'] !== null)
                        <a href="{{ $enlace(['router_id' => $esActual ? null : $fila['router_id']]) }}" class="ppe-router" @if($esActual) aria-current="true" @endif>
                    @else
                        <div class="ppe-router">
                    @endif
                        <span class="ppe-router-fila">
                            <span class="ppe-router-nombre">{{ $fila['nombre'] }}</span>
                            <span class="ppe-router-num"><strong>{{ $miles($fila['desconexiones']) }}</strong> {{ $fila['desconexiones'] === 1 ? 'corte' : 'cortes' }} · {{ $cuantos($fila['conexiones'], 'conexión', 'conexiones') }}</span>
                        </span>
                        <span class="ppe-bar" aria-hidden="true"><span class="ppe-bar-fill" style="width: {{ $fila['pct'] }}%"></span></span>
                    @if($fila['router_id'] !== null)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('servicios.pppoe-eventos.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            @if($periodo['clave'] !== 'fechas' && $periodo['clave'] !== $periodoDefecto)
                <input type="hidden" name="periodo" value="{{ $periodo['clave'] }}">
            @endif
            @if($orden === 'cortes')
                <input type="hidden" name="orden" value="cortes">
            @endif
            <div class="flex flex-col lg:flex-row gap-3 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <label for="ppe-buscar" class="{{ $etiquetaCampo }}">Buscar</label>
                    <input type="text" id="ppe-buscar" name="buscar" value="{{ request('buscar') }}"
                           placeholder="Usuario PPPoE, cliente, IP, MAC…" class="{{ $campo }}">
                </div>
                <div class="sm:w-40">
                    <label for="ppe-tipo" class="{{ $etiquetaCampo }}">Tipo de evento</label>
                    <select id="ppe-tipo" name="tipo" class="{{ $campo }}">
                        <option value="">Todos</option>
                        <option value="{{ $tipoUp }}" @selected($tipoActual === $tipoUp)>Conexiones</option>
                        <option value="{{ $tipoDown }}" @selected($tipoActual === $tipoDown)>Desconexiones</option>
                    </select>
                </div>
                <div class="sm:w-52">
                    <label for="ppe-fuente" class="{{ $etiquetaCampo }}">Fuente</label>
                    <select id="ppe-fuente" name="fuente" class="{{ $campo }}">
                        @foreach(\App\Models\ServicioConexionEvento::fuentesPppoeFiltro() as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(request('fuente', 'mikrotik') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-44">
                    <label for="ppe-router" class="{{ $etiquetaCampo }}">Router</label>
                    <select id="ppe-router" name="router_id" class="{{ $campo }}">
                        <option value="">Todos</option>
                        @foreach($routers as $router)
                            <option value="{{ $router->router_id }}" @selected($routerActual === (string) $router->router_id)>{{ $router->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-36">
                    <label for="ppe-desde" class="{{ $etiquetaCampo }}">Desde</label>
                    <input type="date" id="ppe-desde" name="fecha_desde" value="{{ request('fecha_desde') }}" class="{{ $campo }}">
                </div>
                <div class="sm:w-36">
                    <label for="ppe-hasta" class="{{ $etiquetaCampo }}">Hasta</label>
                    <input type="date" id="ppe-hasta" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="{{ $campo }}">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Filtrar
                    </button>
                    @if($hayFiltros)
                        <a href="{{ route('servicios.pppoe-eventos.index') }}" class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Limpiar
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Cliente</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Último evento</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Usuario PPPoE</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">IP / MAC</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Router</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Fuente</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($grupos as $grupo)
                        @php $ev = $grupo->ultimo_evento; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                @php
                                    $destinoDetalle = $grupo->cliente_id
                                        ? ['cliente_id' => $grupo->cliente_id]
                                        : ($ev?->servicio_id ? ['servicio_id' => $ev->servicio_id] : null);
                                @endphp
                                @if($destinoDetalle)
                                    <button type="button" class="ppe-cliente-btn" aria-haspopup="dialog"
                                            data-ppe-detalle="{{ $urlDetalle($destinoDetalle) }}"
                                            title="Ver los eventos de {{ $grupo->cliente_nombre }}">{{ $grupo->cliente_nombre }}</button>
                                @else
                                    <span class="font-medium">{{ $grupo->cliente_nombre }}</span>
                                @endif
                                <span class="ppe-conteo" title="{{ $cuantos($grupo->total_eventos, 'evento', 'eventos') }} {{ $periodo['etiqueta'] }} con los filtros actuales">
                                    @if($tipoActual !== $tipoUp)
                                        <span class="{{ $grupo->desconexiones > 0 ? 'is-down' : '' }}">{{ $cuantos($grupo->desconexiones, 'corte', 'cortes') }}</span>
                                    @endif
                                    @if($tipoActual === '')
                                        ·
                                    @endif
                                    @if($tipoActual !== $tipoDown)
                                        {{ $cuantos($grupo->conexiones, 'conexión', 'conexiones') }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($ev)
                                    <div class="flex flex-col gap-1">
                                        <span class="text-gray-700 dark:text-gray-200">{{ $ev->ocurrio_at?->format('d/m/Y H:i:s') }}</span>
                                        @if($ev->esPppoeConexion())
                                            <span class="pppoe-badge pppoe-badge--sm pppoe-badge--up">Conectado</span>
                                        @else
                                            <span class="pppoe-badge pppoe-badge--sm pppoe-badge--down">Desconectado</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">
                                {{ $ev?->usuario_pppoe ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @if($ev?->ip)
                                    <div class="font-mono text-xs">{{ $ev->ip }}</div>
                                @endif
                                @if($ev?->mac_address)
                                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $ev->mac_address }}</div>
                                @endif
                                @if(! $ev?->ip && ! $ev?->mac_address)
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $ev?->router?->nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $ev?->etiquetaFuente() ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($ev?->servicio_id)
                                    <a href="{{ route('servicios.herramientas-red', $ev->servicio_id) }}"
                                       class="inline-flex items-center rounded-lg border border-teal-600 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-300 dark:hover:bg-teal-950/40">
                                        Herramientas
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                No hay eventos PPPoE {{ $periodo['etiqueta'] }} con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($grupos->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $grupos->links() }}
            </div>
        @endif
    </div>

    <p class="text-xs text-gray-400 dark:text-gray-500">
        Webhook MikroTik: <span class="font-mono">POST /api/v1/webhooks/mikrotik/pppoe</span>
        · Solo se registra un evento cuando cambia el estado de la sesión.
    </p>
</div>

<div id="ppe-fondo" class="ppe-fondo" hidden></div>
<aside id="ppe-panel" class="ppe-panel" role="dialog" aria-modal="true" aria-labelledby="ppe-panel-titulo" tabindex="-1" hidden
       data-entrar="{{ route('login') }}"></aside>
@endsection

@push('scripts')
<script>
(function () {
    var panel = document.getElementById('ppe-panel');
    var fondo = document.getElementById('ppe-fondo');
    if (!panel || !fondo) return;
    var origen = null;
    var pedido = 0;

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function aviso(titulo, mensaje, extra) {
        panel.innerHTML =
            '<header class="ppe-panel-cabecera"><div class="ppe-panel-titulos">' +
            '<h2 id="ppe-panel-titulo" class="ppe-panel-titulo">' + escapar(titulo) + '</h2></div>' +
            '<button type="button" class="ppe-btn ppe-btn-icono" data-ppe-cerrar aria-label="Cerrar detalle">✕</button></header>' +
            '<div class="ppe-panel-cuerpo"><p class="ppe-seccion-ayuda" role="status">' + escapar(mensaje) + '</p>' + (extra || '') + '</div>';
    }

    function abrir(url, boton) {
        origen = boton || null;
        var turno = ++pedido;
        aviso(boton ? boton.textContent.trim() : 'Detalle', 'Cargando eventos…');
        panel.hidden = false;
        fondo.hidden = false;
        document.body.style.overflow = 'hidden';
        panel.focus();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
            .then(function (respuesta) {
                if (turno !== pedido) return null;
                if (respuesta.status === 401 || respuesta.status === 419 || respuesta.redirected) {
                    aviso('Tu sesión se cerró', 'Entrá de nuevo para ver los eventos.',
                        '<p><a class="ppe-btn" href="' + panel.getAttribute('data-entrar') + '">Entrar</a></p>');
                    return null;
                }
                if (respuesta.status === 404) {
                    aviso('Sin eventos', 'No encontré eventos PPPoE para este cliente.');
                    return null;
                }
                if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);
                return respuesta.text();
            })
            .then(function (html) {
                if (html === null || turno !== pedido) return;
                panel.innerHTML = html;
                panel.scrollTop = 0;
            })
            .catch(function () {
                if (turno !== pedido) return;
                aviso('No pude cargar el detalle', 'Revisá la conexión y probá de nuevo.');
            });
    }

    function cerrar() {
        if (panel.hidden) return;
        pedido++;
        panel.hidden = true;
        fondo.hidden = true;
        panel.innerHTML = '';
        document.body.style.overflow = '';
        if (origen && document.contains(origen)) origen.focus();
        origen = null;
    }

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-ppe-detalle]');
        if (boton) {
            evento.preventDefault();
            abrir(boton.getAttribute('data-ppe-detalle'), boton);
            return;
        }
        if (evento.target.closest('[data-ppe-cerrar]') || evento.target === fondo) cerrar();
    });

    document.addEventListener('keydown', function (evento) {
        if (panel.hidden) return;
        if (evento.key === 'Escape') {
            cerrar();
            return;
        }
        if (evento.key !== 'Tab') return;
        // Mantiene el foco dentro del panel mientras está abierto.
        var enfocables = panel.querySelectorAll('a[href], button:not([disabled])');
        if (enfocables.length === 0) {
            evento.preventDefault();
            return;
        }
        var primero = enfocables[0];
        var ultimo = enfocables[enfocables.length - 1];
        if (!panel.contains(document.activeElement)) {
            evento.preventDefault();
            primero.focus();
        } else if (evento.shiftKey && (document.activeElement === primero || document.activeElement === panel)) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });

    // Filtro por gravedad de la lista "Requieren atención".
    var botonesNivel = document.querySelectorAll('[data-ppe-nivel]');
    Array.prototype.forEach.call(botonesNivel, function (boton) {
        boton.addEventListener('click', function () {
            var nivel = boton.getAttribute('data-ppe-nivel');
            Array.prototype.forEach.call(botonesNivel, function (otro) {
                otro.setAttribute('aria-pressed', otro === boton ? 'true' : 'false');
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-ppe-fila-nivel]'), function (fila) {
                fila.hidden = nivel !== '' && fila.getAttribute('data-ppe-fila-nivel') !== nivel;
            });
        });
    });

    // Menú "⋯" de cada fila: se posiciona fijo para que no lo corte el scroll de la lista.
    var menuAbierto = null;

    function cerrarMenu(devolverFoco) {
        if (!menuAbierto) return;
        menuAbierto.menu.hidden = true;
        menuAbierto.boton.setAttribute('aria-expanded', 'false');
        if (devolverFoco) menuAbierto.boton.focus();
        menuAbierto = null;
    }

    function abrirMenu(boton) {
        var menu = boton.parentNode.querySelector('.ppe-menu');
        if (!menu) return;
        menu.hidden = false;
        boton.setAttribute('aria-expanded', 'true');
        var caja = boton.getBoundingClientRect();
        var alto = menu.offsetHeight;
        var arriba = caja.bottom + 4;
        if (arriba + alto > window.innerHeight - 8) arriba = Math.max(8, caja.top - alto - 4);
        menu.style.top = arriba + 'px';
        menu.style.left = Math.max(8, caja.right - menu.offsetWidth) + 'px';
        menuAbierto = { menu: menu, boton: boton };
        var primero = menu.querySelector('[role="menuitem"]');
        if (primero) primero.focus();
    }

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-ppe-menu]');
        if (boton) {
            var mismo = menuAbierto && menuAbierto.boton === boton;
            cerrarMenu(false);
            if (!mismo) abrirMenu(boton);
            return;
        }
        var copiar = evento.target.closest('[data-ppe-copiar]');
        if (copiar && navigator.clipboard) {
            navigator.clipboard.writeText(copiar.getAttribute('data-ppe-copiar')).catch(function () {});
        }
        cerrarMenu(false);
    });

    document.addEventListener('keydown', function (evento) {
        if (!menuAbierto) return;
        if (evento.key === 'Escape') {
            evento.stopPropagation();
            cerrarMenu(true);
            return;
        }
        if (evento.key !== 'ArrowDown' && evento.key !== 'ArrowUp') return;
        var items = Array.prototype.slice.call(menuAbierto.menu.querySelectorAll('[role="menuitem"]'));
        var actual = items.indexOf(document.activeElement);
        var paso = evento.key === 'ArrowDown' ? 1 : -1;
        evento.preventDefault();
        items[(actual + paso + items.length) % items.length].focus();
    }, true);

    window.addEventListener('scroll', function () { cerrarMenu(false); }, true);
    window.addEventListener('resize', function () { cerrarMenu(false); });
})();
</script>
@endpush
