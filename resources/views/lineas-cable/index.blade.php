@extends('layouts.app')

@section('title', 'Cables y Planta Externa')

@push('styles')
<style>
    .cable-table tbody tr {
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }
</style>
@endpush

@section('content')
@php
    $busqueda = $busqueda ?? '';
    $tipoCable = request('tipo_cable');
    $estado = request('estado');
    $capacidad = request('cantidad_hilos');
    $fibraColorId = request('fibra_color_id');

    $filtrosActivos = [];
    if (request()->filled('q') || request()->filled('buscar')) {
        $qVal = request('q', request('buscar'));
        $filtrosActivos[] = ['clave' => 'q', 'label' => 'Búsqueda: "'.$qVal.'"', 'url' => route('sistema.lineas-cable.index', request()->except(['q', 'buscar', 'page']))];
    }
    if (request()->filled('tipo_cable')) {
        $filtrosActivos[] = ['clave' => 'tipo_cable', 'label' => 'Tipo: '.($tiposCable[$tipoCable] ?? ucfirst($tipoCable)), 'url' => route('sistema.lineas-cable.index', request()->except(['tipo_cable', 'page']))];
    }
    if (request()->filled('estado')) {
        $filtrosActivos[] = ['clave' => 'estado', 'label' => 'Estado: '.($estados[$estado] ?? ucfirst($estado)), 'url' => route('sistema.lineas-cable.index', request()->except(['estado', 'page']))];
    }
    if (request()->filled('cantidad_hilos')) {
        $filtrosActivos[] = ['clave' => 'cantidad_hilos', 'label' => 'Capacidad: '.$capacidad.' FO', 'url' => route('sistema.lineas-cable.index', request()->except(['cantidad_hilos', 'page']))];
    }
    if (request()->filled('fibra_color_id')) {
        $colSel = $fibraColores->firstWhere('fibra_color_id', (int) $fibraColorId);
        $filtrosActivos[] = ['clave' => 'fibra_color_id', 'label' => 'Color: '.($colSel?->nombre ?? '#'.$fibraColorId), 'url' => route('sistema.lineas-cable.index', request()->except(['fibra_color_id', 'page']))];
    }

    $puedeCrear = auth()->user()?->tienePermiso('ftth-lineas-cable.crear') ?? true;
    $puedeEditar = auth()->user()?->tienePermiso('ftth-lineas-cable.editar') ?? true;
    $puedeEliminar = auth()->user()?->tienePermiso('ftth-lineas-cable.eliminar') ?? true;

    // Colección indexada para abrir modal con JS nativo
    $cablesIndexados = [];
    foreach ($lineas as $item) {
        $cablesIndexados[$item->linea_cable_id] = [
            'linea_cable_id' => $item->linea_cable_id,
            'nombre'         => $item->nombre ?: 'Cable #'.$item->linea_cable_id,
            'cantidad_hilos' => $item->cantidad_hilos,
            'pelos'          => $item->pelos,
        ];
    }
