@extends('layouts.app')

@section('title', 'Salidas PON')

@push('styles')
<style>
    .pon-table tbody tr {
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }
</style>
@endpush

@section('content')
@php
    $busqueda = $busqueda ?? '';
    $nodoId = request('nodo_id');
    $oltId = request('olt_id');
    $tipoModulo = request('tipo_modulo');
    $filtroNap = request('filtro_nap');

    $filtrosActivos = [];
    if (request()->filled('q')) {
        $filtrosActivos[] = ['clave' => 'q', 'label' => 'Búsqueda: "'.request('q').'"', 'url' => route('sistema.salida-pons.index', request()->except(['q', 'page']))];
    }
    if (request()->filled('nodo_id')) {
        $nodoSel = $nodos->firstWhere('nodo_id', (int) $nodoId);
        $filtrosActivos[] = ['clave' => 'nodo_id', 'label' => 'Nodo: '.($nodoSel?->descripcion ?? '#'.$nodoId), 'url' => route('sistema.salida-pons.index', request()->except(['nodo_id', 'page']))];
    }
    if (request()->filled('olt_id')) {
        $oltSel = $olts->firstWhere('olt_id', (int) $oltId);
        $filtrosActivos[] = ['clave' => 'olt_id', 'label' => 'OLT: '.($oltSel?->codigo ?? $oltSel?->ip ?? '#'.$oltId), 'url' => route('sistema.salida-pons.index', request()->except(['olt_id', 'page']))];
    }
    if (request()->filled('tipo_modulo')) {
        $filtrosActivos[] = ['clave' => 'tipo_modulo', 'label' => 'Módulo: '.$tipoModulo, 'url' => route('sistema.salida-pons.index', request()->except(['tipo_modulo', 'page']))];
    }
    if ($filtroNap === 'con_naps') {
        $filtrosActivos[] = ['clave' => 'filtro_nap', 'label' => 'Con cajas NAP', 'url' => route('sistema.salida-pons.index', request()->except(['filtro_nap', 'page']))];
    } elseif ($filtroNap === 'sin_naps') {
        $filtrosActivos[] = ['clave' => 'filtro_nap', 'label' => 'Sin cajas NAP', 'url' => route('sistema.salida-pons.index', request()->except(['filtro_nap', 'page']))];
    }

    $puedeCrear = auth()->user()?->tienePermiso('ftth-salidas-pon.crear');
    $puedeEditar = auth()->user()?->tienePermiso('ftth-salidas-pon.editar');
    $puedeEliminar = auth()->user()?->tienePermiso('ftth-salidas-pon.eliminar');
