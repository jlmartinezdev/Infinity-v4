@extends('layouts.app')

@section('title', 'Cajas NAP')

@push('styles')
<style>
    .nap-table tbody tr {
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }
</style>
@endpush

@section('content')
@php
    $busqueda = $busqueda ?? '';
    $nodoId = request('nodo_id');
    $salidaPonId = request('salida_pon_id');
    $tipo = request('tipo');
    $estado = request('estado');
    $splitterSegundo = request('splitter_segundo_nivel');
    $filtroGps = request('filtro_gps');
    $filtroOcupacion = request('filtro_ocupacion');

    $filtrosActivos = [];
    if (request()->filled('q') || request()->filled('buscar')) {
        $qVal = request('q', request('buscar'));
        $filtrosActivos[] = ['clave' => 'q', 'label' => 'Búsqueda: "'.$qVal.'"', 'url' => route('sistema.cajas-nap.index', request()->except(['q', 'buscar', 'page']))];
    }
    if (request()->filled('nodo_id')) {
        $nodoSel = $nodos->firstWhere('nodo_id', (int) $nodoId);
        $filtrosActivos[] = ['clave' => 'nodo_id', 'label' => 'Nodo: '.($nodoSel?->descripcion ?? '#'.$nodoId), 'url' => route('sistema.cajas-nap.index', request()->except(['nodo_id', 'page']))];
    }
    if (request()->filled('salida_pon_id')) {
        $salidaSel = $salidas->firstWhere('salida_pon_id', (int) $salidaPonId);
        $filtrosActivos[] = ['clave' => 'salida_pon_id', 'label' => 'Salida PON: '.($salidaSel?->codigo ?? '#'.$salidaPonId), 'url' => route('sistema.cajas-nap.index', request()->except(['salida_pon_id', 'page']))];
    }

    $salidasPorOlt = $salidas
        ->groupBy(function ($sp) {
            if (! $sp->olt_id) {
                return 'Sin OLT';
            }

            return (string) ($sp->olt?->codigo ?: $sp->olt?->ip ?: 'OLT #'.$sp->olt_id);
        })
        ->sortKeysUsing(function ($a, $b) {
            if ($a === 'Sin OLT') {
                return 1;
            }
            if ($b === 'Sin OLT') {
                return -1;
            }

            return strnatcasecmp($a, $b);
        });
    if (request()->filled('tipo')) {
        $filtrosActivos[] = ['clave' => 'tipo', 'label' => 'Tipo: '.ucfirst($tipo), 'url' => route('sistema.cajas-nap.index', request()->except(['tipo', 'page']))];
    }
    if (request()->filled('estado')) {
        $filtrosActivos[] = ['clave' => 'estado', 'label' => 'Estado: '.ucfirst($estado), 'url' => route('sistema.cajas-nap.index', request()->except(['estado', 'page']))];
    }
    if (request()->filled('splitter_segundo_nivel')) {
        $filtrosActivos[] = ['clave' => 'splitter_segundo_nivel', 'label' => 'Capacidad: 1×'.$splitterSegundo, 'url' => route('sistema.cajas-nap.index', request()->except(['splitter_segundo_nivel', 'page']))];
    }
    if ($filtroGps === 'con_gps') {
        $filtrosActivos[] = ['clave' => 'filtro_gps', 'label' => 'Con GPS en mapa', 'url' => route('sistema.cajas-nap.index', request()->except(['filtro_gps', 'page']))];
    } elseif ($filtroGps === 'sin_gps') {
        $filtrosActivos[] = ['clave' => 'filtro_gps', 'label' => 'Sin GPS', 'url' => route('sistema.cajas-nap.index', request()->except(['filtro_gps', 'page']))];
    }
    if ($filtroOcupacion === 'con_libres') {
        $filtrosActivos[] = ['clave' => 'filtro_ocupacion', 'label' => 'Con puertos libres', 'url' => route('sistema.cajas-nap.index', request()->except(['filtro_ocupacion', 'page']))];
    } elseif ($filtroOcupacion === 'saturadas') {
        $filtrosActivos[] = ['clave' => 'filtro_ocupacion', 'label' => 'Saturadas (100%)', 'url' => route('sistema.cajas-nap.index', request()->except(['filtro_ocupacion', 'page']))];
    } elseif ($filtroOcupacion === 'vacias') {
        $filtrosActivos[] = ['clave' => 'filtro_ocupacion', 'label' => 'Sin clientes (0%)', 'url' => route('sistema.cajas-nap.index', request()->except(['filtro_ocupacion', 'page']))];
    }

    $puedeCrear = auth()->user()?->tienePermiso('ftth-cajas-nap.crear') ?? true;
    $puedeEditar = auth()->user()?->tienePermiso('ftth-cajas-nap.editar') ?? true;
    $puedeEliminar = auth()->user()?->tienePermiso('ftth-cajas-nap.eliminar') ?? true;