@endphp

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    {{-- CABECERA PRINCIPAL CON ICONO SVG --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-indigo-100 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 shadow-sm border border-indigo-200 dark:border-indigo-800/60">
                {{-- SVG Cable Reel / Planta Externa --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">Cables y Planta Externa (FO)</h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Tendido de cables de calle, tubos buffer, pelos/hilos individuales y código de colores normalizado TIA-598.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <button type="button" onclick="document.getElementById('guia-colores-tia').classList.toggle('hidden')"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors"
                title="Ver tabla del Código de Colores TIA-598">
                {{-- SVG Color Palette --}}
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4 5 5 0 015-5c1.5 0 2.5 1 3.5 2s2 1.5 3.5 1.5c2 0 3-1.5 3-3.5 0-3-2.5-5.5-6-5.5a8.5 8.5 0 108.5 8.5c0-.5 0-1-.5-1.5"/>
                </svg>
                <span>Código de Colores</span>
            </button>

            <a href="{{ route('sistema.cajas-nap.mapa') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold shadow-sm transition-colors"
                title="Ver trazado en mapa">
                {{-- SVG Map --}}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <span>Ver en mapa</span>
            </a>

            @if($puedeCrear)
                <a href="{{ route('sistema.lineas-cable.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold shadow-sm transition-colors">
                    {{-- SVG Plus --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Nuevo Cable</span>
                </a>
            @endif
        </div>
    </div>

    {{-- GUÍA DESPLEGABLE: CÓDIGO DE COLORES TIA-598-C --}}
    <div id="guia-colores-tia" class="hidden p-5 rounded-2xl bg-white dark:bg-gray-800 border border-purple-200 dark:border-purple-800/60 shadow-md">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-purple-600 animate-pulse"></span>
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Código Internacional de Colores de Fibra Óptica (TIA-598-C)</h3>
            </div>
            <button type="button" onclick="document.getElementById('guia-colores-tia').classList.add('hidden')" class="text-xs font-semibold text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                ✕ Cerrar guía
            </button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            La secuencia estándar de 12 colores se aplica tanto al color de cada tubo buffer como a cada pelo dentro del tubo.
        </p>
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
            @foreach ($coloresTIA as $pos => $c)
                <div class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-750 flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center font-black text-xs shadow-xs {{ $c['texto'] }}" style="background-color: {{ $c['hex'] }}">
                        {{ $pos }}
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate">{{ $c['nombre'] }}</div>
                        <div class="text-[10px] text-gray-400 font-mono">{{ $c['hex'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- DECK SUPERIOR DE 4 KPIS DE PLANTA EXTERNA --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Cables / Tramos --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tramos de Cable</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    {{-- SVG Reel --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                    {{ $kpis['total_cables'] }}
                </span>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">cables tendidos</span>
            </div>
            <div class="mt-1 flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                <span class="text-purple-600 dark:text-purple-400 font-bold">{{ $kpis['cables_troncales'] }} troncales</span>
                <span>·</span>
                <span class="text-cyan-600 dark:text-cyan-400 font-bold">{{ $kpis['cables_distribucion'] }} dist.</span>
                <span>·</span>
                <span>{{ $kpis['cables_drop'] }} drops</span>
            </div>
        </div>

        {{-- Card 2: Metraje Total --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Metraje en Campo</span>
                <span class="w-8 h-8 rounded-xl bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                    {{-- SVG Ruler --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-cyan-600 dark:text-cyan-400">
                    {{ $kpis['total_metros'] >= 1000 ? $kpis['total_km'] : number_format($kpis['total_metros'], 0) }}
                </span>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                    {{ $kpis['total_metros'] >= 1000 ? 'km de fibra' : 'metros' }}
                </span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                {{ number_format($kpis['total_metros'], 0) }} metros lineales registrados
            </div>
        </div>

        {{-- Card 3: Pelos / Hilos en Servicio --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Hilos / Pelos (FO)</span>
                <span class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    {{-- SVG Fiber Core / Light --}}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-purple-600 dark:text-purple-400">
                    {{ $kpis['pelos_iluminados'] }}
                </span>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                    / {{ $kpis['total_pelos'] }} iluminados
                </span>
            </div>
            <div class="mt-2">
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div class="h-1.5 rounded-full transition-all duration-300 {{ $kpis['porcentaje_ocupacion'] >= 80 ? 'bg-rose-500' : ($kpis['porcentaje_ocupacion'] >= 50 ? 'bg-amber-500' : 'bg-purple-600') }}"
                        style="width: {{ min(100, $kpis['porcentaje_ocupacion']) }}%"></div>
                </div>
                <div class="mt-1 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                    <span>{{ $kpis['porcentaje_ocupacion'] }}% en uso</span>
                    <span>{{ $kpis['pelos_libres'] }} disponibles (reserva)</span>
                </div>
            </div>
        </div>

        {{-- Card 4: Paleta de Colores Normalizada --}}
        <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xs cursor-pointer hover:border-purple-300 transition-colors"
            onclick="document.getElementById('guia-colores-tia').classList.toggle('hidden')">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Colores TIA-598</span>
                <span class="text-[11px] font-bold text-purple-600 dark:text-purple-400">Ver tabla →</span>
            </div>
            <div class="mt-2.5 flex items-center gap-1">
                @foreach (array_slice($coloresTIA, 0, 12, true) as $c)
                    <span class="flex-1 h-5 rounded-md shadow-xs" style="background-color: {{ $c['hex'] }}" title="{{ $c['pos'] }}. {{ $c['nombre'] }}"></span>
                @endforeach
            </div>
            <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                12 colores normalizados para tubos e hilos
            </div>
        </div>
    </div>

    {{-- CONTENEDOR PRINCIPAL: BUSCADOR + FILTROS + TABLA --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

        {{-- FORMULARIO DE FILTROS & BÚSQUEDA --}}
        <form method="GET" action="{{ route('sistema.lineas-cable.index') }}" id="form-filtros-cable" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/80">
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
                        placeholder="Buscar cable por nombre, origen, destino, notas o color..."
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2.5 pr-3 py-2 text-sm focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @if($busqueda !== '')
                        <a href="{{ route('sistema.lineas-cable.index', request()->except(['q', 'buscar', 'page'])) }}" class="pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Limpiar búsqueda">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>

                {{-- Filtro Tipo de Cable --}}
                <div class="sm:w-48 shrink-0">
                    <select name="tipo_cable" onchange="document.getElementById('form-filtros-cable').submit()"
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="">Todos los tipos</option>
                        @foreach ($tiposCable as $k => $v)
                            <option value="{{ $k }}" @selected($tipoCable === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro Capacidad de Hilos --}}
                <div class="sm:w-44 shrink-0">
                    <select name="cantidad_hilos" onchange="document.getElementById('form-filtros-cable').submit()"
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="">Todas las capacidades</option>
                        @foreach ($capacidades as $cap)
                            <option value="{{ $cap }}" @selected((string)$capacidad === (string)$cap)>{{ $cap }} Hilos (FO)</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro Estado --}}
                <div class="sm:w-44 shrink-0">
                    <select name="estado" onchange="document.getElementById('form-filtros-cable').submit()"
                        class="w-full h-11 px-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $k => $v)
                            <option value="{{ $k }}" @selected($estado === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Selector Paginación --}}
                <div class="w-24 shrink-0">
                    <select name="per_page" onchange="document.getElementById('form-filtros-cable').submit()"
                        class="w-full h-11 px-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                        <option value="10" @selected($perPage == 10)>10 / pág</option>
                        <option value="15" @selected($perPage == 15)>15 / pág</option>
                        <option value="25" @selected($perPage == 25)>25 / pág</option>
                        <option value="50" @selected($perPage == 50)>50 / pág</option>
                    </select>
                </div>
            </div>

            {{-- CHIPS DE FILTROS ACTIVOS CON BOTÓN REMOVER --}}
            @if(count($filtrosActivos) > 0)
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Filtros aplicados:</span>
                    @foreach ($filtrosActivos as $fa)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">
                            <span>{{ $fa['label'] }}</span>
                            <a href="{{ $fa['url'] }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200" title="Remover este filtro">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        </span>
                    @endforeach
                    <a href="{{ route('sistema.lineas-cable.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline ml-1">
                        Restablecer
                    </a>
                </div>
            @endif
        </form>

        {{-- TABLA MODERNIZADA DE CABLES DE PLANTA EXTERNA --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 cable-table text-left">
                <thead class="bg-gray-50/90 dark:bg-gray-750 text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">Cable de Calle (Nombre / Tipo)</th>
                        <th class="px-4 py-3.5">Capacidad & Pelos</th>
                        <th class="px-4 py-3.5">Origen</th>
                        <th class="px-4 py-3.5">Destino</th>
                        <th class="px-4 py-3.5">Longitud</th>
                        <th class="px-4 py-3.5">Trazado GPS</th>
                        <th class="px-4 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/80 bg-white dark:bg-gray-800">
                    @forelse ($lineas as $l)
                        @php
                            $nombreCable = $l->nombre ?: 'Cable #'.$l->linea_cable_id;
                            $pelosData = $l->pelos;
                            $iluminados = $l->pelosIluminadosCount();
                            $libres = $l->pelosLibresCount();
                            $reservados = $l->pelosReservadosCount();
                            $danados = $l->pelosDanadosCount();
                            $totalPelosCable = count($pelosData);
                            $porcentajeOcup = $l->porcentajeOcupacion();
                            $tubosCount = (int) ceil($totalPelosCable / 12);
                            $hasCoords = is_array($l->coordenadas) && count($l->coordenadas) > 0;
                        @endphp
                        <tr class="hover:bg-indigo-50/30 dark:hover:bg-indigo-950/10">
                            {{-- Cable (Nombre, Tipo, Estado) --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex items-start gap-2.5">
                                    <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 flex items-center justify-center shrink-0 mt-0.5">
                                        {{-- SVG Cable Reel --}}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                                {{ $nombreCable }}
                                            </span>

                                            {{-- Badge Tipo de Cable --}}
                                            @if($l->tipo_cable === 'troncal')
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                                                    Troncal
                                                </span>
                                            @elseif($l->tipo_cable === 'distribucion')
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/60">
                                                    Distribución
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                                                    Drop
                                                </span>
                                            @endif

                                            {{-- Badge Estado --}}
                                            @if($l->estado === 'activo')
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    Activo
                                                </span>
                                            @elseif($l->estado === 'proyectado')
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                                    Proyectado
                                                </span>
                                            @elseif($l->estado === 'danado')
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                    Dañado
                                                </span>
                                            @else
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                                    {{ ucfirst($l->estado) }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($l->notas)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ Str::limit($l->notas, 45) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Capacidad & Pelos --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-extrabold text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800/60">
                                            {{ $totalPelosCable }} FO
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            ({{ $tubosCount }} {{ $tubosCount === 1 ? 'tubo' : 'tubos' }})
                                        </span>
                                    </div>

                                    <div class="mt-1 flex items-center gap-1.5 text-[11px]">
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $iluminados }} en uso</span>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $libres }} libres</span>
                                        @if($reservados > 0)
                                            <span class="text-gray-300 dark:text-gray-600">·</span>
                                            <span class="text-amber-600 dark:text-amber-400 font-semibold">{{ $reservados }} res.</span>
                                        @endif
                                        @if($danados > 0)
                                            <span class="text-gray-300 dark:text-gray-600">·</span>
                                            <span class="text-rose-600 dark:text-rose-400 font-semibold">{{ $danados }} rotos</span>
                                        @endif
                                    </div>

                                    {{-- Botón para abrir modal de gestión de pelos --}}
                                    <div class="mt-1.5">
                                        <button type="button"
                                            onclick="abrirModalPelos({{ $l->linea_cable_id }})"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 hover:underline">
                                            {{-- SVG Strands Matrix --}}
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                            </svg>
                                            <span>Gestionar Pelos ({{ $totalPelosCable }}) →</span>
                                        </button>
                                    </div>
                                </div>
                            </td>

                            {{-- Origen --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex items-center gap-1.5 text-xs">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0" title="Punto de origen"></span>
                                    @if($l->origen_url)
                                        <a href="{{ $l->origen_url }}" class="font-semibold text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                            {{ $l->origen_etiqueta }}
                                        </a>
                                    @else
                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ $l->origen_etiqueta }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Destino --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex items-center gap-1.5 text-xs">
                                    <span class="w-2 h-2 rounded-full bg-purple-500 shrink-0" title="Punto de destino"></span>
                                    @if($l->destino_url)
                                        <a href="{{ $l->destino_url }}" class="font-semibold text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                            {{ $l->destino_etiqueta }}
                                        </a>
                                    @else
                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ $l->destino_etiqueta }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Longitud --}}
                            <td class="px-4 py-3.5 align-middle">
                                @if($l->longitud_metros)
                                    <div>
                                        <span class="font-extrabold text-sm text-gray-900 dark:text-gray-100">
                                            {{ number_format($l->longitud_metros, 0) }} m
                                        </span>
                                        @if($l->longitud_metros >= 1000)
                                            <div class="text-[11px] text-gray-400">
                                                {{ number_format($l->longitud_metros / 1000, 2) }} km
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Trazado GPS --}}
                            <td class="px-4 py-3.5 align-middle">
                                @if($hasCoords)
                                    <a href="{{ route('sistema.cajas-nap.mapa') }}"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold bg-cyan-50 dark:bg-cyan-950/50 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/60 hover:bg-cyan-100 transition-colors">
                                        {{-- SVG Route --}}
                                        <svg class="w-3.5 h-3.5 text-cyan-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                        </svg>
                                        <span>Ruta en mapa</span>
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">Directo (auto)</span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-4 py-3.5 align-middle text-right">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Botón Gestionar Pelos --}}
                                    <button type="button"
                                        onclick="abrirModalPelos({{ $l->linea_cable_id }})"
                                        class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        title="Ver y editar pelos del cable">
                                        {{-- SVG Strands --}}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                        </svg>
                                    </button>

                                    @if($puedeEditar)
                                        {{-- Botón Editar --}}
                                        <a href="{{ route('sistema.lineas-cable.edit', $l) }}"
                                            class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                            title="Editar cable">
                                            {{-- SVG Pencil --}}
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                    @endif

                                    @if($puedeEliminar)
                                        {{-- Botón Eliminar --}}
                                        <form action="{{ route('sistema.lineas-cable.destroy', $l) }}" method="POST" class="inline"
                                            onsubmit="return confirm('¿Está seguro de eliminar el cable {{ $nombreCable }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                title="Eliminar cable">
                                                {{-- SVG Trash --}}
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                        </svg>
                                    </div>
                                    <p class="font-bold text-gray-700 dark:text-gray-300">No se encontraron cables de fibra óptica</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Registre su primer cable de calle con sus hilos y código de colores.</p>
                                    @if($puedeCrear)
                                        <a href="{{ route('sistema.lineas-cable.create') }}" class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-purple-600 text-white hover:bg-purple-700 transition-colors">
                                            + Registrar nuevo cable
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
        @if ($lineas->hasPages())
            <div class="px-4 py-3.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/80">
                {{ $lineas->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL INTERACTIVO: GESTIÓN DE PELOS DEL CABLE (VANILLA JS) --}}
    <div id="modal-pelos-cable" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden" onclick="event.stopPropagation()">

            {{-- Cabecera del modal --}}
            <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-750">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                        FO
                    </span>
                    <div>
                        <h3 id="modal-cable-nombre" class="text-base font-bold text-gray-900 dark:text-gray-100">Gestión de Pelos</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Identificación de hilos por color normalizado TIA-598, estado y destino de conexión.
                        </p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModalPelos()" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Formulario para guardar cambios en los pelos --}}
            <form id="form-guardar-pelos" method="POST" action="" class="flex-1 overflow-y-auto p-5 space-y-4">
                @csrf

                <div class="flex items-center justify-between bg-indigo-50/60 dark:bg-indigo-950/30 p-3 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                    <div class="text-xs text-indigo-900 dark:text-indigo-200">
                        <strong id="modal-cable-info"></strong>
                        · Asigne el estado a cada hilo (Iluminado, Libre, Reservado, Dañado) y escriba su uso en campo.
                    </div>
                </div>

                {{-- Tabla de pelos --}}
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-750 text-gray-500 dark:text-gray-400 font-bold uppercase">
                            <tr>
                                <th class="px-3 py-2.5 text-center w-12">#</th>
                                <th class="px-3 py-2.5 text-left w-28">Tubo Buffer</th>
                                <th class="px-3 py-2.5 text-left w-36">Color del Pelo</th>
                                <th class="px-3 py-2.5 text-left w-40">Estado</th>
                                <th class="px-3 py-2.5 text-left">Uso / Enlace en Campo</th>
                            </tr>
                        </thead>
                        <tbody id="modal-pelos-tbody" class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                            {{-- Se inyecta dinámicamente con JS --}}
                        </tbody>
                    </table>
                </div>

                {{-- Pie del modal --}}
                <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="cerrarModalPelos()" class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cerrar
                    </button>
                    @if($puedeEditar)
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition-colors">
                            Guardar Matriz de Pelos
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const CABLES_DATA = @json($cablesIndexados);

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function abrirModalPelos(cableId) {
        const cable = CABLES_DATA[cableId];
        if (!cable) return;

        document.getElementById('modal-cable-nombre').innerText = cable.nombre;
        document.getElementById('modal-cable-info').innerText = cable.cantidad_hilos + ' hilos totales';
        document.getElementById('form-guardar-pelos').action = '/sistema/lineas-cable/' + cable.linea_cable_id + '/pelos';

        const tbody = document.getElementById('modal-pelos-tbody');
        tbody.innerHTML = '';

        if (Array.isArray(cable.pelos)) {
            cable.pelos.forEach((pelo, idx) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-750';
                tr.innerHTML = `
                    <td class="px-3 py-2 text-center font-bold text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="pelos[${idx}][numero]" value="${pelo.numero}">
                        ${pelo.numero}
                    </td>
                    <td class="px-3 py-2 align-middle">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: ${pelo.color_tubo_hex}"></span>
                            <span>T${pelo.tubo} ${pelo.color_tubo}</span>
                        </span>
                    </td>
                    <td class="px-3 py-2 align-middle">
                        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-xs" style="background-color: ${pelo.color_pelo_hex}">
                            <span class="${pelo.texto}">${pelo.color_pelo}</span>
                        </span>
                    </td>
                    <td class="px-3 py-2 align-middle">
                        <select name="pelos[${idx}][estado]" class="w-full text-xs py-1 px-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-semibold focus:ring-1 focus:ring-indigo-500">
                            <option value="libre" ${pelo.estado === 'libre' ? 'selected' : ''}>⚪ Libre / Reserva</option>
                            <option value="iluminado" ${pelo.estado === 'iluminado' ? 'selected' : ''}>🟢 Iluminado / Activo</option>
                            <option value="reservado" ${pelo.estado === 'reservado' ? 'selected' : ''}>🟡 Reservado</option>
                            <option value="danado" ${pelo.estado === 'danado' ? 'selected' : ''}>🔴 Dañado / Corte</option>
                        </select>
                    </td>
                    <td class="px-3 py-2 align-middle">
                        <input type="text" name="pelos[${idx}][uso]" value="${escapeHtml(pelo.uso)}" placeholder="Ej: Salida PON 1 a NAP-001..." class="w-full text-xs py-1 px-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400">
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        const modal = document.getElementById('modal-pelos-cable');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModalPelos() {
        const modal = document.getElementById('modal-pelos-cable');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Cerrar con Escape o clic fuera
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalPelos();
        }
    });

    document.getElementById('modal-pelos-cable').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalPelos();
        }
    });
</script>
@endpush
@endsection
