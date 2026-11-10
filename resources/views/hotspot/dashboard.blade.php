@extends('layouts.app')

@php
    $estados = \App\Models\Servicio::estadosDisponibles();
    $linkUser = 'underline underline-offset-2 decoration-gray-400 dark:decoration-gray-500 text-gray-900 dark:text-gray-100 hover:text-gray-700 dark:hover:text-white';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-11 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $card = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800';
    $estadoRouter = function ($r): array {
        $e = strtolower((string) ($r->estado ?? ''));
        if ($e === \App\Models\Router::ESTADO_CONECTADO) {
            return ['is-ok', 'En línea'];
        }
        if ($e === \App\Models\Router::ESTADO_DESCONECTADO) {
            return ['is-down', 'Caído'];
        }

        return ['', 'Sin datos'];
    };
    $nodoSeleccionado = '';
    if ($selectedRouter) {
        foreach ($routersPorNodo as $nodoNombre => $grupo) {
            if ($grupo->contains(fn ($r) => (int) $r->router_id === (int) $selectedRouter->router_id)) {
                $nodoSeleccionado = (string) $nodoNombre;
                break;
            }
        }
    }
    $lupa = '<svg class="w-4 h-4 flex-none text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="2"></circle><path stroke-linecap="round" stroke-width="2" d="m20 20-3.5-3.5"></path></svg>';
    $vista = $vista ?? 'sesiones';
    $puedePerfiles = auth()->user()?->tienePermiso('servicios-hotspot-perfiles.ver') || auth()->user()?->tienePermiso('servicios.ver');
    $titulosHotspot = [
        'sesiones' => ['Sesiones hotspot', 'Quién está en el Wi‑Fi de un router y qué cuentas no tienen sesión.'],
        'usuarios' => ['Usuarios hotspot', 'Cuentas del locker: documento, PIN y hasta tres usuarios por cliente.'],
        'perfiles' => ['Perfiles hotspot', 'Velocidad y cuota que usa cada cuenta.'],
    ];
    $tituloPagina = $titulosHotspot[$vista][0] ?? $titulosHotspot['sesiones'][0];
    $subtituloPagina = $titulosHotspot[$vista][1] ?? $titulosHotspot['sesiones'][1];
    $menuHotspot = function (string $destino, array $extra = []) use ($selectedRouter) {
        return route('hotspot.dashboard', array_filter(array_merge([
            'vista' => $destino,
            'router_id' => $selectedRouter?->router_id,
        ], $extra), fn ($valor) => $valor !== null && $valor !== ''));
    };
    $sesiones = $sesiones ?? [];
    $offline = $offline ?? collect();
    $puedeCrear = (bool) auth()->user()?->tienePermiso('servicios.editar');
    $macsBloqueadas = $macsBloqueadas ?? [];
    $nVivas = count($sesiones);
    $nLocker = 0;
    $nPortal = 0;
    $nSinLocker = 0;
    foreach ($sesiones as $filaConteo) {
        $origenConteo = (string) ($filaConteo['origen'] ?? '');
        if ($origenConteo === 'locker') {
            $nLocker++;
        } elseif (in_array($origenConteo, ['sin-sesion', 'sin-usuario', 'trial'], true)) {
            $nPortal++;
        } else {
            $nSinLocker++;
        }
    }
@endphp

@section('title', $tituloPagina)