@endphp

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    {{-- CABECERA PRINCIPAL CON ICONO SVG --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-cyan-100 dark:bg-cyan-950/70 text-cyan-600 dark:text-cyan-400 flex items-center justify-center shrink-0 shadow-sm border border-cyan-200 dark:border-cyan-800/60">
                {{-- SVG Caja NAP / Terminal Box --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">Cajas NAP</h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Cajas terminales ópticas FTTH, capacidad de splitters, ramales PON y distribución de abonados en campo.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <a href="{{ request()->fullUrl() }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors"
                title="Recargar listado">
                {{-- SVG Actualizar --}}
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span class="hidden sm:inline">Actualizar</span>
            </a>

            <a href="{{ route('sistema.cajas-nap.mapa') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold shadow-sm transition-colors"
                title="Ver infraestructura en mapa interactivo">
                {{-- SVG Mapa interactivo --}}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <span>Ver mapa</span>
            </a>

            @if($puedeCrear)
                <a href="{{ route('sistema.cajas-nap.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold shadow-sm transition-colors">
                    {{-- SVG Plus --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Nueva Caja NAP</span>
                </a>
            @endif
        </div>
    </div>

    {{-- DECK SUPERIOR DE 4 KPIS FTTH / NOC --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Cajas NAP --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Cajas NAP</span>
                <span class="w-8 h-8 rounded-xl bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                    {{-- SVG Box --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                    {{ $kpis['total'] }}
                </span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                    {{ $kpis['activas'] }} activas
                </span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                {{ $cajas->total() }} registradas en esta vista
            </div>
        </div>

        {{-- Card 2: Capacidad y Clientes Conectados --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ocupación FTTH</span>
                <span class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    {{-- SVG Users / Ports --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-purple-600 dark:text-purple-400">
                    {{ $kpis['puertos_ocupados'] }}
                </span>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                    / {{ $kpis['capacidad_total'] }} puertos
                </span>
            </div>
            <div class="mt-2">
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div class="h-1.5 rounded-full transition-all duration-300 {{ $kpis['porcentaje_ocupacion'] >= 90 ? 'bg-rose-500' : ($kpis['porcentaje_ocupacion'] >= 70 ? 'bg-amber-500' : 'bg-purple-600') }}"
                        style="width: {{ min(100, $kpis['porcentaje_ocupacion']) }}%"></div>
                </div>
                <div class="mt-1 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                    <span>{{ $kpis['porcentaje_ocupacion'] }}% saturación</span>
                    <span>{{ $kpis['puertos_libres'] }} libres</span>
                </div>
            </div>
        </div>

        {{-- Card 3: Georreferenciadas en Mapa --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cajas en Mapa</span>
                <span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    {{-- SVG Map Pin --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-blue-600 dark:text-blue-400">
                    {{ $kpis['con_gps'] }}
                </span>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                    / {{ $kpis['total'] }} con GPS
                </span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                {{ $kpis['total'] > 0 ? round(($kpis['con_gps'] / $kpis['total']) * 100) : 0 }}% listas con coordenadas geográficas
            </div>
        </div>

        {{-- Card 4: Potencia Óptica Promedio --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Potencia de Salida</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    {{-- SVG Signal dBm --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                    {{ $kpis['potencia_promedio'] !== null ? ($kpis['potencia_promedio'] > 0 ? '+'.$kpis['potencia_promedio'] : $kpis['potencia_promedio']) : '—' }}
                </span>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">dBm prom.</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                {{ $kpis['con_potencia'] }} cajas con medición cargada
            </div>
        </div>
    </div>

    {{-- CONTENEDOR PRINCIPAL: BUSCADOR + FILTROS + TABLA --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

        {{-- FORMULARIO DE FILTROS & BÚSQUEDA --}}
        <form method="GET" action="{{ route('sistema.cajas-nap.index') }}" id="form-filtros-nap" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/80">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                {{-- Input Buscador --}}
                <div class="flex items-center flex-1 min-w-0 h-11 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:ring-2 focus-within:ring-purple-500/20 focus-within:border-purple-500 transition-all">
                    <span class="pl-3.5 text-gray-400 dark:text-gray-500">
                        {{-- SVG Search --}}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                        </svg>
                    </span>
                    <input type="search" name="q" value="{{ $busqueda }}"
                        placeholder="Buscar por código de NAP, nodo, salida PON, OLT, dirección..."
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2.5 pr-3 py-2 text-sm focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @if($busqueda !== '')
                        <a href="{{ route('sistema.cajas-nap.index', request()->except(['q', 'buscar', 'page'])) }}" class="pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Limpiar búsqueda">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>

                {{-- Filtro Rápido por Nodo --}}
                <div class="sm:w-52 shrink-0">
                    <select name="nodo_id" onchange="document.getElementById('form-filtros-nap').submit()"
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="">Todos los Nodos</option>
                        @foreach ($nodos as $n)
                            <option value="{{ $n->nodo_id }}" @selected((string)$nodoId === (string)$n->nodo_id)>
                                {{ $n->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro Rápido por Salida PON --}}
                <div class="sm:w-56 shrink-0">
                    <select name="salida_pon_id" onchange="document.getElementById('form-filtros-nap').submit()"
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="">Todas las Salidas PON</option>
                        @foreach ($salidasPorOlt as $oltLabel => $grupo)
                            <optgroup label="{{ $oltLabel }}">
                                @foreach ($grupo as $sp)
                                    <option value="{{ $sp->salida_pon_id }}" @selected((string)$salidaPonId === (string)$sp->salida_pon_id)>
                                        {{ $sp->codigo }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                {{-- Selector Paginación --}}
                <div class="w-24 shrink-0">
                    <select name="per_page" onchange="document.getElementById('form-filtros-nap').submit()"
                        class="w-full h-11 px-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="10" @selected($perPage == 10)>10 / pág</option>
                        <option value="15" @selected($perPage == 15)>15 / pág</option>
                        <option value="25" @selected($perPage == 25)>25 / pág</option>
                        <option value="50" @selected($perPage == 50)>50 / pág</option>
                        <option value="100" @selected($perPage == 100)>100 / pág</option>
                    </select>
                </div>

                {{-- Botón Popover Filtros Avanzados --}}
                <div class="relative shrink-0" id="nap-filtros-wrap">
                    <button type="button" id="nap-filtros-btn"
                        class="h-11 px-4 inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-xs transition-colors">
                        {{-- SVG Filters --}}
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span>Filtros</span>
                        @php
                            $extraFiltersCount = (request()->filled('tipo') ? 1 : 0) +
                                (request()->filled('estado') ? 1 : 0) +
                                (request()->filled('splitter_segundo_nivel') ? 1 : 0) +
                                (request()->filled('filtro_gps') ? 1 : 0) +
                                (request()->filled('filtro_ocupacion') ? 1 : 0);
                        @endphp
                        @if($extraFiltersCount > 0)
                            <span class="w-5 h-5 rounded-full bg-purple-600 text-white text-[11px] font-bold flex items-center justify-center">
                                {{ $extraFiltersCount }}
                            </span>
                        @endif
                    </button>

                    {{-- Popover Dropdown --}}
                    @php
                        $selectFiltro = 'w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100';
                    @endphp
                    <div id="nap-filtros-menu"
                        class="hidden overflow-y-auto p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl">
                        <div class="flex items-center justify-between gap-4 pb-4 mb-5 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">Filtros Avanzados</span>
                            @if($extraFiltersCount > 0)
                                <a href="{{ route('sistema.cajas-nap.index', request()->only(['q', 'nodo_id', 'salida_pon_id', 'per_page'])) }}"
                                    class="text-sm text-purple-600 dark:text-purple-400 hover:underline font-semibold">
                                    Limpiar
                                </a>
                            @endif
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Tipo de Caja</label>
                                <select name="tipo" class="{{ $selectFiltro }}">
                                    <option value="">Todos los tipos</option>
                                    <option value="primaria" @selected($tipo === 'primaria')>Primaria</option>
                                    <option value="secundaria" @selected($tipo === 'secundaria')>Secundaria</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Estado Operativo</label>
                                <select name="estado" class="{{ $selectFiltro }}">
                                    <option value="">Todos los estados</option>
                                    <option value="activo" @selected($estado === 'activo')>Activo</option>
                                    <option value="mantenimiento" @selected($estado === 'mantenimiento')>Mantenimiento</option>
                                    <option value="inactivo" @selected($estado === 'inactivo')>Inactivo</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Capacidad Splitter (2º Nivel)</label>
                                <select name="splitter_segundo_nivel" class="{{ $selectFiltro }}">
                                    <option value="">Todas las capacidades</option>
                                    <option value="8" @selected((string)$splitterSegundo === '8')>1×8 (8 Puertos FTTH)</option>
                                    <option value="16" @selected((string)$splitterSegundo === '16')>1×16 (16 Puertos FTTH)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Ocupación / Disponibilidad</label>
                                <select name="filtro_ocupacion" class="{{ $selectFiltro }}">
                                    <option value="">Todas</option>
                                    <option value="con_libres" @selected($filtroOcupacion === 'con_libres')>Con puertos libres</option>
                                    <option value="saturadas" @selected($filtroOcupacion === 'saturadas')>Saturadas (100% ocupadas)</option>
                                    <option value="vacias" @selected($filtroOcupacion === 'vacias')>Sin clientes asignados (0%)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Coordenadas GPS</label>
                                <select name="filtro_gps" class="{{ $selectFiltro }}">
                                    <option value="">Todas</option>
                                    <option value="con_gps" @selected($filtroGps === 'con_gps')>Solo con GPS en mapa</option>
                                    <option value="sin_gps" @selected($filtroGps === 'sin_gps')>Solo sin coordenadas GPS</option>
                                </select>
                            </div>

                            <div class="pt-2 flex items-center gap-3">
                                <button type="submit" class="flex-1 h-11 px-4 rounded-xl bg-purple-600 text-white text-sm font-bold hover:bg-purple-700 shadow-sm transition-colors">
                                    Aplicar Filtros
                                </button>
                                <button type="button" id="nap-filtros-cerrar" class="h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CHIPS DE FILTROS ACTIVOS CON BOTÓN REMOVER --}}
            @if(count($filtrosActivos) > 0)
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Filtros aplicados:</span>
                    @foreach ($filtrosActivos as $fa)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-cyan-100 dark:bg-cyan-950/60 text-cyan-800 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/60">
                            <span>{{ $fa['label'] }}</span>
                            <a href="{{ $fa['url'] }}" class="text-cyan-600 dark:text-cyan-400 hover:text-cyan-900 dark:hover:text-cyan-200" title="Remover este filtro">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        </span>
                    @endforeach
                    <a href="{{ route('sistema.cajas-nap.index') }}" class="text-xs font-semibold text-cyan-600 dark:text-cyan-400 hover:underline ml-1">
                        Restablecer
                    </a>
                </div>
            @endif
        </form>

        {{-- TABLA MODERNIZADA DE CAJAS NAP --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 nap-table text-left">
                <thead class="bg-gray-50/90 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">Caja NAP (Código / Info)</th>
                        <th class="px-4 py-3.5">Nodo / Central</th>
                        <th class="px-4 py-3.5">Troncal PON / OLT</th>
                        <th class="px-4 py-3.5">Splitter & Nivel</th>
                        <th class="px-4 py-3.5">Puertos FTTH & Ocupación</th>
                        <th class="px-4 py-3.5">Potencia</th>
                        <th class="px-4 py-3.5">Ubicación GPS</th>
                        <th class="px-4 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/80 bg-white dark:bg-gray-800">
                    @forelse ($cajas as $c)
                        @php
                            $hasGps = ($c->lat !== null && $c->lon !== null);
                            $mapUrl = $hasGps ? 'https://www.google.com/maps?q='.$c->lat.','.$c->lon : null;
                            $trabajosAbiertos = $trabajosAbiertosPorCaja[$c->caja_nap_id] ?? 0;
                            $potencia = $c->potencia_salida !== null ? (float) $c->potencia_salida : null;
                            
                            $capFTTH = $c->capacidadFTTH() ?? ((int) $c->splitter_segundo_nivel ?: 0);
                            $ocupados = (int) ($c->puertos_ocupados_count ?? 0);
                            $libres = max(0, $capFTTH - $ocupados);
                            $pctOcup = $capFTTH > 0 ? min(100, round(($ocupados / $capFTTH) * 100)) : 0;

                            $barColor = 'bg-emerald-500';
                            $badgeColor = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60';
                            if ($pctOcup >= 100) {
                                $barColor = 'bg-rose-500';
                                $badgeColor = 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800/60';
                            } elseif ($pctOcup >= 75) {
                                $barColor = 'bg-amber-500';
                                $badgeColor = 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800/60';
                            }
                        @endphp
                        <tr class="hover:bg-cyan-50/40 dark:hover:bg-cyan-950/10">
                            {{-- Código / Identificador & Badges --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex items-start gap-2.5">
                                    <span class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/60 flex items-center justify-center shrink-0 mt-0.5">
                                        {{-- SVG NAP Box --}}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <a href="{{ route('sistema.cajas-nap.show', $c) }}"
                                                class="font-bold text-sm text-gray-900 dark:text-gray-100 hover:text-cyan-600 dark:hover:text-cyan-400 transition-colors">
                                                {{ $c->codigo }}
                                            </a>
                                            @if($c->estado === 'mantenimiento')
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                    Mantenimiento
                                                </span>
                                            @elseif($c->estado === 'inactivo')
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Inactivo
                                                </span>
                                            @endif
                                        </div>

                                        @if($c->descripcion || $c->direccion)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                                {{-- SVG Location pin icon --}}
                                                <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                </svg>
                                                <span>{{ Str::limit($c->descripcion ?: $c->direccion, 38) }}</span>
                                            </p>
                                        @endif

                                        {{-- Alerta de Trabajos de Red abiertos --}}
                                        @if($trabajosAbiertos > 0)
                                            <div class="mt-1.5">
                                                <a href="{{ route('trabajos-red.index', ['activo_tipo' => 'caja_nap', 'activo_id' => $c->caja_nap_id]) }}"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200 border border-amber-300 dark:border-amber-700/80 hover:bg-amber-200 transition-colors"
                                                    title="Ver órdenes de trabajo activas">
                                                    {{-- SVG Wrench --}}
                                                    <svg class="w-3 h-3 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                    <span>{{ $trabajosAbiertos }} orden(es) en red</span>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Nodo / Central --}}
                            <td class="px-4 py-3.5 align-middle">
                                @if($c->nodo)
                                    <div class="flex items-center gap-1.5">
                                        {{-- SVG Building/Tower --}}
                                        <svg class="w-4 h-4 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                        <span class="font-medium text-xs sm:text-sm text-gray-900 dark:text-gray-100">
                                            {{ $c->nodo->descripcion }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Sin nodo asignado</span>
                                @endif
                            </td>

                            {{-- Troncal PON / OLT --}}
                            <td class="px-4 py-3.5 align-middle">
                                @if($c->salidaPon)
                                    <div class="space-y-1">
                                        <a href="{{ route('sistema.salida-pons.index', ['q' => $c->salidaPon->codigo]) }}"
                                            class="inline-flex items-center gap-1 font-semibold text-xs text-purple-600 dark:text-purple-400 hover:underline">
                                            {{-- SVG Fiber Cable --}}
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            <span>{{ $c->salidaPon->codigo }}</span>
                                        </a>

                                        @if($c->salidaPon->olt)
                                            <div class="flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400">
                                                {{-- SVG Server OLT --}}
                                                <svg class="w-3 h-3 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                                </svg>
                                                <a href="{{ route('sistema.olts.show', $c->salidaPon->olt_id) }}" class="hover:underline text-gray-700 dark:text-gray-300">
                                                    {{ $c->salidaPon->olt->codigo ?? $c->salidaPon->olt->ip }}
                                                </a>
                                                @if($c->salidaPon->oltPuerto)
                                                    <span class="text-gray-400">· P{{ $c->salidaPon->oltPuerto->numero }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Sin troncal asignado</span>
                                @endif
                            </td>

                            {{-- Splitter & Nivel --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">
                                        1×{{ $c->splitter_segundo_nivel ?: '?' }}
                                    </span>
                                    @if($c->splitter_primer_nivel)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600" title="Splitter primer nivel (Tap)">
                                            Tap {{ $c->splitter_primer_nivel }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Puertos FTTH & Ocupación --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="w-36">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-bold text-gray-800 dark:text-gray-200">
                                            {{ $ocupados }}/{{ $capFTTH }}
                                        </span>
                                        <span class="text-[11px] font-semibold {{ $pctOcup >= 100 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $libres }} libres
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full transition-all duration-300 {{ $barColor }}"
                                            style="width: {{ $pctOcup }}%"></div>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between text-[10px]">
                                        <span class="font-medium text-gray-500 dark:text-gray-400">{{ $pctOcup }}% saturación</span>
                                        <a href="{{ route('sistema.cajas-nap.show', $c) }}" class="text-cyan-600 dark:text-cyan-400 hover:underline font-semibold" title="Abrir matriz de puertos">
                                            Ver matriz →
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Potencia Óptica --}}
                            <td class="px-4 py-3.5 align-middle">
                                @if($potencia !== null)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold {{ $potencia >= -22 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : ($potencia >= -26 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60') }}"
                                        title="Nivel de potencia óptica registrada">
                                        {{-- SVG Signal --}}
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <span>{{ $potencia > 0 ? '+'.number_format($potencia, 1) : number_format($potencia, 1) }} dBm</span>
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Ubicación GPS --}}
                            <td class="px-4 py-3.5 align-middle">
                                @if($hasGps)
                                    <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-cyan-50 dark:hover:bg-cyan-950/50 hover:text-cyan-700 dark:hover:text-cyan-300 border border-gray-200 dark:border-gray-600 transition-colors"
                                        title="Abrir ubicación en Google Maps">
                                        {{-- SVG Map Pin --}}
                                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span>{{ number_format($c->lat, 4) }}, {{ number_format($c->lon, 4) }}</span>
                                        {{-- SVG External Link --}}
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                                        <svg class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        <span>Sin GPS</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Menú emergente de acciones --}}
                            <td class="px-4 py-3.5 align-middle text-right w-14">
                                @php
                                    $puedeTrabajoNap = (bool) auth()->user()?->tienePermiso('trabajos-red.crear');
                                    $itemAccion = 'flex w-full items-center gap-2 px-3.5 py-2 text-sm font-medium text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
                                @endphp
                                <div class="nap-acciones-wrap relative inline-flex justify-end">
                                    <button type="button"
                                        class="nap-acciones-btn inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                        title="Acciones"
                                        aria-haspopup="menu"
                                        aria-expanded="false">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                    </button>
                                    <div class="nap-acciones-menu hidden py-1.5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl" role="menu">
                                        <a href="{{ route('sistema.cajas-nap.show', $c) }}"
                                            class="{{ $itemAccion }}" role="menuitem">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span>Ver detalle</span>
                                        </a>
                                        <a href="{{ route('sistema.cajas-nap.mapa', ['nodo_id' => $c->nodo_id]) }}"
                                            class="{{ $itemAccion }}" role="menuitem">
                                            {!! \App\Support\TrabajoRedIconos::svg('map', 'w-4 h-4 shrink-0') !!}
                                            <span>Ver en mapa</span>
                                        </a>
                                        @if($puedeTrabajoNap)
                                            <a href="{{ route('trabajos-red.create', ['activo_tipo' => 'caja_nap', 'activo_id' => $c->caja_nap_id, 'nodo_id' => $c->nodo_id]) }}"
                                                class="{{ $itemAccion }}" role="menuitem">
                                                {!! \App\Support\TrabajoRedIconos::svg('herramienta', 'w-4 h-4 shrink-0') !!}
                                                <span>Crear trabajo de red</span>
                                            </a>
                                        @endif
                                        @if($puedeEditar)
                                            <a href="{{ route('sistema.cajas-nap.edit', $c) }}"
                                                class="{{ $itemAccion }}" role="menuitem">
                                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                <span>Editar</span>
                                            </a>
                                        @endif
                                        @if($puedeEliminar)
                                            <form action="{{ route('sistema.cajas-nap.destroy', $c) }}" method="POST"
                                                onsubmit="return confirm('¿Está seguro de eliminar la caja {{ $c->codigo }}? Si posee puertos con clientes activos, no podrá ser eliminada.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="{{ $itemAccion }} text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40" role="menuitem">
                                                    {!! \App\Support\TrabajoRedIconos::svg('trash', 'w-4 h-4 shrink-0') !!}
                                                    <span>Eliminar</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                    </div>
                                    <p class="font-bold text-gray-700 dark:text-gray-300">No se encontraron Cajas NAP</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pruebe ajustando o limpiando los criterios de búsqueda y filtros seleccionados.</p>
                                    @if(count($filtrosActivos) > 0)
                                        <a href="{{ route('sistema.cajas-nap.index') }}" class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-cyan-600 text-white hover:bg-cyan-700 transition-colors">
                                            Limpiar todos los filtros
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN --}}
        @if ($cajas->hasPages())
            <div class="px-4 py-3.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/80">
                {{ $cajas->links() }}
            </div>
        @endif
    </div>

    {{-- ENLACES DE ACCESO DIRECTO A MÓDULOS DE INFRAESTRUCTURA --}}
    <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/60 flex flex-wrap items-center justify-between gap-4">
        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Navegación FTTH & Red
        </span>
        <div class="flex flex-wrap items-center gap-4 text-xs font-medium">
            <a href="{{ route('sistema.salida-pons.index') }}" class="inline-flex items-center gap-1.5 text-purple-600 dark:text-purple-400 hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Salidas PON</span>
                <span class="text-gray-400">→</span>
            </a>
            <a href="{{ route('sistema.cajas-nap.mapa') }}" class="inline-flex items-center gap-1.5 text-cyan-600 dark:text-cyan-400 hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                <span>Mapa de Cobertura</span>
                <span class="text-gray-400">→</span>
            </a>
            <a href="{{ route('sistema.lineas-cable.index') }}" class="inline-flex items-center gap-1.5 text-blue-600 dark:text-blue-400 hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span>Líneas de Cable</span>
                <span class="text-gray-400">→</span>
            </a>
            <a href="{{ route('trabajos-red.index', ['activo_tipo' => 'caja_nap']) }}" class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400 hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Trabajos en Red</span>
                <span class="text-gray-400">→</span>
            </a>
        </div>
    </div>
</div>

<script>
(function () {
    var btnFiltros = document.getElementById('nap-filtros-btn');
    var menuFiltros = document.getElementById('nap-filtros-menu');
    var wrapFiltros = document.getElementById('nap-filtros-wrap');
    var btnCerrarFiltros = document.getElementById('nap-filtros-cerrar');
    if (btnFiltros && menuFiltros) {
        function posicionarMenuFiltros() {
            if (menuFiltros.classList.contains('hidden')) return;
            var margin = 12;
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var btn = btnFiltros.getBoundingClientRect();
            var maxW = Math.min(380, Math.max(280, vw - margin * 2));
            var left = btn.right - maxW;
            if (left < margin) left = margin;
            if (left + maxW > vw - margin) left = Math.max(margin, vw - margin - maxW);
            var top = btn.bottom + 8;
            var maxH = Math.min(vh * 0.78, 720, vh - top - margin);
            if (maxH < 220) {
                maxH = Math.min(vh * 0.78, 720, btn.top - margin);
                top = Math.max(margin, btn.top - 8 - maxH);
            }
            menuFiltros.style.position = 'fixed';
            menuFiltros.style.zIndex = '60';
            menuFiltros.style.width = maxW + 'px';
            menuFiltros.style.left = left + 'px';
            menuFiltros.style.right = 'auto';
            menuFiltros.style.top = top + 'px';
            menuFiltros.style.maxHeight = Math.max(180, maxH) + 'px';
        }

        btnFiltros.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            menuFiltros.classList.toggle('hidden');
            posicionarMenuFiltros();
        });
        if (btnCerrarFiltros) {
            btnCerrarFiltros.addEventListener('click', function (e) {
                e.preventDefault();
                menuFiltros.classList.add('hidden');
            });
        }
        document.addEventListener('click', function (e) {
            if (wrapFiltros && !wrapFiltros.contains(e.target) && !menuFiltros.contains(e.target)) {
                menuFiltros.classList.add('hidden');
            }
        });
        window.addEventListener('resize', posicionarMenuFiltros);
        window.addEventListener('scroll', posicionarMenuFiltros, true);
    }

    var ignoreScrollNap = 0;

    function cerrarMenusNap(excepto) {
        document.querySelectorAll('.nap-acciones-menu').forEach(function (m) {
            if (m !== excepto) m.classList.add('hidden');
        });
        document.querySelectorAll('.nap-acciones-btn').forEach(function (b) {
            if (!excepto || b.parentElement.querySelector('.nap-acciones-menu') !== excepto) {
                b.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function posicionarMenuNap(btn, menu) {
        var margin = 8;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var r = btn.getBoundingClientRect();
        ignoreScrollNap = Date.now() + 300;
        menu.style.position = 'fixed';
        menu.style.zIndex = '70';
        menu.style.minWidth = '13.5rem';
        menu.classList.remove('hidden');
        var mw = menu.offsetWidth;
        var mh = menu.offsetHeight;
        var left = r.right - mw;
        if (left < margin) left = margin;
        if (left + mw > vw - margin) left = Math.max(margin, vw - margin - mw);
        var top = r.bottom + 6;
        if (top + mh > vh - margin) {
            top = Math.max(margin, r.top - 6 - mh);
        }
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.style.right = 'auto';
    }

    document.querySelectorAll('.nap-acciones-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var menu = btn.parentElement.querySelector('.nap-acciones-menu');
            if (!menu) return;
            var abierto = !menu.classList.contains('hidden');
            cerrarMenusNap();
            if (!abierto) {
                posicionarMenuNap(btn, menu);
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('.nap-acciones-wrap')) return;
        cerrarMenusNap();
    });
    window.addEventListener('resize', function () { cerrarMenusNap(); });
    window.addEventListener('scroll', function () {
        if (Date.now() < ignoreScrollNap) return;
        cerrarMenusNap();
    }, true);
})();
</script>
@endsection