@endphp

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    {{-- CABECERA PRINCIPAL CON ICONO SVG --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-purple-100 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0 shadow-sm border border-purple-200 dark:border-purple-800/60">
                {{-- SVG Fibra Óptica / Puerto PON --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">Salidas PON</h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Troncales de fibra óptica, puertos de transmisión OLT, potencia de módulos SFP y ramales a cajas NAP.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ request()->fullUrl() }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors">
                {{-- SVG Actualizar --}}
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Actualizar
            </a>
            @if($puedeCrear)
            <a href="{{ route('sistema.salida-pons.create') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 hover:bg-purple-700 px-4 py-2.5 font-semibold text-sm text-white shadow-md shadow-purple-600/20 transition-all hover:shadow-lg hover:shadow-purple-600/30">
                {{-- SVG Plus --}}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nueva salida PON
            </a>
            @endif
        </div>
    </div>

    {{-- DECK SUPERIOR DE 4 TARJETAS NOC / FTTH --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        {{-- Card 1: Total Salidas PON --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Salidas PON</span>
                <span class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    {{-- SVG Rayo PON --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-purple-600 dark:text-purple-400">
                    {{ $kpis['total'] ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">interfaces ópticas</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                Troncales FTTH registradas
            </div>
        </div>

        {{-- Card 2: Puertos OLT Enlazados --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Puertos OLT</span>
                <span class="w-8 h-8 rounded-xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                    {{-- SVG OLT Server --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-sky-600 dark:text-sky-400">
                    {{ $kpis['con_olt'] ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">vinculadas a OLT</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                De {{ $olts->count() }} OLTs en red
            </div>
        </div>

        {{-- Card 3: Cajas NAP Conectadas --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cajas NAP</span>
                <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    {{-- SVG NAP Box --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400">
                    {{ $kpis['total_naps'] ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">puntos de derivación</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                Alimentados por estas salidas
            </div>
        </div>

        {{-- Card 4: Potencia Óptica Promedio --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Potencia Óptica</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    {{-- SVG Signal dBm --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                    {{ $kpis['potencia_promedio'] > 0 ? '+'.$kpis['potencia_promedio'] : $kpis['potencia_promedio'] }}
                </span>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">dBm prom.</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                {{ $kpis['con_potencia'] ?? 0 }} salidas con lectura registrada
            </div>
        </div>
    </div>

    {{-- CONTENEDOR PRINCIPAL: BUSCADOR + FILTROS + TABLA --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

        {{-- FORMULARIO DE FILTROS & BÚSQUEDA --}}
        <form method="GET" action="{{ route('sistema.salida-pons.index') }}" id="form-filtros-pon" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/80">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                {{-- Input Buscador --}}
                <div class="flex items-center flex-1 min-w-0 h-11 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:ring-2 focus-within:ring-purple-500/20 focus-within:border-purple-500 transition-all">
                    <span class="pl-3.5 text-gray-400 dark:text-gray-500">
                        {{-- SVG Search --}}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    </span>
                    <input type="search" name="q" value="{{ $busqueda }}"
                        placeholder="Buscar por código de salida, nodo, OLT, puerto o caja NAP..."
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2.5 pr-3 py-2 text-sm focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @if($busqueda !== '')
                        <a href="{{ route('sistema.salida-pons.index', request()->except(['q', 'page'])) }}" class="pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Limpiar búsqueda">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>

                {{-- Filtro Rápido por Nodo --}}
                <div class="sm:w-56 shrink-0">
                    <select name="nodo_id" onchange="this.form.submit()"
                        class="w-full h-11 px-3 text-xs sm:text-sm font-medium rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                        <option value="">🏢 Todos los nodos</option>
                        @foreach($nodos as $n)
                            <option value="{{ $n->nodo_id }}" @selected((string) $nodoId === (string) $n->nodo_id)>
                                {{ $n->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Paginación y Popover de Filtros Avanzados --}}
                <div class="flex items-center gap-2 shrink-0">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span class="hidden md:inline">Mostrar:</span>
                        <select name="per_page" onchange="this.form.submit()"
                            class="h-11 px-2.5 text-xs font-semibold rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-purple-500">
                            @foreach ([10, 15, 25, 50, 100] as $p)
                                <option value="{{ $p }}" @selected($salidas->perPage() == $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Popover de Filtros Avanzados --}}
                    <div class="relative" id="pon-filtros-wrap">
                        <button type="button" id="pon-filtros-btn"
                            class="h-11 inline-flex items-center gap-2 px-3.5 rounded-xl border font-semibold text-xs sm:text-sm transition-all {{ count($filtrosActivos) > 0 ? 'border-purple-600 bg-purple-600 text-white shadow-sm shadow-purple-600/30' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600' }}">
                            {{-- SVG Filter Funnel --}}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <span>Filtros</span>
                            @if(count($filtrosActivos) > 0)
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold bg-white text-purple-700">
                                    {{ count($filtrosActivos) }}
                                </span>
                            @endif
                        </button>

                        <div id="pon-filtros-menu" class="hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] p-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl z-30 space-y-3.5">
                            <div class="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-bold text-gray-900 dark:text-gray-100">Filtros de Salidas PON</span>
                                <a href="{{ route('sistema.salida-pons.index') }}" class="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline">
                                    Limpiar todos
                                </a>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">OLT Conectada</label>
                                <select name="olt_id" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">Todas las OLTs</option>
                                    @foreach ($olts as $o)
                                        <option value="{{ $o->olt_id }}" @selected((string) $oltId === (string) $o->olt_id)>
                                            {{ $o->codigo ?? $o->ip }} ({{ $o->ip }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Tipo de Módulo SFP</label>
                                <select name="tipo_modulo" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">Todos los tipos</option>
                                    @foreach (\App\Models\SalidaPon::TIPOS_MODULO as $mod)
                                        <option value="{{ $mod }}" @selected($tipoModulo === $mod)>{{ $mod }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Cajas NAP</label>
                                <select name="filtro_nap" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">Todas</option>
                                    <option value="con_naps" @selected($filtroNap === 'con_naps')>Solo con Cajas NAP</option>
                                    <option value="sin_naps" @selected($filtroNap === 'sin_naps')>Solo sin Cajas NAP</option>
                                </select>
                            </div>

                            <div class="pt-2 flex items-center gap-2">
                                <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-purple-600 text-white text-xs font-bold hover:bg-purple-700 shadow-sm transition-colors">
                                    Aplicar Filtros
                                </button>
                                <button type="button" onclick="document.getElementById('pon-filtros-menu').classList.add('hidden')" class="py-2 px-3 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                            <span>{{ $fa['label'] }}</span>
                            <a href="{{ $fa['url'] }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-200" title="Remover este filtro">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        </span>
                    @endforeach
                    <a href="{{ route('sistema.salida-pons.index') }}" class="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline ml-1">
                        Restablecer
                    </a>
                </div>
            @endif
        </form>

        {{-- TABLA MODERNIZADA DE SALIDAS PON --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 pon-table text-left">
                <thead class="bg-gray-50/90 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">Código de Salida PON</th>
                        <th class="px-4 py-3.5">Nodo / Central</th>
                        <th class="px-4 py-3.5">OLT Vinculada</th>
                        <th class="px-4 py-3.5">Puerto PON</th>
                        <th class="px-4 py-3.5">Módulo / Potencia</th>
                        <th class="px-4 py-3.5">Cajas NAP Derivadas</th>
                        <th class="px-4 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/80 bg-white dark:bg-gray-800">
                    @forelse ($salidas as $s)
                        @php
                            $coords = $s->getCoordenadasParaMapa();
                            $mapUrl = $coords ? 'https://www.google.com/maps?q='.$coords['lat'].','.$coords['lon'] : null;
                            $trabajosAbiertos = $trabajosAbiertosPorSalida[$s->salida_pon_id] ?? 0;
                            $potencia = $s->potencia_salida !== null ? (float) $s->potencia_salida : null;
                        @endphp
                        <tr class="hover:bg-purple-50/30 dark:hover:bg-gray-700/50">
                            {{-- 1. CÓDIGO DE SALIDA PON --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 flex items-center justify-center shrink-0 border border-purple-200 dark:border-purple-800/50">
                                        {{-- SVG Fibra PON --}}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-bold text-sm text-gray-900 dark:text-gray-100 block truncate">
                                            {{ $s->codigo }}
                                        </span>
                                        @if($s->nota)
                                            <span class="text-xs text-gray-500 dark:text-gray-400 truncate block max-w-xs" title="{{ $s->nota }}">
                                                {{ Str::limit($s->nota, 40) }}
                                            </span>
                                        @endif
                                        @if($trabajosAbiertos > 0)
                                            <a href="{{ route('trabajos-red.index', ['activo_tipo' => 'salida_pon', 'q' => $s->codigo]) }}"
                                                class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800 hover:bg-amber-200 transition-colors">
                                                <span>🛠️ {{ $trabajosAbiertos }} orden(es) en red</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- 2. NODO --}}
                            <td class="px-4 py-3.5 align-middle text-xs sm:text-sm text-gray-700 dark:text-gray-300">
                                @if($s->nodo)
                                    <div class="flex items-center gap-1.5 font-medium">
                                        {{-- SVG Edificio Nodo --}}
                                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                        <span class="truncate">{{ $s->nodo->descripcion }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-400 italic">—</span>
                                @endif
                            </td>

                            {{-- 3. OLT --}}
                            <td class="px-4 py-3.5 align-middle text-xs sm:text-sm">
                                @if($s->olt)
                                    <a href="{{ route('sistema.olts.show', $s->olt) }}"
                                        class="inline-flex items-center gap-1.5 font-bold text-purple-700 dark:text-purple-300 hover:underline">
                                        {{-- SVG OLT Server --}}
                                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                        </svg>
                                        <span>{{ $s->olt->codigo ?? $s->olt->ip ?? 'OLT #'.$s->olt_id }}</span>
                                    </a>
                                    @if($s->olt->ip && $s->olt->codigo)
                                        <span class="block text-[11px] font-mono text-gray-500 dark:text-gray-400 pl-5.5">
                                            {{ $s->olt->ip }}
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 italic">
                                        — Sin OLT
                                    </span>
                                @endif
                            </td>

                            {{-- 4. PUERTO PON --}}
                            <td class="px-4 py-3.5 align-middle text-xs">
                                @if($s->oltPuerto)
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-800 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60 font-semibold">
                                        {{-- SVG Chip Puerto --}}
                                        <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                        </svg>
                                        <span>PON {{ $s->oltPuerto->numero }}</span>
                                        <span class="text-[10px] uppercase font-bold text-sky-600 dark:text-sky-400">({{ $s->oltPuerto->tipo_pon }})</span>
                                    </div>
                                @else
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-semibold">
                                            Puerto #{{ $s->puerto_olt }}
                                        </span>
                                        @if($s->olt_id)
                                            <span class="block text-[10px] text-amber-600 dark:text-amber-400">Manual (sin tabla)</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            {{-- 5. MÓDULO / POTENCIA ÓPTICA --}}
                            <td class="px-4 py-3.5 align-middle text-xs">
                                <div class="space-y-1">
                                    @if($s->tipo_modulo)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md font-bold text-[11px] bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                            {{ $s->tipo_modulo }}
                                        </span>
                                    @endif

                                    @if($potencia !== null)
                                        <div class="inline-flex items-center gap-1 font-bold text-emerald-700 dark:text-emerald-400">
                                            {{-- SVG Optical Signal --}}
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            <span>{{ number_format($potencia, 2) }} dBm</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 italic block text-[11px]">— dBm</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 6. CAJAS NAP DERIVADAS --}}
                            <td class="px-4 py-3.5 align-middle text-xs">
                                @if($s->cajaNaps->isNotEmpty())
                                    <div class="space-y-1 max-w-xs">
                                        <div class="flex items-center gap-1 font-semibold text-gray-500 dark:text-gray-400 text-[11px]">
                                            {{-- SVG Box --}}
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            <span>{{ $s->cajaNaps->count() }} caja(s) NAP:</span>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($s->cajaNaps->take(4) as $nap)
                                                <a href="{{ route('sistema.cajas-nap.show', $nap) }}"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-purple-100 hover:text-purple-800 dark:hover:bg-purple-950 dark:hover:text-purple-300 transition-colors">
                                                    {{ $nap->codigo }}
                                                </a>
                                            @endforeach
                                            @if($s->cajaNaps->count() > 4)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                    +{{ $s->cajaNaps->count() - 4 }} más
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Sin cajas NAP</span>
                                @endif
                            </td>

                            {{-- 7. MENÚ EMERGENTE DE ACCIONES --}}
                            <td class="px-4 py-3.5 align-middle text-right w-14">
                                @php
                                    $puedeTrabajoPon = (bool) auth()->user()?->tienePermiso('trabajos-red.crear');
                                    $tieneAccionesPon = $puedeTrabajoPon || $mapUrl || $puedeEditar || $puedeEliminar;
                                    $itemAccion = 'flex w-full items-center gap-2 px-3.5 py-2 text-sm font-medium text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
                                @endphp
                                @if($tieneAccionesPon)
                                    <div class="pon-acciones-wrap relative inline-flex justify-end">
                                        <button type="button"
                                            class="pon-acciones-btn inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                            title="Acciones"
                                            aria-haspopup="menu"
                                            aria-expanded="false">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                        </button>
                                        <div class="pon-acciones-menu hidden py-1.5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl" role="menu">
                                            @if($puedeTrabajoPon)
                                                <a href="{{ route('trabajos-red.create', ['activo_tipo' => 'salida_pon', 'nodo_id' => $s->nodo_id, 'activo_id' => $s->salida_pon_id]) }}"
                                                    class="{{ $itemAccion }}" role="menuitem">
                                                    {!! \App\Support\TrabajoRedIconos::svg('herramienta', 'w-4 h-4 shrink-0') !!}
                                                    <span>Crear trabajo de red</span>
                                                </a>
                                            @endif
                                            @if($mapUrl)
                                                <a href="{{ $mapUrl }}" target="_blank" rel="noopener"
                                                    class="{{ $itemAccion }}" role="menuitem">
                                                    {!! \App\Support\TrabajoRedIconos::svg('pin', 'w-4 h-4 shrink-0') !!}
                                                    <span>Ver en mapa</span>
                                                </a>
                                            @endif
                                            @if($puedeEditar)
                                                <a href="{{ route('sistema.salida-pons.edit', $s) }}"
                                                    class="{{ $itemAccion }}" role="menuitem">
                                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                    <span>Editar</span>
                                                </a>
                                            @endif
                                            @if($puedeEliminar)
                                                <form action="{{ route('sistema.salida-pons.destroy', $s) }}" method="POST"
                                                    onsubmit="return confirm('¿Seguro que deseas eliminar la Salida PON {{ $s->codigo }}? Esta acción no se puede deshacer.');">
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
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-400 mx-auto flex items-center justify-center mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">No se encontraron salidas PON</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">No hay salidas PON que coincidan con los filtros seleccionados.</p>
                                @if($puedeCrear)
                                    <div class="mt-3">
                                        <a href="{{ route('sistema.salida-pons.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-600 text-white text-xs font-bold hover:bg-purple-700">
                                            Crear salida PON
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINACIÓN --}}
        @if ($salidas->hasPages())
            <div class="px-4 py-3.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800">
                {{ $salidas->links() }}
            </div>
        @endif
    </div>
</div>

{{-- SCRIPT INTERACTIVO --}}
<script>
(function () {
    var btnFiltros = document.getElementById('pon-filtros-btn');
    var menuFiltros = document.getElementById('pon-filtros-menu');
    var wrapFiltros = document.getElementById('pon-filtros-wrap');
    if (btnFiltros && menuFiltros) {
        btnFiltros.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            menuFiltros.classList.toggle('hidden');
        });
        document.addEventListener('click', function (e) {
            if (wrapFiltros && !wrapFiltros.contains(e.target)) {
                menuFiltros.classList.add('hidden');
            }
        });
    }

    var ignoreScrollPon = 0;

    function cerrarMenusPon(excepto) {
        document.querySelectorAll('.pon-acciones-menu').forEach(function (m) {
            if (m !== excepto) m.classList.add('hidden');
        });
        document.querySelectorAll('.pon-acciones-btn').forEach(function (b) {
            if (!excepto || b.parentElement.querySelector('.pon-acciones-menu') !== excepto) {
                b.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function posicionarMenuPon(btn, menu) {
        var margin = 8;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var r = btn.getBoundingClientRect();
        ignoreScrollPon = Date.now() + 300;
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

    document.querySelectorAll('.pon-acciones-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var menu = btn.parentElement.querySelector('.pon-acciones-menu');
            if (!menu) return;
            var abierto = !menu.classList.contains('hidden');
            cerrarMenusPon();
            if (!abierto) {
                posicionarMenuPon(btn, menu);
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('.pon-acciones-wrap')) return;
        cerrarMenusPon();
    });
    window.addEventListener('resize', function () { cerrarMenusPon(); });
    window.addEventListener('scroll', function () {
        if (Date.now() < ignoreScrollPon) return;
        cerrarMenusPon();
    }, true);
})();
</script>
@endsection