@push('styles')
<style>
    .hsd-kpi {
        display: block;
        width: 100%;
        text-align: left;
        cursor: pointer;
    }
    .hsd-kpi:hover { border-color: #c084fc; }
    .hsd-kpi:focus-visible {
        outline: 2px solid #c084fc;
        outline-offset: 2px;
    }
    #hotspot-dash-page .hsd-kpi[aria-pressed="true"] {
        border-color: #9333ea;
        background: #faf5ff;
    }
    #hotspot-dash-page .hsd-kpi[aria-pressed="true"] .hsd-kpi-titulo { color: #6b21a8; }
    .hsd-kpi-alerta { color: #b45309; }
    .hsd-cuota { min-width: 11rem; }
    .hsd-bar {
        height: 6px;
        margin-top: 0.375rem;
        border-radius: 3px;
        background: #e5e7eb;
        overflow: hidden;
    }
    .hsd-bar-fill {
        height: 100%;
        border-radius: 3px;
        background: #9333ea;
    }
    .hsd-bar-fill.is-warn { background: #d97706; }
    .hsd-bar-fill.is-danger { background: #dc2626; }
    .hsd-cuota-pct { color: #111827; }
    .hsd-cuota-pct.is-warn { color: #b45309; }
    .hsd-cuota-pct.is-danger { color: #b91c1c; }
    .hsd-tag {
        padding: 2px 6px;
        border-radius: 4px;
        background: #fef3c7;
        color: #92400e;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
    }
    .hsd-grupo[hidden] { display: none; }
    .hsd-grupo-titulo th {
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
    }
    html.dark .hsd-grupo-titulo th {
        background: rgba(17, 24, 39, 0.4);
        border-top-color: #374151;
    }
    .hsd-estado.is-ok { color: #15803d; }
    .hsd-estado.is-warn { color: #b45309; }
    .hsd-estado.is-off { color: #b91c1c; }
    .hsd-btn {
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
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }
    .hsd-btn:hover { background: #f3f4f6; }
    .hsd-btn:focus-visible,
    .hsd-menu-item:focus-visible {
        outline: 2px solid #c084fc;
        outline-offset: 1px;
    }
    .hsd-btn-icon {
        width: 2.25rem;
        padding: 0;
        font-size: 1rem;
        line-height: 1;
    }
    .hsd-btn-danger {
        border-color: #fca5a5;
        color: #b91c1c;
    }
    .hsd-btn-danger:hover { background: #fef2f2; }
    .hsd-btn-primary {
        border-color: transparent;
        background: #9333ea;
        color: #fff;
        font-weight: 600;
    }
    .hsd-btn-primary:hover { background: #7e22ce; }
    .hsd-menu-wrap { display: inline-block; }
    .hsd-menu {
        position: fixed;
        z-index: 40;
        min-width: 11rem;
        padding: 0.25rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        text-align: left;
        white-space: normal;
    }
    .hsd-menu[hidden] { display: none; }
    .hsd-menu form { margin: 0; }
    .hsd-menu-item {
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
    .hsd-menu-item:hover { background: #f3f4f6; }
    .hsd-menu-item.is-danger { color: #b91c1c; }
    html.dark .hsd-tag {
        background: #3a2e0f;
        color: #fbbf24;
    }
    html.dark .hsd-estado.is-ok { color: #4ade80; }
    html.dark .hsd-estado.is-warn { color: #fbbf24; }
    html.dark .hsd-estado.is-off { color: #fca5a5; }
    html.dark .hsd-btn {
        border-color: #4b5563;
        color: #f3f4f6;
    }
    html.dark .hsd-btn:hover { background: #374151; }
    html.dark .hsd-btn-danger {
        border-color: #7f1d1d;
        color: #fca5a5;
    }
    html.dark .hsd-btn-danger:hover { background: rgba(127, 29, 29, 0.3); }
    html.dark .hsd-btn-primary {
        border-color: transparent;
        background: #9333ea;
        color: #fff;
    }
    html.dark .hsd-btn-primary:hover { background: #a855f7; }
    html.dark .hsd-menu {
        background: #1f2937;
        border-color: #4b5563;
    }
    html.dark .hsd-menu-item { color: #f3f4f6; }
    html.dark .hsd-menu-item:hover { background: #374151; }
    html.dark .hsd-menu-item.is-danger { color: #fca5a5; }
    html.dark .hsd-kpi:hover { border-color: #a855f7; }
    html.dark #hotspot-dash-page .hsd-kpi[aria-pressed="true"] {
        border-color: #a855f7;
        background: rgba(107, 33, 168, 0.25);
    }
    html.dark #hotspot-dash-page .hsd-kpi[aria-pressed="true"] .hsd-kpi-titulo { color: #e9d5ff; }
    html.dark .hsd-kpi-alerta { color: #fbbf24; }
    html.dark .hsd-bar { background: #374151; }
    html.dark .hsd-bar-fill { background: #a855f7; }
    html.dark .hsd-bar-fill.is-warn { background: #f59e0b; }
    html.dark .hsd-bar-fill.is-danger { background: #ef4444; }
    html.dark .hsd-cuota-pct { color: #f3f4f6; }
    html.dark .hsd-cuota-pct.is-warn { color: #fbbf24; }
    html.dark .hsd-cuota-pct.is-danger { color: #fca5a5; }
    .hotspot-dash-tech > summary {
        cursor: pointer;
        list-style: none;
    }
    .hotspot-dash-tech > summary::-webkit-details-marker {
        display: none;
    }
    #hotspot-dash-page ::selection {
        background: #e9d5ff;
        color: #111827;
    }
    html.dark #hotspot-dash-page ::selection {
        background: #6b21a8;
        color: #f3f4f6;
    }
    #hotspot-dash-page {
        scrollbar-color: #d1d5db transparent;
    }
    html.dark #hotspot-dash-page {
        scrollbar-color: #4b5563 transparent;
    }
    .hsd-router {
        position: relative;
        width: 100%;
    }
    @media (min-width: 640px) {
        .hsd-router { width: 21rem; }
        #hotspot-dash-page .hsd-search { flex: 0 0 18.75rem; }
    }
    .hsd-router-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        min-height: 3rem;
        padding: 0.375rem 0.875rem;
        border: 1px solid #d1d5db;
        border-radius: 0.625rem;
        background: #fff;
        color: #111827;
        text-align: left;
    }
    .hsd-router-btn:focus-visible,
    .hsd-icon-btn:focus-visible,
    .hsd-auto-btn:focus-visible,
    .hsd-tab:focus-visible {
        outline: 2px solid #c084fc;
        outline-offset: 2px;
    }
    .hsd-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
        flex: none;
        background: #9ca3af;
    }
    .hsd-dot.is-ok { background: #22c55e; }
    .hsd-dot.is-down { background: #ef4444; }
    .hsd-pop {
        position: absolute;
        z-index: 30;
        top: calc(100% + 0.25rem);
        left: 0;
        right: 0;
        border: 1px solid #d1d5db;
        border-radius: 0.625rem;
        background: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        overflow: hidden;
    }
    .hsd-pop[hidden],
    .hsd-opt[hidden],
    .hsd-group[hidden] { display: none; }
    .hsd-pop-find {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid #e5e7eb;
    }
    #hotspot-dash-page .hsd-pop-find input,
    #hotspot-dash-page .hsd-search input {
        flex: 1;
        min-width: 0;
        padding: 0;
        border: 0;
        outline: 0;
        box-shadow: none;
        background: transparent;
        color: inherit;
        font-size: 0.875rem;
    }
    .hsd-list {
        max-height: 20rem;
        overflow-y: auto;
        padding: 0.25rem 0;
    }
    .hsd-group-title {
        padding: 0.5rem 0.75rem 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #6b7280;
    }
    .hsd-opt {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.5rem 0.75rem;
        color: #111827;
        text-decoration: none;
        font-size: 0.875rem;
    }
    .hsd-opt:hover,
    .hsd-opt.is-active { background: #f3f4f6; }
    .hsd-opt[aria-selected="true"] { background: #faf5ff; }
    .hsd-opt-meta {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .hsd-tabs { border-bottom: 1px solid #e5e7eb; }
    .hsd-tabs nav {
        margin-bottom: -1px;
        overflow-x: auto;
        overflow-y: hidden;
    }
    .hsd-tab {
        padding: 0.75rem 0.875rem;
        border-bottom: 2px solid transparent;
        font-size: 0.875rem;
        color: #4b5563;
        text-decoration: none;
        white-space: nowrap;
    }
    .hsd-tab:hover { color: #111827; }
    .hsd-tab.is-on {
        font-weight: 600;
        color: #6b21a8;
        border-bottom-color: #9333ea;
    }
    .hsd-search {
        flex: 1 1 100%;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        height: 2.5rem;
        padding: 0 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        color: #111827;
        box-sizing: border-box;
    }
    .hsd-search:focus-within,
    .hsd-pop-find:focus-within {
        box-shadow: inset 0 0 0 2px rgba(192, 132, 252, 0.45);
    }
    .hsd-auto {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        height: 2.5rem;
        padding: 0 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 0.8125rem;
        color: #4b5563;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .hsd-auto-ring {
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 9999px;
        flex: none;
        background: conic-gradient(#9333ea var(--p, 100%), #d1d5db 0);
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2.5px), #000 calc(100% - 2px));
        mask: radial-gradient(farthest-side, transparent calc(100% - 2.5px), #000 calc(100% - 2px));
    }
    .hsd-auto-btn {
        padding: 0;
        border: 0;
        background: transparent;
        color: #7e22ce;
        font-size: 0.8125rem;
        font-weight: 500;
    }
    .hsd-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        flex: none;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #f3f4f6;
        color: #1f2937;
    }
    .hsd-icon-btn:hover { background: #e5e7eb; }
    .hsd-icon-btn.is-busy svg { animation: hsd-spin 0.8s linear infinite; }
    @keyframes hsd-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) {
        .hsd-icon-btn.is-busy svg { animation: none; }
    }
    html.dark .hsd-router-btn,
    html.dark .hsd-pop,
    html.dark .hsd-search {
        background: #1f2937;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    html.dark .hsd-pop-find,
    html.dark .hsd-tabs,
    html.dark .hsd-auto { border-color: #374151; }
    html.dark .hsd-opt { color: #f3f4f6; }
    html.dark .hsd-opt:hover,
    html.dark .hsd-opt.is-active { background: #374151; }
    html.dark .hsd-opt[aria-selected="true"] { background: rgba(107, 33, 168, 0.35); }
    html.dark .hsd-opt-meta,
    html.dark .hsd-group-title { color: #9ca3af; }
    html.dark .hsd-tab { color: #d1d5db; }
    html.dark .hsd-tab:hover { color: #fff; }
    html.dark .hsd-tab.is-on {
        color: #f3e8ff;
        border-bottom-color: #a855f7;
    }
    html.dark .hsd-auto { color: #d1d5db; }
    html.dark .hsd-auto-ring { background: conic-gradient(#a855f7 var(--p, 100%), #4b5563 0); }
    html.dark .hsd-auto-btn { color: #d8b4fe; }
    html.dark .hsd-icon-btn {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    html.dark .hsd-icon-btn:hover { background: #4b5563; }
</style>
@endpush

@section('content')
<div id="hotspot-dash-page" class="max-w-7xl mx-auto space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $tituloPagina }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $subtituloPagina }}</p>
        </div>
        @if($vista === 'sesiones')
        <div id="hotspot-dash-router-box" class="hsd-router" data-loaded="{{ $selectedRouter?->router_id }}">
            <span id="hotspot-dash-router-label" class="block mb-1 text-xs text-gray-500 dark:text-gray-400">Router</span>
            <button type="button" id="hotspot-dash-router-btn" class="hsd-router-btn"
                aria-haspopup="listbox" aria-expanded="false" aria-controls="hotspot-dash-router-pop"
                aria-labelledby="hotspot-dash-router-label hotspot-dash-router-actual">
                <span id="hotspot-dash-router-actual" class="flex items-center gap-3 flex-1 min-w-0">
                    @if($selectedRouter)
                        @php
                            [$estadoClase, $estadoTexto] = $estadoRouter($selectedRouter);
                        @endphp
                        <span class="hsd-dot {{ $estadoClase }}" aria-hidden="true"></span>
                        <span class="flex flex-col min-w-0">
                            <span class="text-sm font-semibold truncate">{{ $selectedRouter->nombre }}</span>
                            <span class="hsd-opt-meta truncate">{{ $selectedRouter->ip }}@if($nodoSeleccionado !== '') · {{ $nodoSeleccionado }}@endif · {{ $estadoTexto }}</span>
                        </span>
                    @else
                        <span class="text-sm text-gray-600 dark:text-gray-300">Elegí un router</span>
                    @endif
                </span>
                {!! $lupa !!}
            </button>
            <div id="hotspot-dash-router-pop" class="hsd-pop" hidden>
                <div class="hsd-pop-find">
                    {!! $lupa !!}
                    <input type="search" id="hotspot-dash-router-q" autocomplete="off" role="combobox"
                        aria-expanded="true" aria-controls="hotspot-dash-router-list" aria-autocomplete="list"
                        aria-label="Buscar router por nombre, IP o nodo"
                        placeholder="Buscar por nombre, IP o nodo">
                </div>
                <div id="hotspot-dash-router-list" class="hsd-list" role="listbox" aria-label="Routers">
                    @foreach($routersPorNodo as $nodoNombre => $grupo)
                        <div class="hsd-group" role="group" aria-label="{{ $nodoNombre }}">
                            <p class="hsd-group-title" aria-hidden="true">{{ $nodoNombre }}</p>
                            @foreach($grupo as $r)
                                @php
                                    [$estadoClase, $estadoTexto] = $estadoRouter($r);
                                    $esActual = $selectedRouter && (int) $selectedRouter->router_id === (int) $r->router_id;
                                @endphp
                                <a role="option" id="hsd-router-{{ $r->router_id }}" class="hsd-opt"
                                    href="{{ route('hotspot.dashboard', ['router_id' => $r->router_id]) }}"
                                    data-router-id="{{ $r->router_id }}"
                                    data-buscar="{{ mb_strtolower($r->nombre.' '.$r->ip.' '.$nodoNombre.' '.$estadoTexto) }}"
                                    aria-selected="{{ $esActual ? 'true' : 'false' }}">
                                    <span class="hsd-dot {{ $estadoClase }}" aria-hidden="true"></span>
                                    <span class="flex flex-col flex-1 min-w-0">
                                        <span class="font-medium truncate">{{ $r->nombre }}</span>
                                        <span class="hsd-opt-meta truncate">{{ $r->ip }}</span>
                                    </span>
                                    <span class="hsd-opt-meta">{{ $estadoTexto }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                    <p id="hotspot-dash-router-vacio" class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300" hidden>Ningún router coincide.</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="hsd-tabs flex flex-wrap items-end justify-between gap-x-3">
        <nav class="flex gap-1" aria-label="Administrar hotspot">
            <a href="{{ $menuHotspot('sesiones') }}" class="hsd-tab {{ $vista === 'sesiones' ? 'is-on' : '' }}" @if($vista === 'sesiones') aria-current="page" @endif>Sesiones</a>
            <a href="{{ $menuHotspot('usuarios') }}" class="hsd-tab {{ $vista === 'usuarios' ? 'is-on' : '' }}" @if($vista === 'usuarios') aria-current="page" @endif>Usuarios hotspot</a>
            @if($puedePerfiles)
                <a href="{{ $menuHotspot('perfiles') }}" class="hsd-tab {{ $vista === 'perfiles' ? 'is-on' : '' }}" @if($vista === 'perfiles') aria-current="page" @endif>Perfiles</a>
            @endif
        </nav>
        @if($vista === 'sesiones' && $selectedRouter)
            <div class="flex flex-wrap items-center gap-2 pb-2 w-full sm:w-auto">
                <label class="hsd-search">
                    {!! $lupa !!}
                    <input type="search" id="hotspot-dash-find" autocomplete="off"
                        aria-label="Buscar en ambas listas"
                        placeholder="Buscar MAC, IP, usuario o cliente">
                </label>
                <div class="hsd-auto">
                    <span id="hotspot-dash-auto-ring" class="hsd-auto-ring" aria-hidden="true"></span>
                    <span id="hotspot-dash-auto-text">Auto · 30 s</span>
                    <button type="button" id="hotspot-dash-auto-toggle" class="hsd-auto-btn" aria-label="Pausar actualización automática">Pausar</button>
                </div>
                <button type="button" id="hotspot-dash-refresh" class="hsd-icon-btn" title="Actualizar ahora" aria-label="Actualizar ahora">
                    @include('hotspot._icono', ['nombre' => 'sincronizar'])
                </button>
            </div>
        @endif
    </div>

    @if($vista === 'usuarios')
        @include('hotspot.paneles.usuarios')
    @elseif($vista === 'perfiles')
        @include('hotspot.paneles.perfiles')
    @elseif($selectedRouter)
        <div id="hotspot-dash-session-host"
            data-copy="Tu sesión se cerró. Entrá de nuevo para seguir las sesiones."
            data-entrar="{{ route('login') }}"></div>

        <div id="hotspot-dash-kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $nConCuenta = $nLocker + $nSinLocker;
                $offlineVistas = [];
                $nCuota80 = 0;
                foreach ($offline as $shVista) {
                    $vistaOff = \App\Services\RadiusHotspotSyncService::consumoVista(
                        (int) ($consumo[$shVista->username] ?? 0),
                        $shVista->hotspotPerfil?->cuota_gb
                    );
                    $offlineVistas[$shVista->getKey()] = $vistaOff;
                    if (! empty($vistaOff['tiene_cuota']) && (float) ($vistaOff['porcentaje'] ?? 0) >= 80) {
                        $nCuota80++;
                    }
                }
                foreach ($sesiones as $filaCuota) {
                    if (($filaCuota['origen'] ?? '') === 'locker'
                        && ! empty($filaCuota['cuota']['tiene_cuota'])
                        && (float) ($filaCuota['cuota']['porcentaje'] ?? 0) >= 80) {
                        $nCuota80++;
                    }
                }
                $nClientesOffline = $offline->pluck('cliente_id')->filter()->unique()->count();
                $kpis = [
                    [
                        'filtro' => 'en-linea',
                        'titulo' => 'En línea ahora',
                        'valor' => $routerError ? '—' : (string) $nVivas,
                        'detalle' => $routerError
                            ? 'No pude leer el router'
                            : $nConCuenta.' con cuenta · '.$nPortal.' en portal (trial)',
                        'alerta' => false,
                    ],
                    [
                        'filtro' => 'trial',
                        'titulo' => 'Sin cuenta (trial)',
                        'valor' => $routerError ? '—' : (string) $nPortal,
                        'detalle' => 'Conectado al portal, sin login',
                        'alerta' => ! $routerError && $nPortal > 0,
                    ],
                    [
                        'filtro' => 'sin-sesion',
                        'titulo' => 'Cuentas sin sesión',
                        'valor' => (string) $offline->count(),
                        'detalle' => $nClientesOffline === 1 ? 'De 1 cliente' : 'De '.$nClientesOffline.' clientes',
                        'alerta' => false,
                    ],
                    [
                        'filtro' => 'cuota80',
                        'titulo' => 'Cuota sobre 80 %',
                        'valor' => (string) $nCuota80,
                        'detalle' => 'Cuentas por agotar su plan',
                        'alerta' => $nCuota80 > 0,
                    ],
                ];
            @endphp
            @foreach($kpis as $kpi)
                <button type="button" class="hsd-kpi {{ $card }} px-4 py-3" data-tarjeta="{{ $kpi['filtro'] }}" aria-pressed="false">
                    <span class="hsd-kpi-titulo block text-sm text-gray-600 dark:text-gray-300">{{ $kpi['titulo'] }}</span>
                    <span class="block mt-1 text-2xl font-bold tabular-nums {{ $kpi['alerta'] ? 'hsd-kpi-alerta' : 'text-gray-900 dark:text-gray-100' }}">{{ $kpi['valor'] }}</span>
                    <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['detalle'] }}</span>
                </button>
            @endforeach
        </div>

        <div id="hotspot-dash-watch" class="relative space-y-4">
            <div id="hotspot-dash-skel" class="{{ $card }} px-4 py-6 space-y-3" style="display: none;" aria-hidden="true">
                <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-8 w-2/3 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
            </div>

            <section id="hotspot-dash-vivos" class="relative {{ $card }} overflow-hidden" aria-labelledby="hotspot-dash-vivos-title">
                @if($routerError)
                    <div id="hotspot-dash-stale" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 px-4" style="display:flex;background:rgba(17,24,39,0.8)" role="alert">
                        <p class="max-w-md text-center text-sm font-medium text-white">No pude leer las sesiones de {{ $selectedRouter->nombre }}.</p>
                        <details class="hotspot-dash-tech max-w-md text-center">
                            <summary class="text-sm text-gray-200 underline underline-offset-2">Detalle técnico</summary>
                            <p class="mt-2 text-sm text-gray-200 break-words">{{ $routerError }}</p>
                        </details>
                        <a href="{{ route('hotspot.dashboard', ['router_id' => $selectedRouter->router_id]) }}" class="{{ $btnPrimary }}" data-dash-refresh>Reintentar</a>
                    </div>
                @endif
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="hotspot-dash-vivos-title" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2" aria-live="polite">
                            @unless($routerError)
                                <span class="inline-block h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span>
                            @endunless
                            Sesiones en línea
                            @unless($routerError)
                                <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ count($sesiones) }}</span>
                            @endunless
                        </h2>
                    </div>
                    @if($consultadoEn)
                        <p id="hotspot-dash-pulse" class="text-xs text-gray-500 dark:text-gray-400 tabular-nums"
                            data-consultado="{{ $consultadoEn->toIso8601String() }}">Actualizado ahora</p>
                    @endif
                </div>

                @if($routerError)
                    <div class="px-4 py-16" aria-hidden="true"></div>
                @elseif(count($sesiones) === 0)
                    <div class="px-4 py-8 text-center" role="status">
                        <p class="text-sm text-gray-700 dark:text-gray-200">Nadie conectado en {{ $selectedRouter->nombre }}.</p>
                    </div>
                @else
                    <div id="hotspot-dash-vivos-scroll" class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario / equipo</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Consumo del plan</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Tráfico ahora</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">En línea</th>
                                    <th scope="col" class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($sesiones as $fila)
                                    @php
                                        $esPortal = in_array($fila['origen'], ['sin-sesion', 'sin-usuario', 'trial'], true);
                                        $esLocker = $fila['origen'] === 'locker';
                                        $pctFila = $esLocker && ! empty($fila['cuota']['tiene_cuota'])
                                            ? (float) ($fila['cuota']['porcentaje'] ?? 0)
                                            : null;
                                        $macOk = preg_match('/^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/', (string) $fila['mac']) === 1;
                                        $etiquetaPortal = $fila['origen'] === 'trial' ? 'TRIAL' : 'PORTAL';
                                        $quien = $esPortal ? 'Invitado '.$fila['mac'] : $fila['username'];
                                        $estadoCodigo = (string) $fila['estado'];
                                        $estadoTexto = $estadoCodigo !== ''
                                            ? 'Cuenta '.preg_replace('/o$/', 'a', mb_strtolower((string) ($estados[$estadoCodigo] ?? $estadoCodigo)))
                                            : '';
                                        $estadoTono = ['A' => 'ok', 'S' => 'warn'][$estadoCodigo] ?? 'off';
                                        $puedeCortar = $puedeCrear && $macOk;
                                        $hayMenu = $macOk || $esLocker;
                                    @endphp
                                    <tr class="hotspot-dash-fila {{ $esLocker ? '' : 'bg-amber-50/60 dark:bg-amber-950/20' }}"
                                        data-grupo="{{ $esPortal ? 'trial' : 'cuenta' }}"
                                        data-pct="{{ $pctFila ?? '' }}"
                                        data-buscar="{{ mb_strtolower($fila['username'].' '.$fila['ip'].' '.$fila['mac'].' '.$fila['cliente'].' '.$fila['perfil'].($esPortal ? ' invitado '.$etiquetaPortal : '')) }}">
                                        <td class="px-4 py-3">
                                            @if($esPortal)
                                                <span class="flex items-center gap-2">
                                                    <span class="font-semibold text-gray-900 dark:text-gray-100">Invitado</span>
                                                    <span class="hsd-tag">{{ $etiquetaPortal }}</span>
                                                </span>
                                            @elseif($esLocker && $fila['username'] !== '—')
                                                <a href="{{ $menuHotspot('usuarios', ['buscar' => $fila['username']]) }}" class="font-semibold {{ $linkUser }}">{{ $fila['username'] }}</a>
                                            @else
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $fila['username'] }}</span>
                                            @endif
                                            <span class="block mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $fila['ip'] }} · {{ $fila['mac'] }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($esLocker)
                                                <span class="block max-w-[14rem] truncate text-gray-900 dark:text-gray-100" title="{{ $fila['cliente'] }}">{{ $fila['cliente'] !== '' ? $fila['cliente'] : '—' }}</span>
                                                @if($estadoTexto !== '')
                                                    <span class="block text-xs hsd-estado is-{{ $estadoTono }}">{{ $estadoTexto }}</span>
                                                @endif
                                            @elseif($esPortal)
                                                <span class="text-gray-500 dark:text-gray-400">Sin cuenta asignada</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">Usuario que no está en Infinity</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @include('hotspot._cuota-barra', [
                                                'perfil' => $fila['origen'] === 'trial' ? '' : $fila['perfil'],
                                                'vista' => $fila['cuota'] ?? null,
                                                'trial' => $fila['origen'] === 'trial',
                                                'trialResta' => ! empty($fila['trial_tiempo']) ? ($fila['cuota']['etiqueta'] ?? '') : '',
                                            ])
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap"
                                            data-trafico="{{ $fila['mac'] }}"
                                            data-subida="{{ $fila['bytes_subida'] ?? '' }}"
                                            data-bajada="{{ $fila['bytes_bajada'] ?? '' }}"
                                            title="Promedio desde la actualización anterior">—</td>
                                        <td class="px-4 py-3 whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100">{{ $fila['uptime_legible'] ?? $fila['uptime'] }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                @if($esPortal)
                                                    <a href="{{ $menuHotspot('usuarios') }}" class="hsd-btn hsd-btn-primary">Asignar cuenta</a>
                                                @elseif($puedeCortar)
                                                    <form method="POST" action="{{ route('hotspot.dashboard.desconectar', $selectedRouter) }}" class="inline" onsubmit="return confirm(@json('¿Desconectar a '.$quien.'? Si entró con la cuenta del celular, no vuelve a entrar solo.'))">
                                                        @csrf
                                                        <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                        <button type="submit" class="hsd-btn hsd-btn-danger">Desconectar</button>
                                                    </form>
                                                @endif
                                                @if($hayMenu)
                                                    <div class="hsd-menu-wrap">
                                                        <button type="button" class="hsd-btn hsd-btn-icon" data-menu-btn aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de {{ $quien }}">⋯</button>
                                                        <div class="hsd-menu" role="menu" hidden>
                                                            @if($macOk)
                                                                <button type="button" role="menuitem" class="hsd-menu-item" data-copiar="{{ $fila['mac'] }}" data-copiar-texto>Copiar MAC</button>
                                                            @endif
                                                            @if($esLocker && $fila['username'] !== '—')
                                                                <a role="menuitem" class="hsd-menu-item" href="{{ $menuHotspot('usuarios', ['buscar' => $fila['username']]) }}">Ver usuario</a>
                                                            @endif
                                                            @if($esLocker && $fila['cliente_id'] && $puedeCrear)
                                                                <a role="menuitem" class="hsd-menu-item" href="{{ route('hotspot.clientes.edit', $fila['cliente_id']) }}">Editar cuenta</a>
                                                            @endif
                                                            @if($puedeCortar)
                                                                @if($esPortal)
                                                                    <form method="POST" action="{{ route('hotspot.dashboard.desconectar', $selectedRouter) }}" onsubmit="return confirm(@json('¿Desconectar a '.$quien.'?'))">
                                                                        @csrf
                                                                        <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                                        <button type="submit" role="menuitem" class="hsd-menu-item is-danger">Desconectar</button>
                                                                    </form>
                                                                @endif
                                                                <form method="POST" action="{{ route('hotspot.dashboard.bloquear', $selectedRouter) }}" onsubmit="return confirm(@json('¿Bloquear '.$fila['mac'].'? No va a poder entrar al hotspot hasta que la desbloquees.'))">
                                                                    @csrf
                                                                    <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                                    <button type="submit" role="menuitem" class="hsd-menu-item is-danger">Bloquear MAC</button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p id="hotspot-dash-vivos-vacio" class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300" style="display:none" role="status">Ninguna sesión coincide con el filtro.</p>
                @endif
                @if(! $routerError && count($macsBloqueadas) > 0)
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">MACs bloqueadas</p>
                        <ul class="mt-2 flex flex-col gap-2">
                            @foreach($macsBloqueadas as $macBloqueada)
                                <li class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm text-gray-800 dark:text-gray-100">{{ $macBloqueada }}</span>
                                    @if($puedeCrear)
                                        <form method="POST" action="{{ route('hotspot.dashboard.desbloquear', $selectedRouter) }}" class="inline" onsubmit="return confirm(@json('¿Desbloquear '.$macBloqueada.'?'))">
                                            @csrf
                                            <input type="hidden" name="mac" value="{{ $macBloqueada }}">
                                            <button type="submit" class="hsd-btn" aria-label="Desbloquear {{ $macBloqueada }}">Desbloquear</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section id="hotspot-dash-offline" class="{{ $card }} overflow-hidden" aria-labelledby="hotspot-dash-offline-title">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="hotspot-dash-offline-title" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            Cuentas sin sesión
                            <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ $offline->count() }}</span>
                        </h2>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Cuentas de este router que ahora no están en línea.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 p-0.5 text-sm" role="group" aria-label="Filtrar por cuota">
                            <button type="button" data-cuota-filtro="todos" class="px-2.5 py-1 rounded-md font-medium text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700" aria-pressed="true">Todos</button>
                            <button type="button" data-cuota-filtro="cuota" class="px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300" aria-pressed="false">Con cuota</button>
                            <button type="button" data-cuota-filtro="libre" class="px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300" aria-pressed="false">Ilimitadas</button>
                        </div>
                        @if($puedeCrear)
                            <a href="{{ route('hotspot.create') }}" class="{{ $btnPrimary }}">+ Nuevo usuario</a>
                        @endif
                    </div>
                </div>
                @if($offline->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">
                        @if($mappedCount === 0)
                            Este router no tiene cuentas.
                        @else
                            Todas las cuentas de este router están en Sesiones en línea.
                        @endif
                    </p>
                @else
                    @php
                        $nombreClienteDe = function ($sh): string {
                            $cliente = $sh->cliente ?? $sh->servicio?->cliente;

                            return trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
                        };
                        $gruposOffline = $offline
                            ->groupBy(fn ($sh) => $sh->cliente_id ? 'c'.$sh->cliente_id : 'u'.$sh->getKey())
                            ->sortBy(fn ($cuentas) => mb_strtolower($nombreClienteDe($cuentas->first())));
                        $ultimasConexiones = $ultimasConexiones ?? [];
                    @endphp
                    <div id="hotspot-dash-offline-scroll" class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Consumo del plan</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Última conexión</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cuenta</th>
                                    <th scope="col" class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                                </tr>
                            </thead>
                            @foreach($gruposOffline as $grupoId => $cuentas)
                                @php
                                    $nombre = $nombreClienteDe($cuentas->first());
                                    $clienteId = $cuentas->first()->cliente_id;
                                    $nCuentas = $cuentas->count();
                                @endphp
                                <tbody class="hsd-grupo" data-grupo-offline="{{ $grupoId }}">
                                    <tr class="hsd-grupo-titulo">
                                        <th scope="rowgroup" colspan="5" class="px-4 py-2 text-left text-sm font-normal">
                                            @if($clienteId)
                                                <a href="{{ route('hotspot.clientes.edit', $clienteId) }}" class="font-semibold {{ $linkUser }}">{{ $nombre !== '' ? $nombre : 'Cliente sin nombre' }}</a>
                                            @else
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $nombre !== '' ? $nombre : 'Sin cliente' }}</span>
                                            @endif
                                            <span class="text-gray-500 dark:text-gray-400"> · {{ $nCuentas === 1 ? '1 cuenta' : $nCuentas.' cuentas' }}</span>
                                        </th>
                                    </tr>
                                    @foreach($cuentas as $sh)
                                        @php
                                            $codigo = (string) ($sh->servicio?->estado ?? '');
                                            $cuotaVista = $offlineVistas[$sh->getKey()];
                                            $perfilNombre = $sh->hotspotPerfil?->nombreDistintoDeCuota() ?? '';
                                            $ultima = $ultimasConexiones[$sh->username] ?? null;
                                            $estadoTexto = $codigo !== ''
                                                ? ucfirst((string) preg_replace('/o$/', 'a', mb_strtolower((string) ($estados[$codigo] ?? $codigo))))
                                                : '';
                                            $estadoTono = ['A' => 'ok', 'S' => 'warn'][$codigo] ?? 'off';
                                        @endphp
                                        <tr class="hotspot-dash-fila border-t border-gray-200 dark:border-gray-700"
                                            data-cuota="{{ ! empty($cuotaVista['tiene_cuota']) ? 'cuota' : 'libre' }}"
                                            data-pct="{{ ! empty($cuotaVista['tiene_cuota']) ? (float) ($cuotaVista['porcentaje'] ?? 0) : '' }}"
                                            data-buscar="{{ mb_strtolower($sh->username.' '.$nombre.' '.$perfilNombre.' '.($ultima['mac'] ?? '')) }}">
                                            <td class="px-4 py-3">
                                                <a href="{{ $menuHotspot('usuarios', ['buscar' => $sh->username]) }}" class="font-semibold {{ $linkUser }}">{{ $sh->username }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                                @include('hotspot._cuota-barra', [
                                                    'perfil' => $perfilNombre,
                                                    'vista' => $cuotaVista,
                                                ])
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($ultima)
                                                    <span class="text-gray-700 dark:text-gray-200" title="{{ $ultima['fecha'] }}">{{ $ultima['texto'] }}</span>@if($ultima['mac'] !== '')<span class="font-mono text-xs text-gray-500 dark:text-gray-400"> · {{ $ultima['mac'] }}</span>@endif
                                                @else
                                                    <span class="text-gray-500 dark:text-gray-400">Nunca se conectó</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($estadoTexto !== '')
                                                    <span class="hsd-estado is-{{ $estadoTono }}">{{ $estadoTexto }}</span>
                                                @else
                                                    <span class="text-gray-500 dark:text-gray-400">Sin servicio</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1">
                                                    <form method="POST" action="{{ route('hotspot.sync', $sh) }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="from_dashboard" value="1">
                                                        <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                        <button type="submit" class="hsd-btn">Sincronizar</button>
                                                    </form>
                                                    <div class="hsd-menu-wrap">
                                                        <button type="button" class="hsd-btn hsd-btn-icon" data-menu-btn aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de {{ $sh->username }}">⋯</button>
                                                        <div class="hsd-menu" role="menu" hidden>
                                                            <a role="menuitem" class="hsd-menu-item" href="{{ $menuHotspot('usuarios', ['buscar' => $sh->username]) }}">Ver usuario</a>
                                                            @if($sh->cliente_id && $puedeCrear)
                                                                <a role="menuitem" class="hsd-menu-item" href="{{ route('hotspot.clientes.edit', $sh->cliente_id) }}">Editar cuenta</a>
                                                                <form method="POST" action="{{ route('hotspot.clientes.destroy', [$sh->cliente_id, $sh]) }}" onsubmit="return confirm(@json('¿Quitar la cuenta '.$sh->username.'? Deja de poder entrar al hotspot.'))">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <input type="hidden" name="from_dashboard" value="1">
                                                                    <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                                    <button type="submit" role="menuitem" class="hsd-menu-item is-danger">Quitar cuenta</button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                    <p id="hotspot-dash-offline-vacio" class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300" style="display:none" role="status">Ninguna cuenta coincide con el filtro.</p>
                @endif
            </section>
        </div>
    @else
        <div class="{{ $card }} px-4 py-10 text-center">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Elegí un router.</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Vas a ver quién está conectado al hotspot y las cuentas de ese router que no tienen sesión.</p>
        </div>
    @endif
</div>
@endsection

@if($vista === 'sesiones')
@push('scripts')
<script>
(function () {
    var box = document.getElementById('hotspot-dash-router-box');
    var btn = document.getElementById('hotspot-dash-router-btn');
    var pop = document.getElementById('hotspot-dash-router-pop');
    var filter = document.getElementById('hotspot-dash-router-q');
    var list = document.getElementById('hotspot-dash-router-list');
    var find = document.getElementById('hotspot-dash-find');
    var watch = document.getElementById('hotspot-dash-watch');
    var skel = document.getElementById('hotspot-dash-skel');
    var vivos = document.getElementById('hotspot-dash-vivos');
    var pulse = document.getElementById('hotspot-dash-pulse');
    var autoRing = document.getElementById('hotspot-dash-auto-ring');
    var autoText = document.getElementById('hotspot-dash-auto-text');
    var autoToggle = document.getElementById('hotspot-dash-auto-toggle');
    var refreshBtn = document.getElementById('hotspot-dash-refresh');
    var sessionHost = document.getElementById('hotspot-dash-session-host');
    var loaded = box ? String(box.getAttribute('data-loaded') || '') : '';
    var leaving = false;
    var dead = false;
    var busy = false;
    var userPaused = false;
    var activa = null;
    var cuotaFiltro = 'todos';
    var tarjeta = '';
    var menuAbierto = null;
    var muestras = {};
    var REFRESH_S = 30;
    var PAUSA_KEY = 'hotspotDashPausa';
    var remain = REFRESH_S;
    var pingUrl = @json(route('sesion.ping', [], false));
    var btnPrimaryClass = @json($btnPrimary);
    var filtroOn = 'px-2.5 py-1 rounded-md font-medium text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700';
    var filtroOff = 'px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300';

    try { userPaused = window.sessionStorage.getItem(PAUSA_KEY) === '1'; } catch (err) { userPaused = false; }

    function popOpen() {
        return !!(pop && !pop.hidden);
    }

    function isPaused() {
        if (leaving || dead || document.hidden || !loaded) return true;
        if (userPaused || popOpen() || menuAbierto) return true;
        if (find && document.activeElement === find) return true;
        return false;
    }

    function showSkel() {
        leaving = true;
        if (skel) skel.style.display = 'block';
        if (vivos) vivos.style.display = 'none';
        var offline = document.getElementById('hotspot-dash-offline');
        if (offline) offline.style.display = 'none';
        var stale = document.getElementById('hotspot-dash-stale');
        if (stale) stale.style.display = 'none';
    }

    function ageText() {
        if (!pulse) return 'ahora';
        var iso = pulse.getAttribute('data-consultado');
        if (!iso) return 'ahora';
        var then = Date.parse(iso);
        if (!then) return 'ahora';
        var sec = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (sec < 5) return 'ahora';
        if (sec < 60) return 'hace ' + sec + ' s';
        if (sec < 3600) return 'hace ' + Math.floor(sec / 60) + ' min';
        return 'hace ' + Math.floor(sec / 3600) + ' h';
    }

    function paintPulse() {
        var paused = isPaused();
        if (autoRing) {
            autoRing.style.setProperty('--p', (dead || paused) ? '0%' : (Math.max(0, Math.round((remain / REFRESH_S) * 100)) + '%'));
        }
        if (autoText) {
            autoText.textContent = dead ? 'Auto · detenido' : (paused ? 'Auto · en pausa' : 'Auto · ' + remain + ' s');
        }
        if (autoToggle) {
            autoToggle.textContent = userPaused ? 'Reanudar' : 'Pausar';
            autoToggle.setAttribute('aria-label', (userPaused ? 'Reanudar' : 'Pausar') + ' actualización automática');
            autoToggle.disabled = dead;
        }
        if (pulse) pulse.textContent = 'Actualizado ' + ageText();
    }

    function marcarCuota() {
        document.querySelectorAll('[data-cuota-filtro]').forEach(function (btn) {
            var on = btn.getAttribute('data-cuota-filtro') === cuotaFiltro;
            btn.className = on ? filtroOn : filtroOff;
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function velocidad(bitsPorSegundo) {
        if (bitsPorSegundo >= 1e6) return (bitsPorSegundo / 1e6).toFixed(1).replace('.', ',') + ' Mb/s';
        return Math.round(bitsPorSegundo / 1e3) + ' kb/s';
    }

    // El router da bytes acumulados: la velocidad sale de comparar dos lecturas seguidas.
    function pintarTrafico() {
        var t = pulse ? Date.parse(pulse.getAttribute('data-consultado') || '') : NaN;
        var nuevas = {};
        document.querySelectorAll('[data-trafico]').forEach(function (celda) {
            var clave = celda.getAttribute('data-trafico') || '';
            var sube = parseFloat(celda.getAttribute('data-subida'));
            var baja = parseFloat(celda.getAttribute('data-bajada'));
            if (clave === '' || isNaN(sube) || isNaN(baja) || isNaN(t)) {
                celda.textContent = '—';
                return;
            }
            var antes = muestras[clave];
            var texto = 'midiendo…';
            if (antes && t === antes.t) {
                texto = antes.texto;
            } else if (antes && t > antes.t && sube >= antes.sube && baja >= antes.baja) {
                var seg = (t - antes.t) / 1000;
                texto = '↓ ' + velocidad((baja - antes.baja) * 8 / seg) + ' · ↑ ' + velocidad((sube - antes.sube) * 8 / seg);
            }
            celda.textContent = texto;
            nuevas[clave] = { t: t, sube: sube, baja: baja, texto: texto };
        });
        muestras = nuevas;
    }

    function cerrarMenu(volverAlBoton) {
        if (!menuAbierto) return;
        menuAbierto.menu.hidden = true;
        menuAbierto.btn.setAttribute('aria-expanded', 'false');
        if (volverAlBoton && document.body.contains(menuAbierto.btn)) menuAbierto.btn.focus();
        menuAbierto = null;
        paintPulse();
    }

    function abrirMenu(boton) {
        cerrarMenu(false);
        var menu = boton.parentNode ? boton.parentNode.querySelector('.hsd-menu') : null;
        if (!menu) return;
        menu.hidden = false;
        var r = boton.getBoundingClientRect();
        var ancho = menu.offsetWidth;
        var alto = menu.offsetHeight;
        var left = Math.max(8, Math.min(r.right - ancho, window.innerWidth - ancho - 8));
        var top = r.bottom + 4;
        if (top + alto > window.innerHeight - 8) top = Math.max(8, r.top - alto - 4);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        boton.setAttribute('aria-expanded', 'true');
        menuAbierto = { btn: boton, menu: menu };
        var primero = menu.querySelector('.hsd-menu-item');
        if (primero) primero.focus();
        paintPulse();
    }

    function marcarTarjetas() {
        document.querySelectorAll('[data-tarjeta]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.getAttribute('data-tarjeta') === tarjeta ? 'true' : 'false');
        });
    }

    function sobre80(fila) {
        var pct = fila.getAttribute('data-pct');
        return pct !== null && pct !== '' && parseFloat(pct) >= 80;
    }

    function filtrarFilas() {
        var term = find ? String(find.value || '').trim().toLowerCase() : '';
        var secVivos = document.getElementById('hotspot-dash-vivos');
        var secOffline = document.getElementById('hotspot-dash-offline');
        if (secVivos) secVivos.hidden = tarjeta === 'sin-sesion';
        if (secOffline) secOffline.hidden = tarjeta === 'en-linea' || tarjeta === 'trial';

        var visiblesVivos = 0;
        var totalVivos = 0;
        document.querySelectorAll('#hotspot-dash-vivos .hotspot-dash-fila').forEach(function (fila) {
            totalVivos += 1;
            var hay = String(fila.getAttribute('data-buscar') || '');
            var pasaTexto = term === '' || hay.indexOf(term) !== -1;
            var pasaTarjeta = true;
            if (tarjeta === 'trial') pasaTarjeta = fila.getAttribute('data-grupo') === 'trial';
            else if (tarjeta === 'cuota80') pasaTarjeta = sobre80(fila);
            var ver = pasaTexto && pasaTarjeta;
            fila.hidden = !ver;
            if (ver) visiblesVivos += 1;
        });
        var vacioVivos = document.getElementById('hotspot-dash-vivos-vacio');
        if (vacioVivos) vacioVivos.style.display = (totalVivos > 0 && visiblesVivos === 0) ? 'block' : 'none';

        var visibles = 0;
        var total = 0;
        document.querySelectorAll('#hotspot-dash-offline .hotspot-dash-fila').forEach(function (fila) {
            total += 1;
            var hay = String(fila.getAttribute('data-buscar') || '');
            var cuota = String(fila.getAttribute('data-cuota') || '');
            var pasaTexto = term === '' || hay.indexOf(term) !== -1;
            var pasaCuota = cuotaFiltro === 'todos' || cuota === cuotaFiltro;
            var pasaTarjeta = tarjeta !== 'cuota80' || sobre80(fila);
            var ver = pasaTexto && pasaCuota && pasaTarjeta;
            fila.hidden = !ver;
            if (ver) visibles += 1;
        });
        document.querySelectorAll('#hotspot-dash-offline [data-grupo-offline]').forEach(function (grupo) {
            grupo.hidden = !grupo.querySelector('.hotspot-dash-fila:not([hidden])');
        });
        var vacio = document.getElementById('hotspot-dash-offline-vacio');
        if (vacio) vacio.style.display = (total > 0 && visibles === 0) ? 'block' : 'none';
        marcarCuota();
        marcarTarjetas();
    }

    function showSessionDead() {
        dead = true;
        leaving = false;
        if (sessionHost && !sessionHost.dataset.shown) {
            sessionHost.dataset.shown = '1';
            var box = document.createElement('div');
            box.className = 'mb-4 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3';
            box.setAttribute('role', 'alert');
            var p = document.createElement('p');
            p.className = 'text-sm font-medium text-red-800 dark:text-red-200';
            p.textContent = sessionHost.getAttribute('data-copy') || '';
            var a = document.createElement('a');
            a.href = sessionHost.getAttribute('data-entrar') || '';
            a.className = btnPrimaryClass + ' mt-3';
            a.textContent = 'Entrar';
            box.appendChild(p);
            box.appendChild(a);
            sessionHost.appendChild(box);
        }
        paintPulse();
    }

    function scrollWrap(id) {
        return document.getElementById(id);
    }

    function applyWatch(doc) {
        var newKpis = doc.getElementById('hotspot-dash-kpis');
        var newVivos = doc.getElementById('hotspot-dash-vivos');
        var newOff = doc.getElementById('hotspot-dash-offline');
        if (!newVivos || !newOff) return false;
        var yV = scrollWrap('hotspot-dash-vivos-scroll');
        var yO = scrollWrap('hotspot-dash-offline-scroll');
        var topV = yV ? yV.scrollTop : 0;
        var topO = yO ? yO.scrollTop : 0;
        var curK = document.getElementById('hotspot-dash-kpis');
        var curV = document.getElementById('hotspot-dash-vivos');
        var curO = document.getElementById('hotspot-dash-offline');
        if (curK && newKpis) curK.replaceWith(newKpis);
        if (curV) curV.replaceWith(newVivos);
        if (curO) curO.replaceWith(newOff);
        vivos = document.getElementById('hotspot-dash-vivos');
        pulse = document.getElementById('hotspot-dash-pulse');
        var nv = scrollWrap('hotspot-dash-vivos-scroll');
        var no = scrollWrap('hotspot-dash-offline-scroll');
        if (nv) nv.scrollTop = topV;
        if (no) no.scrollTop = topO;
        var nuevoActual = doc.getElementById('hotspot-dash-router-actual');
        var actual = document.getElementById('hotspot-dash-router-actual');
        if (nuevoActual && actual) actual.replaceWith(nuevoActual);
        menuAbierto = null;
        remain = REFRESH_S;
        pintarTrafico();
        filtrarFilas();
        paintPulse();
        return true;
    }

    function setBusy(on) {
        busy = on;
        if (refreshBtn) refreshBtn.classList.toggle('is-busy', on);
    }

    function tryRefresh(force) {
        if (leaving || dead || busy) return;
        if (!force && isPaused()) return;
        setBusy(true);
        fetch(pingUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        }).then(function (r) {
            var json = (r.headers.get('content-type') || '').indexOf('json') !== -1;
            if (!r.ok || r.redirected || !json) {
                showSessionDead();
                return null;
            }
            return fetch(window.location.pathname + window.location.search, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
        }).then(function (r) {
            if (!r) return null;
            if (!r.ok || r.redirected) {
                showSessionDead();
                return null;
            }
            return r.text();
        }).then(function (html) {
            if (!html) return;
            var doc = new DOMParser().parseFromString(html, 'text/html');
            if (!applyWatch(doc)) {
                showSessionDead();
            }
        }).catch(function () {
            showSessionDead();
        }).then(function () {
            setBusy(false);
        });
    }

    function opciones() {
        return list ? Array.prototype.slice.call(list.querySelectorAll('.hsd-opt')) : [];
    }

    function opcionesVisibles() {
        return opciones().filter(function (opt) { return !opt.hidden; });
    }

    function setActiva(opt) {
        opciones().forEach(function (o) { o.classList.remove('is-active'); });
        activa = opt || null;
        if (!filter) return;
        if (!activa) {
            filter.removeAttribute('aria-activedescendant');
            return;
        }
        activa.classList.add('is-active');
        filter.setAttribute('aria-activedescendant', activa.id);
        activa.scrollIntoView({ block: 'nearest' });
    }

    function filterRouters() {
        if (!list) return;
        var term = filter ? String(filter.value || '').trim().toLowerCase() : '';
        var hayAlguno = false;
        list.querySelectorAll('.hsd-group').forEach(function (g) {
            var any = false;
            g.querySelectorAll('.hsd-opt').forEach(function (opt) {
                var show = term === '' || String(opt.getAttribute('data-buscar') || '').indexOf(term) !== -1;
                opt.hidden = !show;
                if (show) any = true;
            });
            g.hidden = !any;
            if (any) hayAlguno = true;
        });
        var vacio = document.getElementById('hotspot-dash-router-vacio');
        if (vacio) vacio.hidden = hayAlguno;
        if (!activa || activa.hidden) setActiva(opcionesVisibles()[0] || null);
    }

    function openPop() {
        if (!pop || !btn) return;
        pop.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        if (filter) filter.value = '';
        activa = null;
        filterRouters();
        var actual = list ? list.querySelector('.hsd-opt[aria-selected="true"]') : null;
        if (actual) setActiva(actual);
        if (filter) filter.focus();
        paintPulse();
    }

    function closePop(volverAlBoton) {
        if (!pop || !btn || pop.hidden) return;
        pop.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        if (volverAlBoton) btn.focus();
        paintPulse();
    }

    function irARouter(opt) {
        if (!opt) return;
        if (String(opt.getAttribute('data-router-id') || '') === loaded) {
            closePop(true);
            return;
        }
        closePop(false);
        showSkel();
        window.location.href = opt.href;
    }

    if (btn) {
        btn.addEventListener('click', function () {
            if (popOpen()) closePop(false);
            else openPop();
        });
        btn.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            e.preventDefault();
            openPop();
        });
    }
    if (filter) {
        filter.addEventListener('input', filterRouters);
        filter.addEventListener('keydown', function (e) {
            var visibles = opcionesVisibles();
            var i = visibles.indexOf(activa);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActiva(visibles[Math.min(visibles.length - 1, i + 1)] || visibles[0] || null);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActiva(visibles[Math.max(0, i - 1)] || null);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                irARouter(activa);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closePop(true);
            } else if (e.key === 'Tab') {
                closePop(false);
            }
        });
    }
    if (list) {
        list.addEventListener('click', function (e) {
            var opt = e.target.closest('.hsd-opt');
            if (!opt) return;
            e.preventDefault();
            irARouter(opt);
        });
    }
    document.addEventListener('click', function (e) {
        if (box && popOpen() && !box.contains(e.target)) closePop(false);
    });
    if (find) {
        find.addEventListener('input', filtrarFilas);
        find.addEventListener('focus', paintPulse);
        find.addEventListener('blur', paintPulse);
    }
    document.addEventListener('click', function (e) {
        var botonMenu = e.target.closest('[data-menu-btn]');
        if (botonMenu) {
            if (menuAbierto && menuAbierto.btn === botonMenu) cerrarMenu(false);
            else abrirMenu(botonMenu);
            return;
        }
        if (menuAbierto && !menuAbierto.menu.contains(e.target)) cerrarMenu(false);
    });
    document.addEventListener('keydown', function (e) {
        if (!menuAbierto) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            cerrarMenu(true);
            return;
        }
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        var items = Array.prototype.slice.call(menuAbierto.menu.querySelectorAll('.hsd-menu-item'));
        if (!items.length) return;
        e.preventDefault();
        var i = items.indexOf(document.activeElement);
        var sig = e.key === 'ArrowDown' ? (i + 1) % items.length : (i <= 0 ? items.length - 1 : i - 1);
        items[sig].focus();
    });
    window.addEventListener('scroll', function () { cerrarMenu(false); }, true);
    window.addEventListener('resize', function () { cerrarMenu(false); });
    document.addEventListener('click', function (e) {
        var btnTarjeta = e.target.closest('[data-tarjeta]');
        if (!btnTarjeta) return;
        var valor = btnTarjeta.getAttribute('data-tarjeta') || '';
        tarjeta = tarjeta === valor ? '' : valor;
        filtrarFilas();
    });
    if (autoToggle) {
        autoToggle.addEventListener('click', function () {
            userPaused = !userPaused;
            try { window.sessionStorage.setItem(PAUSA_KEY, userPaused ? '1' : '0'); } catch (err) {}
            if (!userPaused) remain = REFRESH_S;
            paintPulse();
        });
    }
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            tryRefresh(true);
        });
    }
    if (watch) {
        watch.addEventListener('click', function (e) {
            var copiar = e.target.closest('[data-copiar]');
            if (copiar) {
                var mac = copiar.getAttribute('data-copiar') || '';
                var icono = copiar.innerHTML;
                var titulo = copiar.getAttribute('aria-label') || 'Copiar MAC';
                var listo = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                var avisar = function (ok) {
                    if (copiar.hasAttribute('data-copiar-texto')) {
                        copiar.textContent = ok ? 'MAC copiada' : 'No se pudo copiar';
                        setTimeout(function () { copiar.innerHTML = icono; }, 1200);
                        return;
                    }
                    copiar.innerHTML = ok ? listo : icono;
                    copiar.setAttribute('aria-label', ok ? 'MAC copiada' : 'No se pudo copiar');
                    setTimeout(function () {
                        copiar.innerHTML = icono;
                        copiar.setAttribute('aria-label', titulo);
                    }, 1200);
                };
                var fallback = function () {
                    var input = document.createElement('textarea');
                    input.value = mac;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.left = '-9999px';
                    document.body.appendChild(input);
                    input.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
                    document.body.removeChild(input);
                    avisar(ok);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(mac).then(function () { avisar(true); }).catch(fallback);
                } else {
                    fallback();
                }
                return;
            }
            var cuota = e.target.closest('[data-cuota-filtro]');
            if (cuota) {
                cuotaFiltro = cuota.getAttribute('data-cuota-filtro') || 'todos';
                filtrarFilas();
                return;
            }
            var a = e.target.closest('[data-dash-refresh]');
            if (!a) return;
            showSkel();
        });
    }

    pintarTrafico();
    paintPulse();
    setInterval(paintPulse, 1000);
    setInterval(function () {
        if (isPaused()) {
            paintPulse();
            return;
        }
        remain -= 1;
        if (remain <= 0) {
            remain = REFRESH_S;
            tryRefresh();
            return;
        }
        paintPulse();
    }, 1000);
})();
</script>
@endpush
@endif
