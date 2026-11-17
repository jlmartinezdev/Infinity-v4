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
        'usuarios' => ['Usuarios hotspot', 'Cuentas de cada cliente: usuario por documento, PIN y hasta tres por cliente.'],
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
    .hsd-aviso {
        padding: 0.625rem 0.875rem;
        border: 1px solid;
        border-radius: 0.5rem;
        font-size: 0.875rem;
    }
    .hsd-aviso[hidden] { display: none; }
    .hsd-sync {
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .hsd-sync.is-ok { background: #dcfce7; color: #166534; }
    .hsd-sync.is-off { background: #f3f4f6; color: #4b5563; }
    .hsd-sync.is-error { background: #fee2e2; color: #991b1b; }
    html.dark .hsd-sync.is-ok { background: rgba(20, 83, 45, 0.45); color: #bbf7d0; }
    html.dark .hsd-sync.is-off { background: #374151; color: #d1d5db; }
    html.dark .hsd-sync.is-error { background: rgba(127, 29, 29, 0.45); color: #fecaca; }
    .hsd-aviso.is-ok {
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }
    .hsd-aviso.is-error {
        border-color: #fecaca;
        background: #fef2f2;
        color: #991b1b;
    }
    html.dark .hsd-aviso.is-ok {
        border-color: rgba(34, 197, 94, 0.35);
        background: rgba(20, 83, 45, 0.3);
        color: #bbf7d0;
    }
    html.dark .hsd-aviso.is-error {
        border-color: rgba(239, 68, 68, 0.35);
        background: rgba(127, 29, 29, 0.3);
        color: #fecaca;
    }
    #hotspot-dash-bloqueadas:empty { display: none; }
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
    @media (min-width: 640px) {
        #hotspot-dash-page .hsd-search-usuarios { flex-basis: 24rem; }
    }
    .hsd-sugerencias {
        position: absolute;
        z-index: 30;
        top: calc(100% + 0.25rem);
        left: 0;
        right: 0;
        max-height: 15rem;
        overflow-y: auto;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
    }
    .hsd-sugerencias[hidden] { display: none; }
    html.dark .hsd-sugerencias {
        background: #1f2937;
        border-color: #4b5563;
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
                        <span id="hotspot-dash-router-dot" class="hsd-dot {{ $estadoClase }}" aria-hidden="true"></span>
                        <span class="flex flex-col min-w-0">
                            <span class="text-sm font-semibold truncate">{{ $selectedRouter->nombre }}</span>
                            <span class="hsd-opt-meta truncate">{{ $selectedRouter->ip }}@if($nodoSeleccionado !== '') · {{ $nodoSeleccionado }}@endif · <span id="hotspot-dash-router-estado">{{ $estadoTexto }}</span></span>
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
        @elseif($vista === 'usuarios')
            @php
                $buscarUsuarios = trim((string) request('buscar', ''));
            @endphp
            <form id="hotspot-find-form" method="GET" action="{{ route('hotspot.dashboard') }}" role="search" class="flex items-center gap-2 pb-2 w-full sm:w-auto">
                <input type="hidden" name="vista" value="usuarios">
                @if($selectedRouter)
                    <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                @endif
                <div id="hotspot-combobox" class="hsd-search hsd-search-usuarios relative">
                    {!! $lupa !!}
                    <input type="text" name="buscar" id="hotspot-buscar" value="{{ $buscarUsuarios }}"
                        placeholder="Buscar nombre, cédula o usuario"
                        autocomplete="off"
                        role="combobox"
                        aria-label="Buscar usuario o cliente"
                        aria-expanded="false"
                        aria-autocomplete="list"
                        aria-controls="hotspot-sugerencias"
                        aria-haspopup="listbox"
                        aria-describedby="hotspot-find-help">
                    @if($buscarUsuarios !== '')
                        <a href="{{ $menuHotspot('usuarios') }}" class="hsd-auto-btn" style="text-decoration: none">Limpiar</a>
                    @endif
                    <div id="hotspot-sugerencias" role="listbox" hidden class="hsd-sugerencias"></div>
                </div>
                <p id="hotspot-buscar-status" class="sr-only" aria-live="polite"></p>
                <p id="hotspot-find-help" class="sr-only">Enter filtra la lista. Las flechas eligen un cliente. Después tocá una cuenta para confirmar y sincronizar.</p>
            </form>
        @elseif($vista === 'perfiles')
            @php
                $buscarPerfiles = trim((string) request('buscar', ''));
            @endphp
            <form method="GET" action="{{ route('hotspot.dashboard') }}" role="search" class="flex items-center gap-2 pb-2 w-full sm:w-auto">
                <input type="hidden" name="vista" value="perfiles">
                @if($selectedRouter)
                    <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                @endif
                <label class="hsd-search">
                    {!! $lupa !!}
                    <input type="search" name="buscar" value="{{ $buscarPerfiles }}" autocomplete="off"
                        aria-label="Buscar perfil por nombre o velocidad"
                        placeholder="Buscar perfil o velocidad">
                    @if($buscarPerfiles !== '')
                        <a href="{{ $menuHotspot('perfiles') }}" class="hsd-auto-btn" style="text-decoration: none">Limpiar</a>
                    @endif
                </label>
            </form>
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

        <div id="hotspot-dash-aviso" class="hsd-aviso" role="status" aria-live="polite" hidden></div>

        @include('hotspot.paneles.sesiones')

        <div id="hotspot-dash-bloqueadas">
            @include('hotspot.paneles._macs-bloqueadas')
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
        var estadoNuevo = doc.getElementById('hotspot-dash-router-estado-nuevo');
        var dot = document.getElementById('hotspot-dash-router-dot');
        var estadoTxt = document.getElementById('hotspot-dash-router-estado');
        if (estadoNuevo) {
            if (dot) dot.className = 'hsd-dot ' + (estadoNuevo.getAttribute('data-clase') || '');
            if (estadoTxt) estadoTxt.textContent = estadoNuevo.getAttribute('data-texto') || '';
        }
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

    var refrescoPendiente = false;
    function tryRefresh(force) {
        if (leaving || dead) return;
        if (busy) {
            if (force) refrescoPendiente = true;
            return;
        }
        if (!force && isPaused()) return;
        setBusy(true);
        // Una sola consulta: el servidor devuelve solo tarjetas y tablas (sin menú ni MACs bloqueadas).
        var url = new URL(window.location.href);
        url.searchParams.set('parcial', '1');
        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        }).then(function (r) {
            if (r.redirected || r.status === 401 || r.status === 403 || r.status === 419) {
                showSessionDead();
                return null;
            }
            if (!r.ok) {
                avisar('error', 'No se pudo actualizar. Reintento en ' + REFRESH_S + ' s.');
                return null;
            }
            return r.text();
        }).then(function (html) {
            if (!html) return;
            var doc = new DOMParser().parseFromString(html, 'text/html');
            if (!applyWatch(doc)) {
                avisar('error', 'No se pudo actualizar. Reintento en ' + REFRESH_S + ' s.');
            }
        }).catch(function () {
            avisar('error', 'Sin conexión con el servidor. Reintento en ' + REFRESH_S + ' s.');
        }).then(function () {
            remain = REFRESH_S;
            setBusy(false);
            if (refrescoPendiente) {
                refrescoPendiente = false;
                tryRefresh(true);
            }
        });
    }

    var avisoTimer = null;
    function avisar(tipo, texto) {
        var aviso = document.getElementById('hotspot-dash-aviso');
        if (!aviso) return;
        if (avisoTimer) clearTimeout(avisoTimer);
        aviso.className = 'hsd-aviso ' + (tipo === 'success' ? 'is-ok' : 'is-error');
        aviso.textContent = texto;
        aviso.hidden = false;
        avisoTimer = setTimeout(function () { aviso.hidden = true; }, tipo === 'success' ? 6000 : 10000);
    }

    // Acciones (desconectar, bloquear, sincronizar, quitar, desbloquear) sin recargar la página.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-accion], form[data-confirmar]')) return;
        if (e.defaultPrevented) return;
        var pregunta = form.getAttribute('data-confirmar');
        if (pregunta && !window.confirm(pregunta)) {
            e.preventDefault();
            return;
        }
        if (!form.hasAttribute('data-accion')) return;
        if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') return;
        e.preventDefault();
        cerrarMenu(false);
        var accion = form.getAttribute('data-accion') || '';
        var boton = form.querySelector('button[type="submit"]');
        var textoBoton = boton ? boton.textContent : '';
        if (boton) {
            boton.disabled = true;
            boton.textContent = '…';
        }
        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        }).then(function (r) {
            if (r.status === 401 || r.status === 403) {
                showSessionDead();
                return null;
            }
            if (r.status === 419) {
                avisar('error', 'La página quedó vieja. Recargala y probá de nuevo.');
                return null;
            }
            return r.json().catch(function () { return { ok: false }; });
        }).then(function (data) {
            if (!data) return;
            var ok = data.ok === true || data.success === true;
            var texto = data.mensaje || data.error || (ok ? 'Listo.' : 'No se pudo completar la acción.');
            avisar(ok ? 'success' : 'error', texto);
            if (typeof data.bloqueadas_html === 'string') {
                var lista = document.getElementById('hotspot-dash-bloqueadas');
                if (lista) lista.innerHTML = data.bloqueadas_html;
            }
            if (accion !== 'desbloquear' || ok) tryRefresh(true);
        }).catch(function () {
            avisar('error', 'Sin conexión con el servidor. La acción no se completó.');
        }).then(function () {
            if (boton && document.body.contains(boton)) {
                boton.disabled = false;
                boton.textContent = textoBoton;
            }
        });
    });

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
