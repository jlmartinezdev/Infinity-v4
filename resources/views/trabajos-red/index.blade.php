@extends('layouts.app')

@section('title', 'Trabajos de red')

@push('styles')
<style>
    .trabajo-card {
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .trabajo-card:hover {
        border-color: rgba(147, 51, 234, 0.4);
    }
    .kpi-card-active {
        box-shadow: 0 0 0 2px rgba(147, 51, 234, 0.8);
    }
    .scrollbar-thin::-webkit-scrollbar {
        height: 6px;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 9999px;
    }
</style>
@endpush

@section('content')
@php
    $busqueda = $busqueda ?? '';
    $cola = $cola ?? request('cola', '');
    $resaltar = fn (?string $texto): string => \App\Support\SearchHighlight::html($texto, $busqueda);
    $etiquetaAntiguedad = function (?\Illuminate\Support\Carbon $fecha): string {
        if (! $fecha) {
            return '';
        }
        $dias = (int) $fecha->copy()->startOfDay()->diffInDays(now()->startOfDay());

        return match (true) {
            $dias <= 0 => 'Hoy',
            $dias === 1 => 'Ayer',
            default => 'Hace '.$dias.' días',
        };
    };

    $tabActiva = match (true) {
        $cola === 'en_curso' => 'en_curso',
        $cola === 'abiertos' => 'abiertos',
        $cola === 'pendientes' || $cola === 'pendiente' || request('estado') === 'pendiente' => 'pendientes',
        $cola === 'en_camino' || request('estado') === 'en_camino' => 'en_camino',
        $cola === 'en_proceso' || request('estado') === 'en_proceso' => 'en_proceso',
        $cola === 'alta' => 'alta',
        $cola === 'sin_asignar' => 'sin_asignar',
        $cola === 'resueltos' || request('estado') === 'resuelto' => 'resueltos',
        default => ($cola ? $cola : 'todos'),
    };

    $linkTab = fn ($c) => route('trabajos-red.index', array_merge(request()->except(['cola', 'page', 'estado']), $c && $c !== 'todos' ? ['cola' => $c] : []));

    $queueTabs = [
        ['id' => 'en_curso', 'label' => 'En curso', 'icon' => 'rayo', 'n' => $kpis->en_curso ?? 0, 'href' => $linkTab('en_curso')],
        ['id' => 'pendientes', 'label' => 'Pendientes', 'icon' => 'clock', 'n' => $kpis->pendientes ?? 0, 'href' => $linkTab('pendientes')],
        ['id' => 'en_camino', 'label' => 'En camino', 'icon' => 'truck', 'n' => $kpis->en_camino ?? 0, 'href' => $linkTab('en_camino')],
        ['id' => 'en_proceso', 'label' => 'En proceso', 'icon' => 'herramienta', 'n' => $kpis->en_proceso ?? 0, 'href' => $linkTab('en_proceso')],
        ['id' => 'alta', 'label' => 'Alta prioridad', 'icon' => 'fuego', 'n' => $kpis->alta ?? 0, 'href' => $linkTab('alta')],
        ['id' => 'sin_asignar', 'label' => 'Sin técnico', 'icon' => 'alerta', 'n' => $kpis->sin_asignar ?? 0, 'href' => $linkTab('sin_asignar')],
        ['id' => 'resueltos', 'label' => 'Resueltos', 'icon' => 'check', 'n' => $kpis->resueltos ?? 0, 'href' => $linkTab('resueltos')],
        ['id' => 'todos', 'label' => 'Todos', 'icon' => 'list', 'n' => $kpis->total ?? 0, 'href' => $linkTab('todos')],
    ];

    $filtrosActivos = [];
    if (request()->filled('q')) {
        $filtrosActivos[] = ['clave' => 'q', 'label' => 'Búsqueda: "'.request('q').'"', 'url' => route('trabajos-red.index', request()->except(['q', 'page']))];
    }
    if (request()->filled('tipo')) {
        $etq = \App\Models\TrabajoRed::tipos()[request('tipo')] ?? request('tipo');
        $filtrosActivos[] = ['clave' => 'tipo', 'label' => 'Tipo: '.$etq, 'url' => route('trabajos-red.index', request()->except(['tipo', 'page']))];
    }
    if (request()->filled('estado')) {
        $etq = \App\Models\TrabajoRed::estados()[request('estado')] ?? request('estado');
        $filtrosActivos[] = ['clave' => 'estado', 'label' => 'Estado: '.$etq, 'url' => route('trabajos-red.index', request()->except(['estado', 'page']))];
    }
    if (request()->filled('prioridad')) {
        $etq = \App\Models\TrabajoRed::prioridades()[request('prioridad')] ?? request('prioridad');
        $filtrosActivos[] = ['clave' => 'prioridad', 'label' => 'Prioridad: '.$etq, 'url' => route('trabajos-red.index', request()->except(['prioridad', 'page']))];
    }
    if (request()->filled('nodo_id')) {
        $nodoSel = $nodos->firstWhere('nodo_id', (int) request('nodo_id'));
        $filtrosActivos[] = ['clave' => 'nodo_id', 'label' => 'Nodo: '.($nodoSel?->descripcion ?? '#'.request('nodo_id')), 'url' => route('trabajos-red.index', request()->except(['nodo_id', 'page']))];
    }
    if (request()->filled('activo_tipo')) {
        $etq = \App\Models\TrabajoRed::activoTipos()[request('activo_tipo')] ?? request('activo_tipo');
        $filtrosActivos[] = ['clave' => 'activo_tipo', 'label' => 'Activo: '.$etq, 'url' => route('trabajos-red.index', request()->except(['activo_tipo', 'page']))];
    }
    if (request()->filled('asignado_id')) {
        $tecSel = $tecnicos->firstWhere('usuario_id', (int) request('asignado_id'));
        $filtrosActivos[] = ['clave' => 'asignado_id', 'label' => 'Técnico: '.($tecSel?->name ?? '#'.request('asignado_id')), 'url' => route('trabajos-red.index', request()->except(['asignado_id', 'page']))];
    }
    if (request()->boolean('sin_asignar')) {
        $filtrosActivos[] = ['clave' => 'sin_asignar', 'label' => 'Solo sin técnico', 'url' => route('trabajos-red.index', request()->except(['sin_asignar', 'page']))];
    }

    $puedeEditar = auth()->user()?->tienePermiso('trabajos-red.editar');
    $puedeCrear = auth()->user()?->tienePermiso('trabajos-red.crear');
    $puedeEliminar = auth()->user()?->tienePermiso('trabajos-red.eliminar');
    $puedeActuar = $puedeEditar || $puedeCrear;
    $tecnicosAsignar = ($tecnicosCampo ?? collect())->isNotEmpty() ? $tecnicosCampo : ($tecnicos ?? collect());
@endphp

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    {{-- CABECERA PRINCIPAL --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">Trabajos de red</h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        Gestión operativa de cuadrillas de campo, infraestructura FTTH, nodos y mantenimiento de enlaces.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ request()->fullUrl() }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Actualizar
            </a>
            @if($puedeCrear)
            <a href="{{ route('trabajos-red.create') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 hover:bg-purple-700 px-4 py-2.5 font-semibold text-sm text-white shadow-md shadow-purple-600/20 transition-all hover:shadow-lg hover:shadow-purple-600/30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo trabajo
            </a>
            @endif
        </div>
    </div>

    {{-- PAQUETE A: DECK DE 4 TARJETAS NOC KPI --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        {{-- Card 1: Alta Prioridad --}}
        <a href="{{ $linkTab('alta') }}"
            class="group relative p-4 rounded-2xl border bg-white dark:bg-gray-800 transition-all duration-200 hover:shadow-md {{ $tabActiva === 'alta' ? 'border-red-500 ring-2 ring-red-500/20 bg-red-50/20 dark:bg-red-950/10' : 'border-gray-200 dark:border-gray-700 hover:border-red-300 dark:hover:border-red-800' }}">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Prioridad Alta</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-red-600 dark:text-red-400">
                    {{ $kpis->alta ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">órdenes críticas</span>
            </div>
            <div class="mt-1 flex items-center text-[11px] font-medium text-red-600 dark:text-red-400">
                <span>{{ ($kpis->alta ?? 0) > 0 ? 'Atención requerida' : 'Sin urgencias abiertas' }}</span>
                <span class="ml-1 opacity-0 group-hover:opacity-100 transition-opacity">→</span>
            </div>
        </a>

        {{-- Card 2: Sin Técnico --}}
        <a href="{{ $linkTab('sin_asignar') }}"
            class="group relative p-4 rounded-2xl border bg-white dark:bg-gray-800 transition-all duration-200 hover:shadow-md {{ $tabActiva === 'sin_asignar' ? 'border-amber-500 ring-2 ring-amber-500/20 bg-amber-50/20 dark:bg-amber-950/10' : 'border-gray-200 dark:border-gray-700 hover:border-amber-300 dark:hover:border-amber-800' }}">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sin Técnico</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400">
                    {{ $kpis->sin_asignar ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">por despachar</span>
            </div>
            <div class="mt-1 flex items-center text-[11px] font-medium text-amber-600 dark:text-amber-400">
                <span>Asignar cuadrilla</span>
                <span class="ml-1 opacity-0 group-hover:opacity-100 transition-opacity">→</span>
            </div>
        </a>

        {{-- Card 3: En Curso / En Terreno --}}
        <a href="{{ $linkTab('en_curso') }}"
            class="group relative p-4 rounded-2xl border bg-white dark:bg-gray-800 transition-all duration-200 hover:shadow-md {{ $tabActiva === 'en_curso' ? 'border-sky-500 ring-2 ring-sky-500/20 bg-sky-50/20 dark:bg-sky-950/10' : 'border-gray-200 dark:border-gray-700 hover:border-sky-300 dark:hover:border-sky-800' }}">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">En Curso</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-sky-600 dark:text-sky-400">
                    {{ $kpis->en_curso ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">activos en red</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                {{ $kpis->en_camino ?? 0 }} en camino · {{ $kpis->en_proceso ?? 0 }} en proceso
            </div>
        </a>

        {{-- Card 4: Resueltos / Histórico --}}
        <a href="{{ $linkTab('resueltos') }}"
            class="group relative p-4 rounded-2xl border bg-white dark:bg-gray-800 transition-all duration-200 hover:shadow-md {{ $tabActiva === 'resueltos' ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-gray-200 dark:border-gray-700 hover:border-emerald-300 dark:hover:border-emerald-800' }}">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Resueltos</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                    {{ $kpis->resueltos ?? 0 }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">completados</span>
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                De {{ $kpis->total ?? 0 }} órdenes totales
            </div>
        </a>
    </div>

    {{-- BARRA DE PESTAÑAS DE COLA 1-CLIC --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
        @foreach ($queueTabs as $tab)
            @php $activa = ($tab['id'] === $tabActiva); @endphp
            <a href="{{ $tab['href'] }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold shrink-0 transition-all {{ $activa ? 'bg-purple-600 text-white shadow-sm shadow-purple-600/30' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                {!! \App\Support\TrabajoRedIconos::svg($tab['icon'], 'w-3.5 h-3.5') !!}
                <span>{{ $tab['label'] }}</span>
                <span class="inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-xs font-bold {{ $activa ? 'bg-white/25 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                    {{ $tab['n'] }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- CONTENEDOR PRINCIPAL: BUSCADOR + FILTROS + LISTA DE ÓRDENES --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-visible">

        {{-- FORMULARIO DE BÚSQUEDA Y FILTROS --}}
        <form method="GET" action="{{ route('trabajos-red.index') }}" id="form-filtros-trabajo" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/75 dark:bg-gray-800/80">
            @if($cola)
                <input type="hidden" name="cola" value="{{ $cola }}">
            @endif
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                {{-- Input de búsqueda --}}
                <div class="flex items-center flex-1 min-w-0 h-11 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:ring-2 focus-within:ring-purple-500/20 focus-within:border-purple-500 transition-all">
                    <span class="pl-3.5 text-gray-400 dark:text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    </span>
                    <input type="search" name="q" value="{{ $busqueda }}"
                        placeholder="Buscar por título, nodo, caja nap, router, técnico o #id..."
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2.5 pr-3 py-2 text-sm focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @if($busqueda !== '')
                        <a href="{{ route('trabajos-red.index', request()->except(['q', 'page'])) }}" class="pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Limpiar búsqueda">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>

                {{-- Selector de Elementos por Página --}}
                <div class="flex items-center gap-2 shrink-0">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span class="hidden md:inline">Mostrar:</span>
                        <select name="per_page" onchange="this.form.submit()"
                            class="h-11 px-2.5 text-xs font-semibold rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-purple-500">
                            @foreach ([10, 15, 25, 50, 100] as $p)
                                <option value="{{ $p }}" @selected($trabajos->perPage() == $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Botón Popover de Filtros Avanzados --}}
                    <div class="relative z-40" id="trabajo-filtros-wrap">
                        <button type="button" id="trabajo-filtros-btn"
                            class="h-11 inline-flex items-center gap-2 px-4 rounded-xl border font-semibold text-sm transition-all {{ count($filtrosActivos) > 0 ? 'border-purple-600 bg-purple-600 text-white shadow-sm shadow-purple-600/30' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <span>Filtros</span>
                            @if(count($filtrosActivos) > 0)
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold bg-white text-purple-700">
                                    {{ count($filtrosActivos) }}
                                </span>
                            @endif
                        </button>

                        {{-- Menú Popover de Filtros (posición fija vía JS para no salirse en móvil) --}}
                        <div id="trabajo-filtros-menu" class="hidden overflow-y-auto p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl space-y-4">
                            <div class="flex items-center justify-between gap-4 pb-3 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-base font-bold text-gray-900 dark:text-gray-100">Filtros de Trabajos</span>
                                <a href="{{ route('trabajos-red.index', $cola ? ['cola' => $cola] : []) }}" class="text-sm font-semibold text-purple-600 dark:text-purple-400 hover:underline">
                                    Limpiar todos
                                </a>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Tipo</label>
                                    <select name="tipo" class="w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="">Todos</option>
                                        @foreach (\App\Models\TrabajoRed::tipos() as $key => $label)
                                            <option value="{{ $key }}" @selected(request('tipo') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Estado</label>
                                    <select name="estado" class="w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="">Todos</option>
                                        @foreach (\App\Models\TrabajoRed::estados() as $key => $label)
                                            <option value="{{ $key }}" @selected(request('estado') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Prioridad</label>
                                    <select name="prioridad" class="w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="">Todas</option>
                                        @foreach (\App\Models\TrabajoRed::prioridades() as $key => $label)
                                            <option value="{{ $key }}" @selected(request('prioridad') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Tipo de activo</label>
                                    <select name="activo_tipo" class="w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="">Todos</option>
                                        @foreach (\App\Models\TrabajoRed::activoTipos() as $key => $label)
                                            <option value="{{ $key }}" @selected(request('activo_tipo') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Nodo / Central</label>
                                <select name="nodo_id" class="w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">Todos los nodos</option>
                                    @foreach ($nodos as $nodo)
                                        <option value="{{ $nodo->nodo_id }}" @selected((string) request('nodo_id') === (string) $nodo->nodo_id)>{{ $nodo->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Técnico asignado</label>
                                <select name="asignado_id" class="w-full h-11 px-3 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">Todos</option>
                                    @foreach ($tecnicos as $t)
                                        <option value="{{ $t->usuario_id }}" @selected((string) request('asignado_id') === (string) $t->usuario_id)>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <label class="inline-flex items-center gap-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="checkbox" name="sin_asignar" value="1" @checked(request()->boolean('sin_asignar')) class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                Solo órdenes sin técnico asignado
                            </label>

                            <div class="pt-1 flex items-center gap-2">
                                <button type="submit" class="flex-1 h-11 px-4 rounded-xl bg-purple-600 text-white text-sm font-bold hover:bg-purple-700 shadow-sm transition-colors">
                                    Aplicar filtros
                                </button>
                                <button type="button" onclick="document.getElementById('trabajo-filtros-menu').classList.add('hidden')" class="h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PAQUETE A: CHIPS DE FILTROS ACTIVOS CON BOTÓN REMOVER --}}
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
                    <a href="{{ route('trabajos-red.index', $cola ? ['cola' => $cola] : []) }}" class="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:underline ml-1">
                        Restablecer
                    </a>
                </div>
            @endif
        </form>

        {{-- PAQUETE B: LISTA DE FILAS DE TRABAJOS DE RED --}}
        @if($trabajos->isEmpty())
            <div class="py-16 px-4 text-center rounded-b-2xl">
                <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 dark:bg-gray-700/60 text-gray-400 dark:text-gray-500 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">No se encontraron trabajos de red</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                    No hay órdenes registradas con los filtros seleccionados o en esta cola operativa.
                </p>
                @if($puedeCrear)
                    <div class="mt-4">
                        <a href="{{ route('trabajos-red.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Crear nuevo trabajo
                        </a>
                    </div>
                @endif
            </div>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-700/80">
                @foreach ($trabajos as $trabajo)
                    @php
                        $urlActivo = $trabajo->urlActivo();
                        $abierto = in_array($trabajo->estado, \App\Models\TrabajoRed::estadosAbiertos(), true);
                        $mapsDesde = $trabajo->mapsUrlDesde();
                        $mapsHasta = $trabajo->mapsUrlHasta();
                        $rutaMaps = $trabajo->urlRutaMaps();
                        $waUrl = $trabajo->urlWhatsappDespacho();
                    @endphp

                    <li class="trabajo-card p-4 sm:p-5 hover:bg-gray-50/70 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">

                            {{-- COLUMNA 1: IDENTIDAD, TIPO, ESTADO, TÍTULO Y RUTA --}}
                            <div class="flex-1 min-w-0 space-y-2">
                                {{-- Badges superiores: Tipo, Estado, Prioridad, #ID y Tiempo --}}
                                <div class="flex items-center gap-2 flex-wrap">
                                    {{-- Tipo Badge con Icono --}}
                                    <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold {{ $trabajo->claseBadgeTipo() }}">
                                        {!! \App\Support\TrabajoRedIconos::svgTipo($trabajo->tipo) !!}
                                        <span>{{ $trabajo->etiquetaTipo() }}</span>
                                    </span>

                                    {{-- Estado Badge --}}
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $trabajo->claseBadgeEstado() }}">
                                        {{ $trabajo->etiquetaEstado() }}
                                    </span>

                                    {{-- Prioridad Badge --}}
                                    <span class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-xs font-semibold {{ $trabajo->claseBadgePrioridad() }}">
                                        @if(($trabajo->prioridad ?? 'media') === 'alta')
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></span>
                                        @endif
                                        <span>{{ $trabajo->etiquetaPrioridad() }}</span>
                                    </span>

                                    {{-- ID y Antigüedad --}}
                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-mono font-medium">
                                        #{{ $trabajo->id }} · {{ $etiquetaAntiguedad($trabajo->created_at) }}
                                    </span>
                                </div>

                                {{-- Título del trabajo --}}
                                <div>
                                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
                                        {!! $resaltar($trabajo->titulo) !!}
                                    </h2>
                                    @if($trabajo->descripcion)
                                        <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2 mt-0.5">
                                            {!! $resaltar($trabajo->descripcion) !!}
                                        </p>
                                    @endif
                                </div>

                                {{-- TRAZADO GPS / RUTA --}}
                                @if($trabajo->tieneTrazado())
                                    <div class="inline-flex flex-wrap items-center gap-1.5 p-2 rounded-xl bg-gray-100/80 dark:bg-gray-700/60 border border-gray-200 dark:border-gray-600 text-xs">
                                        <span class="font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1">
                                            {!! \App\Support\TrabajoRedIconos::svg('pin', 'w-3.5 h-3.5 text-sky-600 dark:text-sky-400') !!}
                                            <span class="text-gray-500 dark:text-gray-400 font-normal">Desde:</span>
                                            @if($mapsDesde)
                                                <a href="{{ $mapsDesde }}" target="_blank" rel="noopener" class="text-purple-600 dark:text-purple-400 hover:underline font-semibold">
                                                    {{ $trabajo->etiquetaUbicacionDesde() !== '' ? $trabajo->etiquetaUbicacionDesde() : 'Mapa' }}
                                                </a>
                                            @else
                                                <span>{{ $trabajo->etiquetaUbicacionDesde() !== '' ? $trabajo->etiquetaUbicacionDesde() : '—' }}</span>
                                            @endif
                                        </span>

                                        <span class="text-gray-400">→</span>

                                        <span class="font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1">
                                            {!! \App\Support\TrabajoRedIconos::svg('flag', 'w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400') !!}
                                            <span class="text-gray-500 dark:text-gray-400 font-normal">Hasta:</span>
                                            @if($mapsHasta)
                                                <a href="{{ $mapsHasta }}" target="_blank" rel="noopener" class="text-purple-600 dark:text-purple-400 hover:underline font-semibold">
                                                    {{ $trabajo->etiquetaUbicacionHasta() !== '' ? $trabajo->etiquetaUbicacionHasta() : 'Mapa' }}
                                                </a>
                                            @else
                                                <span>{{ $trabajo->etiquetaUbicacionHasta() !== '' ? $trabajo->etiquetaUbicacionHasta() : '—' }}</span>
                                            @endif
                                        </span>

                                        @if($rutaMaps)
                                            <a href="{{ $rutaMaps }}" target="_blank" rel="noopener"
                                                class="ml-1 inline-flex items-center gap-1 rounded-lg bg-white dark:bg-gray-800 px-2 py-0.5 font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 hover:bg-purple-50 dark:hover:bg-purple-950 transition-colors shadow-2xs">
                                                {!! \App\Support\TrabajoRedIconos::svg('map', 'w-3.5 h-3.5') !!}
                                                <span>Ver ruta</span>
                                                {!! \App\Support\TrabajoRedIconos::svg('external', 'w-3 h-3') !!}
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                {{-- FOTO DE EVIDENCIA O NOTAS EN LA FILA --}}
                                <div class="flex items-center gap-2 pt-0.5 flex-wrap">
                                    @if($trabajo->imagen)
                                        <button type="button"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 btn-ver-foto"
                                            data-foto="{{ asset('storage/'.$trabajo->imagen) }}"
                                            data-titulo="Evidencia Trabajo #{{ $trabajo->id }}: {{ $trabajo->titulo }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span>Foto adjunta</span>
                                        </button>
                                    @endif

                                    @if($trabajo->observaciones)
                                        <button type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 btn-ver-detalles"
                                            data-id="{{ $trabajo->id }}"
                                            data-titulo="{{ $trabajo->titulo }}"
                                            data-tipo="{{ $trabajo->etiquetaTipo() }}"
                                            data-estado="{{ $trabajo->etiquetaEstado() }}"
                                            data-prioridad="{{ $trabajo->etiquetaPrioridad() }}"
                                            data-nodo="{{ $trabajo->nodo?->descripcion ?? 'N/D' }}"
                                            data-activo="{{ $trabajo->etiquetaActivo() }}"
                                            data-descripcion="{{ $trabajo->descripcion ?? '' }}"
                                            data-observaciones="{{ $trabajo->observaciones }}">
                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                            <span>Observaciones / Bitácora</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- COLUMNA 2: ACTIVO AFECTADO Y NODO --}}
                            <div class="lg:w-64 shrink-0 space-y-1.5 p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-700 text-xs">
                                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                    <span class="font-bold uppercase tracking-wider text-[10px]">Activo de Red</span>
                                    <span class="font-medium text-gray-400">{{ \App\Models\TrabajoRed::activoTipos()[$trabajo->activo_tipo] ?? $trabajo->activo_tipo }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-400 dark:text-gray-500 shrink-0">{!! \App\Support\TrabajoRedIconos::svgActivo($trabajo->activo_tipo, 'w-4 h-4') !!}</span>
                                    @if($urlActivo)
                                        <a href="{{ $urlActivo }}" class="font-bold text-sm text-purple-700 dark:text-purple-300 hover:underline truncate">
                                            {!! $resaltar($trabajo->etiquetaActivo()) !!}
                                        </a>
                                    @else
                                        <span class="font-bold text-sm text-gray-900 dark:text-gray-100 truncate">
                                            {!! $resaltar($trabajo->etiquetaActivo()) !!}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1 text-gray-600 dark:text-gray-300 pt-0.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span class="font-medium truncate">Nodo: {!! $resaltar($trabajo->nodo?->descripcion ?? 'No especificado') !!}</span>
                                </div>

                                @if($trabajo->usuario)
                                    <div class="text-[11px] text-gray-400 dark:text-gray-500 pt-0.5 truncate">
                                        Creado por: {{ $trabajo->usuario->name }}
                                    </div>
                                @endif
                            </div>

                            {{-- COLUMNA 3: TÉCNICO Y BOTONES OPERATIVOS --}}
                            <div class="lg:w-64 shrink-0 flex flex-col justify-between gap-3">
                                {{-- Badge Técnico --}}
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                                        Técnico Asignado
                                    </span>
                                    @if($trabajo->asignado)
                                        <div class="flex items-center gap-2 p-2 rounded-xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-100 dark:border-purple-900/50">
                                            <div class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                                {{ strtoupper(substr($trabajo->asignado->name, 0, 1)) }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">{{ $trabajo->asignado->name }}</p>
                                                @if($trabajo->asignado->telefono)
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-mono">{{ $trabajo->asignado->telefono }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        @if($puedeActuar && $abierto)
                                            <button type="button"
                                                class="w-full inline-flex items-center justify-center gap-1.5 p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 text-xs font-bold text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/60 transition-colors btn-abrir-asignar"
                                                data-id="{{ $trabajo->id }}"
                                                data-asignado=""
                                                data-estado="{{ $trabajo->estado }}"
                                                data-titulo="{{ $trabajo->titulo }}">
                                                {!! \App\Support\TrabajoRedIconos::svg('alerta', 'w-3.5 h-3.5') !!}
                                                <span>Sin técnico (Asignar)</span>
                                            </button>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                Sin técnico asignado
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                {{-- Botonera de Acciones Rápidas + Kebab --}}
                                <div class="flex items-center gap-1.5 justify-end pt-1">
                                    {{-- Botón rápido Resolver --}}
                                    @if($puedeActuar && $abierto)
                                        <button type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors btn-abrir-resolver"
                                            data-id="{{ $trabajo->id }}"
                                            data-titulo="{{ $trabajo->titulo }}"
                                            data-tecnico="{{ $trabajo->asignado?->name ?? 'Sin asignar' }}"
                                            data-asignado-id="{{ $trabajo->asignado_id }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span>Resolver</span>
                                        </button>
                                    @endif

                                    {{-- Botón Editar --}}
                                    @if($puedeEditar)
                                        <a href="{{ route('trabajos-red.edit', $trabajo) }}"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span>Editar</span>
                                        </a>
                                    @endif

                                    {{-- Menú Kebab de Acciones Avanzadas (PAQUETE C) --}}
                                    <div class="relative dropdown-kebab-wrap">
                                        <button type="button"
                                            class="p-1.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors btn-kebab"
                                            title="Más acciones">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                        </button>

                                        <div class="dropdown-kebab-menu hidden absolute right-0 mt-1 w-56 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl py-1.5 z-30 text-xs">
                                            {{-- Cambiar Estado --}}
                                            @if($puedeActuar)
                                                <button type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 text-gray-700 dark:text-gray-200 font-medium btn-abrir-cambiar-estado"
                                                    data-id="{{ $trabajo->id }}"
                                                    data-titulo="{{ $trabajo->titulo }}"
                                                    data-estado="{{ $trabajo->estado }}"
                                                    data-asignado="{{ $trabajo->asignado_id }}">
                                                    {!! \App\Support\TrabajoRedIconos::svg('refrescar', 'w-3.5 h-3.5') !!}
                                                    <span>Cambiar estado...</span>
                                                </button>

                                                {{-- Asignar técnico --}}
                                                <button type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 text-gray-700 dark:text-gray-200 font-medium btn-abrir-asignar"
                                                    data-id="{{ $trabajo->id }}"
                                                    data-asignado="{{ $trabajo->asignado_id }}"
                                                    data-estado="{{ $trabajo->estado }}"
                                                    data-titulo="{{ $trabajo->titulo }}">
                                                    {!! \App\Support\TrabajoRedIconos::svg('user', 'w-3.5 h-3.5') !!}
                                                    <span>Reasignar técnico...</span>
                                                </button>
                                            @endif

                                            {{-- Ver Notas & Detalles --}}
                                            <button type="button"
                                                class="w-full text-left px-3.5 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2 text-gray-700 dark:text-gray-200 font-medium btn-ver-detalles"
                                                data-id="{{ $trabajo->id }}"
                                                data-titulo="{{ $trabajo->titulo }}"
                                                data-tipo="{{ $trabajo->etiquetaTipo() }}"
                                                data-estado="{{ $trabajo->etiquetaEstado() }}"
                                                data-prioridad="{{ $trabajo->etiquetaPrioridad() }}"
                                                data-nodo="{{ $trabajo->nodo?->descripcion ?? 'N/D' }}"
                                                data-activo="{{ $trabajo->etiquetaActivo() }}"
                                                data-descripcion="{{ $trabajo->descripcion ?? '' }}"
                                                data-observaciones="{{ $trabajo->observaciones }}">
                                                {!! \App\Support\TrabajoRedIconos::svg('documento', 'w-3.5 h-3.5') !!}
                                                <span>Ver bitácora & notas</span>
                                            </button>

                                            {{-- Despachar por WhatsApp --}}
                                            <a href="{{ $waUrl }}" target="_blank" rel="noopener"
                                                class="px-3.5 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-medium transition-colors">
                                                {!! \App\Support\TrabajoRedIconos::svg('chat', 'w-3.5 h-3.5') !!}
                                                <span>Despachar por WhatsApp</span>
                                            </a>

                                            {{-- Ver en Mapa / Ruta --}}
                                            @if($rutaMaps)
                                                <a href="{{ $rutaMaps }}" target="_blank" rel="noopener"
                                                    class="px-3.5 py-2 hover:bg-purple-50 dark:hover:bg-purple-950/40 flex items-center gap-2 text-purple-700 dark:text-purple-300 font-medium transition-colors">
                                                    {!! \App\Support\TrabajoRedIconos::svg('map', 'w-3.5 h-3.5') !!}
                                                    <span>Abrir ruta en Maps</span>
                                                </a>
                                            @endif

                                            {{-- Eliminar --}}
                                            @if($puedeEliminar)
                                                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                                <form action="{{ route('trabajos-red.destroy', $trabajo) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar el trabajo de red #{{ $trabajo->id }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full text-left px-3.5 py-2 hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center gap-2 text-red-600 dark:text-red-400 font-medium">
                                                        {!! \App\Support\TrabajoRedIconos::svg('trash', 'w-3.5 h-3.5') !!}
                                                        <span>Eliminar orden</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- PAGINACIÓN --}}
            <div class="px-4 py-3.5 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800 rounded-b-2xl">
                {{ $trabajos->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODALES MODERNIZADOS (PAQUETE C) --}}
{{-- ======================================================== --}}

{{-- 1. MODAL RESOLVER TRABAJO --}}
@if($puedeActuar)
<dialog id="modal-resolver" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-0 w-[min(30rem,calc(100vw-2rem))] shadow-2xl backdrop:bg-black/60">
    <div class="p-5 sm:p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Resolver Trabajo de Red</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400" id="modal-resolver-subtitulo">Orden #</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-resolver').close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-800 dark:text-emerald-300">
            El trabajo se marcará como <strong>Resuelto</strong> y se registrará la fecha y hora de cierre.
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Técnico que concluyó la tarea
            </label>
            <select id="modal-resolver-tecnico" class="w-full px-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                <option value="">Mantener actual</option>
                @foreach ($tecnicosAsignar as $t)
                    <option value="{{ $t->usuario_id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Nota de Resolución / Observaciones finales (opcional)
            </label>
            <textarea id="modal-resolver-nota" rows="3"
                placeholder="Ej. Fibra fusionada exitosamente en NAP-04. Niveles de potencia en -18.5 dBm..."
                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-resolver').close()"
                class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancelar
            </button>
            <button type="button" id="modal-resolver-btn-confirm"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-md shadow-emerald-600/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Confirmar y Resolver</span>
            </button>
        </div>
    </div>
</dialog>

{{-- 2. MODAL ASIGNAR TÉCNICO --}}
<dialog id="modal-asignar" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-0 w-[min(26rem,calc(100vw-2rem))] shadow-2xl backdrop:bg-black/60">
    <div class="p-5 sm:p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Asignar Cuadrilla / Técnico</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400" id="modal-asignar-subtitulo">Orden #</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-asignar').close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                Seleccione el técnico de campo
            </label>
            <select id="modal-asignar-select" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                <option value="">Sin asignar (desasignar)</option>
                @foreach ($tecnicosAsignar as $t)
                    <option value="{{ $t->usuario_id }}">{{ $t->name }} {{ $t->telefono ? '('.$t->telefono.')' : '' }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-asignar').close()"
                class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancelar
            </button>
            <button type="button" id="modal-asignar-ok"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold shadow-md shadow-purple-600/20">
                <span>Guardar Asignación</span>
            </button>
        </div>
    </div>
</dialog>

{{-- 3. MODAL CAMBIAR ESTADO RÁPIDO --}}
<dialog id="modal-cambiar-estado" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-0 w-[min(28rem,calc(100vw-2rem))] shadow-2xl backdrop:bg-black/60">
    <div class="p-5 sm:p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Cambiar Estado Operativo</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400" id="modal-cambiar-estado-subtitulo">Orden #</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-cambiar-estado').close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nuevo Estado *</label>
            <select id="modal-cambiar-estado-select" class="w-full px-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                @foreach (\App\Models\TrabajoRed::estados() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nota o bitácora de actualización (opcional)</label>
            <textarea id="modal-cambiar-estado-nota" rows="2"
                placeholder="Ej. Técnico se encuentra en camino al nodo..."
                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500"></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('modal-cambiar-estado').close()"
                class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancelar
            </button>
            <button type="button" id="modal-cambiar-estado-ok"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold shadow-md shadow-purple-600/20">
                <span>Guardar Estado</span>
            </button>
        </div>
    </div>
</dialog>
@endif

{{-- 4. MODAL DETALLES & BITÁCORA --}}
<dialog id="modal-detalles" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-0 w-[min(38rem,calc(100vw-2rem))] shadow-2xl backdrop:bg-black/60">
    <div class="p-5 sm:p-6 space-y-4">
        <div class="flex items-start justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
            <div>
                <span class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider" id="modal-detalles-tipo">Mantenimiento</span>
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 mt-0.5" id="modal-detalles-titulo">Detalles del Trabajo</h3>
                <div class="flex items-center gap-2 mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200" id="modal-detalles-estado">Pendiente</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300" id="modal-detalles-prioridad">Media</span>
                    <span class="text-xs text-gray-500" id="modal-detalles-nodo">Nodo: </span>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-detalles').close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Descripción --}}
        <div>
            <span class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Descripción del requerimiento</span>
            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 text-xs sm:text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line" id="modal-detalles-descripcion">
                Sin descripción.
            </div>
        </div>

        {{-- Bitácora / Observaciones --}}
        <div>
            <span class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Historial de Observaciones & Bitácora</span>
            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-750 text-xs sm:text-sm text-gray-800 dark:text-gray-200 max-h-48 overflow-y-auto whitespace-pre-line border border-gray-200 dark:border-gray-700" id="modal-detalles-observaciones">
                Sin observaciones registradas.
            </div>
        </div>

        @if($puedeActuar)
        {{-- Agregar Nota Rápida --}}
        <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Agregar nueva nota a la bitácora</label>
            <div class="flex gap-2">
                <input type="text" id="modal-detalles-nueva-nota" placeholder="Escribir avance o nota operativa..."
                    class="flex-1 px-3 py-2 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                <button type="button" id="modal-detalles-btn-guardar-nota"
                    class="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shrink-0 transition-colors">
                    Agregar
                </button>
            </div>
        </div>
        @endif

        <div class="flex justify-end pt-2">
            <button type="button" onclick="document.getElementById('modal-detalles').close()"
                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Cerrar
            </button>
        </div>
    </div>
</dialog>

{{-- 5. MODAL LIGHTBOX FOTO DE EVIDENCIA --}}
<dialog id="modal-foto" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-0 max-w-3xl shadow-2xl backdrop:bg-black/80">
    <div class="p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100" id="modal-foto-titulo">Foto de Evidencia</h4>
            <button type="button" onclick="document.getElementById('modal-foto').close()" class="text-gray-400 hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex justify-center bg-black/40 rounded-xl overflow-hidden max-h-[75vh]">
            <img id="modal-foto-img" src="" alt="Evidencia" class="max-h-[75vh] w-auto object-contain rounded-xl">
        </div>
        <div class="flex justify-between items-center pt-1 text-xs">
            <a id="modal-foto-link" href="" target="_blank" class="text-purple-600 dark:text-purple-400 hover:underline font-semibold flex items-center gap-1">
                <span>Abrir imagen original</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
            <button type="button" onclick="document.getElementById('modal-foto').close()" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-semibold">
                Cerrar
            </button>
        </div>
    </div>
</dialog>

{{-- SCRIPTS OPERATIVOS --}}
<script>
(function () {
    // 1. Despliegue de filtros
    var btnFiltros = document.getElementById('trabajo-filtros-btn');
    var menuFiltros = document.getElementById('trabajo-filtros-menu');
    var wrapFiltros = document.getElementById('trabajo-filtros-wrap');
    if (btnFiltros && menuFiltros) {
        function posicionarMenuFiltros() {
            if (menuFiltros.classList.contains('hidden')) return;
            var margin = 12;
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var btn = btnFiltros.getBoundingClientRect();
            var maxW = Math.min(576, Math.max(260, vw - margin * 2));
            var left = btn.right - maxW;
            if (left < margin) left = margin;
            if (left + maxW > vw - margin) left = Math.max(margin, vw - margin - maxW);
            var top = btn.bottom + 8;
            var maxH = Math.min(vh * 0.7, 640, vh - top - margin);
            if (maxH < 200) {
                maxH = Math.min(vh * 0.7, 640, btn.top - margin);
                top = Math.max(margin, btn.top - 8 - maxH);
            }
            menuFiltros.style.position = 'fixed';
            menuFiltros.style.zIndex = '60';
            menuFiltros.style.width = maxW + 'px';
            menuFiltros.style.left = left + 'px';
            menuFiltros.style.right = 'auto';
            menuFiltros.style.top = top + 'px';
            menuFiltros.style.maxHeight = Math.max(160, maxH) + 'px';
        }

        btnFiltros.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            menuFiltros.classList.toggle('hidden');
            posicionarMenuFiltros();
        });
        document.addEventListener('click', function (e) {
            if (wrapFiltros && !wrapFiltros.contains(e.target)) {
                menuFiltros.classList.add('hidden');
            }
        });
        window.addEventListener('resize', posicionarMenuFiltros);
        window.addEventListener('scroll', posicionarMenuFiltros, true);
    }

    // 2. Control de menús Kebab
    document.querySelectorAll('.btn-kebab').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var menu = btn.parentElement.querySelector('.dropdown-kebab-menu');
            document.querySelectorAll('.dropdown-kebab-menu').forEach(function (m) {
                if (m !== menu) m.classList.add('hidden');
            });
            if (menu) menu.classList.toggle('hidden');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-kebab-menu').forEach(function (m) {
            m.classList.add('hidden');
        });
    });

    // 3. Variables para llamadas AJAX
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    function patchTrabajo(id, data, exitoCallback) {
        return fetch('/trabajos-red/' + id + '/estado', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        }).then(function (res) {
            if (!res.ok) throw new Error('Error al actualizar el trabajo');
            return res.json();
        }).then(function (json) {
            if (exitoCallback) {
                exitoCallback(json);
            } else {
                window.location.reload();
            }
        }).catch(function (err) {
            alert('Error: ' + err.message);
        });
    }

    // 4. Modal Asignar Técnico
    var modalAsignar = document.getElementById('modal-asignar');
    var selectAsignar = document.getElementById('modal-asignar-select');
    var btnAsignarOk = document.getElementById('modal-asignar-ok');
    var subAsignar = document.getElementById('modal-asignar-subtitulo');
    var trabajoAsignarId = null;
    var trabajoAsignarEstado = 'pendiente';

    document.querySelectorAll('.btn-abrir-asignar').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            trabajoAsignarId = btn.getAttribute('data-id');
            trabajoAsignarEstado = btn.getAttribute('data-estado') || 'pendiente';
            var asignadoActual = btn.getAttribute('data-asignado') || '';
            var titulo = btn.getAttribute('data-titulo') || '';

            if (selectAsignar) selectAsignar.value = asignadoActual;
            if (subAsignar) subAsignar.textContent = 'Orden #' + trabajoAsignarId + ' · ' + titulo;
            if (modalAsignar && modalAsignar.showModal) modalAsignar.showModal();
        });
    });

    if (btnAsignarOk) {
        btnAsignarOk.addEventListener('click', function () {
            if (!trabajoAsignarId) return;
            var val = selectAsignar && selectAsignar.value ? parseInt(selectAsignar.value, 10) : null;
            btnAsignarOk.disabled = true;
            btnAsignarOk.textContent = 'Guardando...';
            patchTrabajo(trabajoAsignarId, { estado: trabajoAsignarEstado, asignado_id: val });
        });
    }

    // 5. Modal Resolver
    var modalResolver = document.getElementById('modal-resolver');
    var selectResolverTecnico = document.getElementById('modal-resolver-tecnico');
    var txtResolverNota = document.getElementById('modal-resolver-nota');
    var btnResolverConfirm = document.getElementById('modal-resolver-btn-confirm');
    var subResolver = document.getElementById('modal-resolver-subtitulo');
    var trabajoResolverId = null;

    document.querySelectorAll('.btn-abrir-resolver').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            trabajoResolverId = btn.getAttribute('data-id');
            var titulo = btn.getAttribute('data-titulo') || '';
            var asignadoId = btn.getAttribute('data-asignado-id') || '';

            if (subResolver) subResolver.textContent = 'Orden #' + trabajoResolverId + ' · ' + titulo;
            if (selectResolverTecnico) selectResolverTecnico.value = asignadoId;
            if (txtResolverNota) txtResolverNota.value = '';
            if (modalResolver && modalResolver.showModal) modalResolver.showModal();
        });
    });

    if (btnResolverConfirm) {
        btnResolverConfirm.addEventListener('click', function () {
            if (!trabajoResolverId) return;
            var payload = { estado: 'resuelto' };
            if (selectResolverTecnico && selectResolverTecnico.value) {
                payload.asignado_id = parseInt(selectResolverTecnico.value, 10);
            }
            if (txtResolverNota && txtResolverNota.value.trim() !== '') {
                payload.observaciones = txtResolverNota.value.trim();
            }
            btnResolverConfirm.disabled = true;
            btnResolverConfirm.textContent = 'Resolviendo...';
            patchTrabajo(trabajoResolverId, payload);
        });
    }

    // 6. Modal Cambiar Estado
    var modalCambiarEstado = document.getElementById('modal-cambiar-estado');
    var selectCambiarEstado = document.getElementById('modal-cambiar-estado-select');
    var txtCambiarEstadoNota = document.getElementById('modal-cambiar-estado-nota');
    var btnCambiarEstadoOk = document.getElementById('modal-cambiar-estado-ok');
    var subCambiarEstado = document.getElementById('modal-cambiar-estado-subtitulo');
    var trabajoCambiarEstadoId = null;
    var trabajoCambiarEstadoAsignado = null;

    document.querySelectorAll('.btn-abrir-cambiar-estado').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            trabajoCambiarEstadoId = btn.getAttribute('data-id');
            trabajoCambiarEstadoAsignado = btn.getAttribute('data-asignado');
            var estadoActual = btn.getAttribute('data-estado') || 'pendiente';
            var titulo = btn.getAttribute('data-titulo') || '';

            if (selectCambiarEstado) selectCambiarEstado.value = estadoActual;
            if (subCambiarEstado) subCambiarEstado.textContent = 'Orden #' + trabajoCambiarEstadoId + ' · ' + titulo;
            if (txtCambiarEstadoNota) txtCambiarEstadoNota.value = '';
            if (modalCambiarEstado && modalCambiarEstado.showModal) modalCambiarEstado.showModal();
        });
    });

    if (btnCambiarEstadoOk) {
        btnCambiarEstadoOk.addEventListener('click', function () {
            if (!trabajoCambiarEstadoId) return;
            var payload = {
                estado: selectCambiarEstado.value,
                asignado_id: trabajoCambiarEstadoAsignado ? parseInt(trabajoCambiarEstadoAsignado, 10) : null
            };
            if (txtCambiarEstadoNota && txtCambiarEstadoNota.value.trim() !== '') {
                payload.observaciones = txtCambiarEstadoNota.value.trim();
            }
            btnCambiarEstadoOk.disabled = true;
            btnCambiarEstadoOk.textContent = 'Guardando...';
            patchTrabajo(trabajoCambiarEstadoId, payload);
        });
    }

    // 7. Modal Detalles & Bitácora
    var modalDetalles = document.getElementById('modal-detalles');
    var detTipo = document.getElementById('modal-detalles-tipo');
    var detTitulo = document.getElementById('modal-detalles-titulo');
    var detEstado = document.getElementById('modal-detalles-estado');
    var detPrioridad = document.getElementById('modal-detalles-prioridad');
    var detNodo = document.getElementById('modal-detalles-nodo');
    var detDesc = document.getElementById('modal-detalles-descripcion');
    var detObs = document.getElementById('modal-detalles-observaciones');
    var detNuevaNota = document.getElementById('modal-detalles-nueva-nota');
    var detBtnGuardarNota = document.getElementById('modal-detalles-btn-guardar-nota');
    var trabajoDetallesId = null;

    document.querySelectorAll('.btn-ver-detalles').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            trabajoDetallesId = btn.getAttribute('data-id');
            if (detTipo) detTipo.textContent = btn.getAttribute('data-tipo') || '';
            if (detTitulo) detTitulo.textContent = '#' + trabajoDetallesId + ' - ' + (btn.getAttribute('data-titulo') || '');
            if (detEstado) detEstado.textContent = btn.getAttribute('data-estado') || '';
            if (detPrioridad) detPrioridad.textContent = 'Prioridad: ' + (btn.getAttribute('data-prioridad') || '');
            if (detNodo) detNodo.textContent = 'Nodo: ' + (btn.getAttribute('data-nodo') || 'N/D') + ' · Activo: ' + (btn.getAttribute('data-activo') || '');
            if (detDesc) detDesc.textContent = btn.getAttribute('data-descripcion') || 'Sin descripción detallada.';
            if (detObs) detObs.textContent = btn.getAttribute('data-observaciones') || 'Sin observaciones registradas.';
            if (detNuevaNota) detNuevaNota.value = '';

            if (modalDetalles && modalDetalles.showModal) modalDetalles.showModal();
        });
    });

    if (detBtnGuardarNota) {
        detBtnGuardarNota.addEventListener('click', function () {
            if (!trabajoDetallesId || !detNuevaNota || !detNuevaNota.value.trim()) return;
            detBtnGuardarNota.disabled = true;
            detBtnGuardarNota.textContent = 'Guardando...';
            patchTrabajo(trabajoDetallesId, {
                estado: detEstado ? detEstado.textContent.toLowerCase().trim() : 'pendiente',
                observaciones: detNuevaNota.value.trim()
            }, function (json) {
                if (detObs && json.observaciones) {
                    detObs.textContent = json.observaciones;
                }
                detNuevaNota.value = '';
                detBtnGuardarNota.disabled = false;
                detBtnGuardarNota.textContent = 'Agregar';
            });
        });
    }

    // 8. Modal Lightbox Foto
    var modalFoto = document.getElementById('modal-foto');
    var imgFoto = document.getElementById('modal-foto-img');
    var linkFoto = document.getElementById('modal-foto-link');
    var tituloFoto = document.getElementById('modal-foto-titulo');

    document.querySelectorAll('.btn-ver-foto').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var src = btn.getAttribute('data-foto');
            var titulo = btn.getAttribute('data-titulo') || 'Evidencia de Trabajo';
            if (imgFoto) imgFoto.src = src;
            if (linkFoto) linkFoto.href = src;
            if (tituloFoto) tituloFoto.textContent = titulo;
            if (modalFoto && modalFoto.showModal) modalFoto.showModal();
        });
    });
})();
</script>
@endsection
