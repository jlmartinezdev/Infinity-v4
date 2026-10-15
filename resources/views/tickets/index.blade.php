@extends('layouts.app')

@section('title', 'Tickets')

@push('styles')
<style>
    mark.search-mark {
        background-color: #ddd6fe;
        color: #3b0764;
        padding: 0 0.12em;
        border-radius: 0.5rem;
        box-decoration-break: clone;
        -webkit-box-decoration-break: clone;
    }
    .dark mark.search-mark {
        background-color: #5b21b6;
        color: #f5f3ff;
    }
    .ticket-col-cliente a:hover,
    .ticket-col-cliente a:focus-visible {
        text-decoration-line: underline;
        text-underline-offset: 2px;
    }
    .ticket-mas-colas > summary { list-style: none; }
    .ticket-mas-colas > summary::-webkit-details-marker { display: none; }
    .ticket-mas-colas-panel {
        position: absolute;
        left: 0;
        z-index: 30;
        margin-top: 0.25rem;
        width: min(18rem, calc(100vw - 2rem));
        max-height: 20rem;
        overflow-y: auto;
    }
    @media (max-width: 767px) {
        .ticket-mas-colas-panel {
            position: fixed;
            left: 1rem;
            right: 1rem;
            width: auto;
        }
        .ticket-row {
            padding: 0.75rem;
        }
        .ticket-row-grid {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                "identidad overflow"
                "cliente cliente"
                "tecnico tecnico"
                "acciones acciones";
            gap: 0.375rem 0.5rem;
            align-items: start;
        }
        .ticket-col-acciones-wrap {
            display: contents;
        }
        .ticket-col-identidad { grid-area: identidad; align-items: flex-start; }
        .ticket-col-overflow { grid-area: overflow; align-self: start; }
        .ticket-col-cliente { grid-area: cliente; }
        .ticket-col-tecnico {
            grid-area: tecnico;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.375rem 0.75rem;
            justify-self: stretch;
            text-align: left;
        }
        .ticket-col-tecnico > p {
            margin-top: 0;
        }
        .ticket-col-acciones {
            grid-area: acciones;
            justify-content: stretch !important;
            flex-wrap: nowrap;
            padding-top: 0.5rem;
            margin-top: 0.125rem;
            border-top: 1px solid #e5e7eb;
        }
        .dark .ticket-col-acciones {
            border-top-color: #4b5563;
        }
        .ticket-col-acciones > .btn-asignar-fila,
        .ticket-col-acciones > .btn-quick-resolver {
            flex: 1 1 0;
            justify-content: center;
        }
        .ticket-col-identidad .truncate {
            overflow: hidden;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .ticket-icono > span {
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
        }
        .ticket-icono svg {
            width: 1.125rem;
            height: 1.125rem;
        }
        .ticket-meta-contacto a {
            height: 1.75rem;
            width: 1.75rem;
            border-color: transparent;
            background: transparent;
        }
        .ticket-col-acciones:not(:has(button)) {
            display: none;
        }
        .ticket-meta-fecha-absoluta { display: none; }
        .ticket-col-tecnico .btn-asignar-rapido { display: none; }
        .ticket-sin-tecnico-movil { display: inline-flex; }
        .ticket-cliente-icono,
        .ticket-linea-via { display: none; }
    }
    .ticket-sin-tecnico-movil { display: none; }
    .swal-tab-btn.active {
        border-bottom-color: #9333ea;
        color: #9333ea;
    }
    .dark .swal-tab-btn.active {
        border-bottom-color: #c084fc;
        color: #c084fc;
    }
</style>
@endpush

@section('content')
@php
    $busqueda = $busqueda ?? '';
    $clienteFiltro = $clienteFiltro ?? null;
    $cola = $cola ?? request('cola', '');
    $ocultaCerrados = request()->boolean('ocultar_resuelto_cerrado');
    $cantidadFiltrosPanel = (int) (
        request()->filled('estado')
        + request()->filled('ticket_asunto_id')
        + request()->filled('asignado_id')
        + request()->filled('prioridad')
        + request()->boolean('sin_asignar')
    );
    $resaltar = fn (?string $texto): string => \App\Support\SearchHighlight::html($texto, $busqueda);
    $mapsUrlCliente = fn (?\App\Models\Cliente $cliente): ?string => \App\Helpers\MapsUrlHelper::toGoogleMapsUrl($cliente?->url_ubicacion);
    $etiquetaAntiguedad = function (?\Illuminate\Support\Carbon $fecha): string {
        if (! $fecha) {
            return '';
        }
        $dias = (int) $fecha->copy()->startOfDay()->diffInDays(now()->startOfDay());

        return match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => 'hace 1 día',
            default => 'hace '.$dias.' días',
        };
    };
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    {{-- Header principal --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Tickets de soporte</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrl() }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Recargar esta misma lista">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span class="hidden sm:inline">Actualizar</span>
            </a>
            <a href="{{ route('tickets.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:bg-purple-600 dark:hover:bg-purple-500 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo ticket</span>
            </a>
        </div>
    </div>

    {{-- Paquete A: Segmented Queue Tabs (1-Click) --}}
    @php
        $tabActiva = match(true) {
            $cola === 'en_curso' => 'en_curso',
            $cola === 'abiertos' => 'abiertos',
            $cola === 'pendientes' || $cola === 'pendiente' || request('estado') === 'pendiente' => 'pendientes',
            $cola === 'en_camino' || request('estado') === 'en_camino' => 'en_camino',
            $cola === 'en_proceso' || request('estado') === 'en_proceso' => 'en_proceso',
            $cola === 'alta' => 'alta',
            $cola === 'sin_asignar' => 'sin_asignar',
            $cola === 'resueltos' || request('estado') === 'resuelto' => 'resueltos',
            default => ($ocultaCerrados && ! request()->filled('estado') ? 'en_curso' : 'todos'),
        };
        $linkTab = fn ($c) => route('tickets.index', array_merge(request()->except(['cola', 'page', 'estado']), $c ? ['cola' => $c] : []));
        $linkEnCurso = route('tickets.index', array_merge(request()->except(['cola', 'page', 'estado', 'ocultar_resuelto_cerrado']), ['cola' => 'en_curso']));
        $linkTodos = route('tickets.index', request()->except(['cola', 'page', 'estado', 'ocultar_resuelto_cerrado']));
        $fraseLista = match ($tabActiva) {
            'en_curso' => 'en curso',
            'abiertos' => 'abiertos',
            'pendientes' => 'pendientes',
            'en_camino' => 'en camino',
            'en_proceso' => 'en proceso',
            'alta' => 'de prioridad alta',
            'sin_asignar' => 'sin técnico',
            'resueltos' => 'resueltos',
            default => 'en total',
        };
        $urlSinOcultar = route('tickets.index', request()->except(['ocultar_resuelto_cerrado', 'page']));
        $chipsCola = [
            ['id' => 'en_curso', 'label' => 'En curso', 'n' => $kpis->en_curso ?? 0, 'href' => $linkEnCurso, 'extra' => ''],
            ['id' => 'abiertos', 'label' => 'Abiertos', 'n' => $kpis->abiertos ?? 0, 'href' => $linkTab('abiertos'), 'extra' => ''],
            ['id' => 'pendientes', 'label' => 'Pendientes', 'n' => $kpis->pendientes ?? 0, 'href' => $linkTab('pendientes'), 'extra' => ''],
            ['id' => 'en_camino', 'label' => 'En camino', 'n' => $kpis->en_camino ?? 0, 'href' => $linkTab('en_camino'), 'extra' => ''],
            ['id' => 'en_proceso', 'label' => 'En proceso', 'n' => $kpis->en_proceso ?? 0, 'href' => $linkTab('en_proceso'), 'extra' => ''],
            ['id' => 'alta', 'label' => 'Prioridad alta', 'n' => $kpis->alta ?? 0, 'href' => $linkTab('alta'), 'extra' => ''],
            ['id' => 'sin_asignar', 'label' => 'Sin técnico', 'n' => $kpis->sin_asignar ?? 0, 'href' => $linkTab('sin_asignar'), 'extra' => ''],
            ['id' => 'resueltos', 'label' => 'Resueltos', 'n' => $kpis->resueltos ?? 0, 'href' => $linkTab('resueltos'), 'extra' => ''],
            ['id' => 'todos', 'label' => 'Todos', 'n' => $kpis->total ?? 0, 'href' => $linkTodos, 'extra' => 'js-quitar-ocultar'],
        ];
        $chipClase = function (bool $activo): string {
            $base = 'inline-flex items-center gap-1.5 min-h-11 px-3 rounded-lg text-sm font-semibold shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900';
            return $base.($activo
                ? ' bg-purple-600 text-white'
                : ' bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700');
        };
        $chipConteo = function (bool $activo): string {
            return $activo
                ? 'bg-white/20 text-white'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100';
        };
        $idsFijos = ['en_curso', 'en_camino', 'alta', 'sin_asignar'];
        $chipsPorId = [];
        foreach ($chipsCola as $chipCola) {
            $chipsPorId[$chipCola['id']] = $chipCola;
        }
        $chipsVisibles = [];
        if (isset($chipsPorId[$tabActiva]) && ! in_array($tabActiva, $idsFijos, true)) {
            $chipsVisibles[] = $chipsPorId[$tabActiva];
        }
        foreach ($idsFijos as $idFijo) {
            $chipsVisibles[] = $chipsPorId[$idFijo];
        }
        $idsVisibles = array_column($chipsVisibles, 'id');
        $idsFueraDeMas = ['abiertos', 'pendientes'];
        $chipsMas = array_values(array_filter(
            $chipsCola,
            fn ($chipCola) => ! in_array($chipCola['id'], $idsVisibles, true) && ! in_array($chipCola['id'], $idsFueraDeMas, true)
        ));
    @endphp
    <div class="flex flex-wrap items-center gap-1.5 min-w-0">
        @foreach ($chipsVisibles as $chip)
            @php $etiquetaChip = $chip['id'] === 'alta' ? 'Alta' : $chip['label']; @endphp
            <a href="{{ $chip['href'] }}" @if($chip['id'] === $tabActiva) aria-current="page" @endif class="{{ trim($chip['extra'].' '.$chipClase($chip['id'] === $tabActiva)) }}">
                <span>{{ $etiquetaChip }}</span>
                <span data-chip-count="{{ $chip['id'] }}" class="inline-flex items-center justify-center min-w-5 px-1.5 rounded-full text-xs {{ $chipConteo($chip['id'] === $tabActiva) }}">{{ $chip['n'] }}</span>
            </a>
        @endforeach
        @if(count($chipsMas) > 0)
            <details class="ticket-mas-colas relative shrink-0">
                <summary class="{{ $chipClase(false) }} cursor-pointer" aria-label="Más colas">Más colas</summary>
                <div class="ticket-mas-colas-panel rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1">
                    @foreach ($chipsMas as $chip)
                        <a href="{{ $chip['href'] }}" @if($chip['id'] === $tabActiva) aria-current="page" @endif class="{{ trim($chip['extra'].' flex items-center justify-between gap-2 min-h-11 px-3 rounded-lg text-sm font-semibold text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700') }}">
                            <span>{{ $chip['label'] }}</span>
                            <span data-chip-count="{{ $chip['id'] }}" class="inline-flex items-center justify-center min-w-5 px-1.5 rounded-full text-xs {{ $chipConteo($chip['id'] === $tabActiva) }}">{{ $chip['n'] }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endif
    </div>

    {{-- Contenedor principal de lista y filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        {{-- Barra de búsqueda y selector de filtros --}}
        <form method="GET" action="{{ route('tickets.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-t-lg">
            @if($clienteFiltro && $busqueda === '')
                <input type="hidden" name="cliente_id" value="{{ $clienteFiltro->cliente_id }}">
            @endif
            @if($cola)
                <input type="hidden" name="cola" value="{{ $cola }}">
            @endif
            <input type="hidden" name="per_page" value="{{ $tickets->perPage() }}">

            <div class="flex items-center gap-3">
                {{-- Campo de búsqueda rápida --}}
                <div class="flex items-center flex-1 min-w-0 min-h-[2.75rem] sm:min-h-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-500/20 shadow-sm">
                    <span class="pl-3 flex items-center shrink-0 text-gray-400 dark:text-gray-500 pointer-events-none" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                        </svg>
                    </span>
                    <input type="search" name="q" id="ticket-busqueda" value="{{ $busqueda }}"
                        placeholder="Buscar por cliente, cédula, IP, asunto, técnico o #ticket..."
                        aria-label="Buscar tickets"
                        autocomplete="off"
                        enterkeyhint="search"
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2 pr-3 py-2.5 sm:py-2 text-base sm:text-sm leading-normal focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 [appearance:textfield] [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden">
                </div>

                {{-- Botón y Menú de Filtros avanzados --}}
                <div class="relative shrink-0" id="ticket-filtros-wrap">
                    <button
                        type="button"
                        id="ticket-filtros-btn"
                        class="relative inline-flex items-center gap-2 h-full px-4 py-2.5 rounded-lg border font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/20 shadow-sm {{ $cantidadFiltrosPanel ? 'border-purple-600 bg-purple-600 text-white hover:bg-purple-700' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                        aria-expanded="false"
                        aria-controls="ticket-filtros-menu"
                        title="Filtros avanzados"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span class="hidden sm:inline">Filtros</span>
                        @if($cantidadFiltrosPanel)
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold bg-white text-purple-700">{{ $cantidadFiltrosPanel }}</span>
                        @endif
                    </button>
                    <div
                        id="ticket-filtros-menu"
                        class="hidden absolute right-0 mt-2 w-80 max-w-sm py-3 px-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 z-30"
                    >
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Filtros avanzados</p>
                            @if($cantidadFiltrosPanel || $busqueda !== '' || $clienteFiltro || $cola)
                                <a href="{{ route('tickets.index') }}" class="text-xs text-purple-600 dark:text-purple-400 hover:underline font-medium">Limpiar todo</a>
                            @endif
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
                                <select name="estado" aria-label="Estado"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos los estados</option>
                                    @foreach (App\Models\Ticket::estados() as $key => $label)
                                        <option value="{{ $key }}" {{ request('estado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Prioridad</label>
                                <select name="prioridad" aria-label="Prioridad"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todas las prioridades</option>
                                    @foreach (App\Models\Ticket::prioridades() as $key => $label)
                                        <option value="{{ $key }}" {{ request('prioridad') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Asunto</label>
                                <select name="ticket_asunto_id" aria-label="Asunto"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos los asuntos</option>
                                    @foreach ($asuntos as $a)
                                        <option value="{{ $a->id }}" {{ request('ticket_asunto_id') == $a->id ? 'selected' : '' }}>{{ $a->getIconoOrDefault() }} {{ $a->nombre }}@if((int) $a->pendientes_count > 0) · {{ $a->pendientes_count }}@endif</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">El número es de tickets en Pendiente.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Técnico (histórico)</label>
                                <select name="asignado_id" aria-label="Técnico histórico"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos los técnicos</option>
                                    @foreach ($tecnicos as $t)
                                        <option value="{{ $t->usuario_id }}" {{ (string) request('asignado_id') === (string) $t->usuario_id ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Incluye a quien ya no sale a la calle.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300 select-none">
                                <input type="checkbox" name="sin_asignar" value="1" {{ request()->boolean('sin_asignar') ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 dark:bg-gray-700">
                                <span>Solo tickets sin técnico</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300 select-none">
                                <input type="checkbox" name="ocultar_resuelto_cerrado" value="1" {{ request()->boolean('ocultar_resuelto_cerrado') ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 dark:bg-gray-700">
                                <span>Ocultar resuelto y cerrado</span>
                            </label>
                            <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors shadow-sm">Aplicar filtros</button>
                        </div>
                    </div>
                </div>
            </div>

            <p id="tickets-resumen" class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span><span id="tickets-resumen-conteo">{{ $tickets->total() }}</span> {{ $fraseLista }}</span>
                @if($tabActiva === 'en_curso')
                    @php
                        $nNoRealizados = (int) ($kpis->no_realizados ?? 0);
                        $nSinTecnico = (int) ($kpis->sin_asignar ?? 0);
                    @endphp
                    <span>· <span id="tickets-resumen-sin-tecnico">{{ $nSinTecnico }}</span> sin técnico</span>
                    <span>· <span id="tickets-resumen-no-realizados">{{ $nNoRealizados }}</span> <span id="tickets-resumen-no-realizados-palabra">{{ $nNoRealizados === 1 ? 'no realizado' : 'no realizados' }}</span></span>
                @endif
                @if($ocultaCerrados && $tabActiva !== 'en_curso')
                    <a href="{{ $urlSinOcultar }}" class="js-quitar-ocultar inline-flex items-center gap-1 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        Sin resueltos ni cerrados
                        <span aria-hidden="true">×</span>
                        <span class="sr-only">Quitar filtro</span>
                    </a>
                @endif
            </p>

            @if($clienteFiltro && $busqueda === '')
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Cliente: <span class="font-medium text-gray-900 dark:text-gray-100">{{ trim($clienteFiltro->nombre.' '.($clienteFiltro->apellido ?? '')) }}</span>
                    · <a href="{{ route('tickets.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todos</a>
                </p>
            @endif
            @if(request()->filled('asignado_id'))
                @php $tecnicoFiltro = ($tecnicos ?? collect())->firstWhere('usuario_id', (int) request('asignado_id')); @endphp
                @if($tecnicoFiltro)
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        Técnico: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $tecnicoFiltro->name }}</span>
                        · <a href="{{ route('tickets.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todos</a>
                    </p>
                @endif
            @endif
        </form>

        {{-- Paquete B: Lista de Filas Ergonómicas con Prioridad y Técnico Visibles --}}
        <div class="divide-y divide-gray-200 dark:divide-gray-700" id="tickets-lista-container" data-tab="{{ $tabActiva }}">
            @forelse ($tickets as $ticket)
                @php
                    $servicioConIp = $ticket->cliente
                        ? $ticket->cliente->servicios
                            ->filter(fn ($s) => filled($s->ip))
                            ->sortBy(fn ($s) => $s->estado === \App\Models\Servicio::ESTADO_ACTIVO ? 0 : 1)
                            ->first()
                        : null;
                    $ipClienteTicket = $servicioConIp?->ip;
                    $nombreClienteTicket = $ticket->cliente
                        ? trim($ticket->cliente->nombre.' '.($ticket->cliente->apellido ?? ''))
                        : '';
                    $detalleFila = \App\Support\TicketNotaLista::paraLista($ticket->descripcion ?: $ticket->observaciones);
                    $lineaFila = \Illuminate\Support\Str::limit($detalleFila, 160);
                    $estados = App\Models\Ticket::estados();
                    $prioridades = App\Models\Ticket::prioridades();
                    $reportado = App\Models\Ticket::reportadoDesdeOpciones();
                    $viaTicket = $reportado[$ticket->reportado_desde] ?? null;
                    $claseEstadoFila = match ($ticket->estado) {
                        'resuelto', 'cerrado' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
                        'cancelado' => 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300',
                        'en_proceso' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300',
                        'en_camino' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-300',
                        default => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
                    };
                    $esActivo = ! in_array($ticket->estado, ['resuelto', 'cerrado', 'cancelado'], true);
                    $mapsUrl = $mapsUrlCliente($ticket->cliente);
                    if (! $mapsUrl && $ticket->pedido) {
                        $mapsUrl = \App\Helpers\MapsUrlHelper::toGoogleMapsUrl(
                            $ticket->pedido->maps_gps,
                            $ticket->pedido->lat !== null ? (float) $ticket->pedido->lat : null,
                            $ticket->pedido->lon !== null ? (float) $ticket->pedido->lon : null
                        );
                    }
                    $nodoTicket = null;
                    if ($ticket->cliente?->servicios) {
                        $nodoTicket = $ticket->cliente->servicios
                            ->filter(fn ($s) => $s->estado !== \App\Models\Servicio::ESTADO_CANCELADO)
                            ->sortBy(fn ($s) => $s->estado === \App\Models\Servicio::ESTADO_ACTIVO ? 0 : 1)
                            ->map(fn ($s) => $s->pool?->router?->nodo?->descripcion
                                ?? $s->cajaNapPuertoActivo?->cajaNap?->nodo?->descripcion)
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(' · ') ?: null;
                    }
                    $canServicioCrear = auth()->user()?->tienePermiso('servicios.crear') ?? false;
                    $canServiciosVer = auth()->user()?->tienePermiso('servicios.ver') ?? false;
                    $canFacturaInternaCrear = auth()->user()?->tienePermiso('factura-interna.crear') ?? false;
                    $serviciosCliente = $ticket->cliente?->servicios ?? collect();
                    $servicioMigrar = $canServicioCrear
                        ? $serviciosCliente->first(fn ($s) => $s->pool?->router)
                        : null;

                    $detalleTicket = [
                        'id' => $ticket->id,
                        'asunto' => $ticket->ticketAsunto?->nombre,
                        'asunto_icono' => $ticket->ticketAsunto?->getIconoOrDefault(),
                        'asunto_badge_style' => $ticket->ticketAsunto?->badgeStyle(),
                        'cliente' => $nombreClienteTicket !== '' ? $nombreClienteTicket : null,
                        'cliente_cedula' => $ticket->cliente?->cedula,
                        'cliente_telefono' => $ticket->cliente?->telefono,
                        'ip_cliente' => $ipClienteTicket,
                        'pedido_id' => $ticket->pedido_id,
                        'descripcion' => \App\Support\TicketNotaLista::paraDetalle($ticket->descripcion),
                        'observaciones' => \App\Support\TicketNotaLista::paraDetalle($ticket->observaciones),
                        'nota_tecnico' => \App\Support\TicketNotaLista::paraDetalle($ticket->nota_tecnico),
                        'diagnostico_vista' => (new \App\Support\TicketDiagnosticoPresenter($ticket->datos_diagnostico))->secciones(),
                        'estado' => $estados[$ticket->estado] ?? $ticket->estado,
                        'prioridad' => $prioridades[$ticket->prioridad ?? 'media'] ?? $ticket->prioridad,
                        'reportado' => $ticket->reportado_desde ? ($reportado[$ticket->reportado_desde] ?? $ticket->reportado_desde) : null,
                        'creador' => $ticket->usuario?->name,
                        'asignado' => $ticket->asignado?->name,
                        'factura_interna_id' => $ticket->factura_interna_id,
                        'monto_cobro_ticket' => $ticket->monto_cobro_ticket ? number_format((float) $ticket->monto_cobro_ticket, 0, ',', '.').' Gs.' : null,
                        'factura_url' => $ticket->factura_interna_id ? route('factura-internas.show', $ticket->factura_interna_id) : null,
                        'imagen_url' => $ticket->imagen ? asset('storage/'.$ticket->imagen) : null,
                        'created_at' => $ticket->created_at?->format('d/m/Y H:i'),
                        'updated_at' => $ticket->updated_at?->format('d/m/Y H:i'),
                        'fecha_cierre' => $ticket->fecha_cierre?->format('d/m/Y H:i'),
                    ];

                    $menuCfg = [
                        'estado' => $ticket->estado,
                        'asignado_id' => $ticket->asignado_id,
                        'asignado_nombre' => $ticket->asignado?->name,
                        'ticket_id' => $ticket->id,
                        'cliente' => $nombreClienteTicket !== '' ? $nombreClienteTicket : null,
                        'asunto' => $ticket->ticketAsunto?->nombre,
                        'nota' => $lineaFila,
                        'update_estado_url' => route('tickets.update-estado', $ticket),
                        'edit_ticket_url' => route('tickets.edit', $ticket),
                        'agenda_url' => route('tickets.crear-agenda', $ticket),
                        'destroy_url' => route('tickets.destroy', $ticket),
                        'csrf' => csrf_token(),
                        'imagen_url' => $ticket->imagen ? asset('storage/'.$ticket->imagen) : null,
                        'puede_marcar_resuelto' => $esActivo,
                        'migrar_url' => $servicioMigrar ? route('servicios.migrar', $servicioMigrar) : null,
                        'herramientas_red_url' => ($canServiciosVer && $servicioConIp)
                            ? route('servicios.herramientas-red', $servicioConIp).'?ticket_id='.$ticket->id
                            : null,
                        'puede_facturar_ticket' => $canFacturaInternaCrear && $ticket->cliente_id && ! $ticket->factura_interna_id,
                        'facturar_url' => $canFacturaInternaCrear ? route('tickets.facturar', $ticket) : '',
                        'asignar_en_fila' => $esActivo && ! $ticket->asignado,
                    ];
                @endphp

                <article class="ticket-row p-3.5 sm:p-4 transition-colors {{ $loop->even ? 'bg-gray-50/70 dark:bg-gray-800/40' : 'bg-white dark:bg-gray-800' }} hover:bg-gray-100/80 dark:hover:bg-gray-700/50" data-id="{{ $ticket->id }}" data-antiguedad="{{ $etiquetaAntiguedad($ticket->created_at) }}">
                    <div class="ticket-row-grid grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        {{-- Columna 1: ID + Asunto + Badges de Estado y Prioridad --}}
                        <div class="ticket-col-identidad md:col-span-3 min-w-0 flex items-center gap-3">
                            <div class="ticket-icono shrink-0">
                                @if($ticket->ticketAsunto)
                                    <x-asunto-icono :asunto="$ticket->ticketAsunto" size="md" class="shadow-xs" />
                                @else
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-700/60 text-gray-400 border border-gray-200 dark:border-gray-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    </span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" title="{{ $ticket->ticketAsunto?->nombre ?? '—' }}">
                                        {!! $resaltar($ticket->ticketAsunto?->nombre ?? '—') !!}
                                    </span>
                                </div>

                                <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                    <span data-estado-badge class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $claseEstadoFila }}">{{ $estados[$ticket->estado] ?? $ticket->estado }}</span>
                                    <span class="ticket-meta-fecha text-xs">
                                        <span class="ticket-meta-fecha-absoluta font-normal text-gray-500 dark:text-gray-400">{{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                        @if($ticket->created_at)
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $etiquetaAntiguedad($ticket->created_at) }}</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Columna 2: Cliente + nodo + mapa; CI/tel ocultos --}}
                        <div class="ticket-col-cliente md:col-span-3 min-w-0">
                            <div class="flex items-start gap-2">
                                <svg class="ticket-cliente-icono w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <div class="min-w-0">
                                    @if($ticket->cliente)
                                        <a href="{{ route('clientes.detalle', $ticket->cliente) }}" class="inline-block text-sm font-semibold text-gray-900 dark:text-gray-100 underline-offset-2 hover:underline focus-visible:underline truncate max-w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 rounded-sm" title="Ver ficha del cliente">{!! $resaltar($nombreClienteTicket) !!}</a>
                                        @if($mapsUrl || $nodoTicket)
                                            <div class="ticket-meta-contacto flex items-center gap-1.5 flex-wrap text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                @if($mapsUrl)
                                                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                                                       class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 hover:bg-sky-50 dark:hover:bg-sky-950/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                                                       title="Abrir mapa"
                                                       aria-label="Abrir mapa de {{ $nombreClienteTicket }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        </svg>
                                                    </a>
                                                @endif
                                                @if($nodoTicket)
                                                    <span class="min-w-0 truncate" title="Nodo: {{ $nodoTicket }}">Nodo {{ $nodoTicket }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">—</p>
                                    @endif

                                    @if($ticket->pedido_id)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Pedido #{!! $resaltar((string) $ticket->pedido_id) !!}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Columna 3: Técnico Asignado + Fecha y Antigüedad --}}
                        <div class="ticket-col-tecnico md:col-span-3 min-w-0">
                            {{-- Técnico Asignado Chip directo --}}
                            <div data-tecnico-slot>
                                @if($ticket->asignado)
                                    <span class="inline-flex items-start gap-1.5 max-w-full px-2 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700/60 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600" title="{{ $ticket->asignado->name }}">
                                        <svg class="w-3.5 h-3.5 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span class="min-w-0 break-words whitespace-normal leading-snug">{!! $resaltar($ticket->asignado->name) !!}</span>
                                    </span>
                                @else
                                    <button type="button" class="btn-asignar-rapido inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-200 dark:border-orange-800 hover:bg-orange-200 transition-colors"
                                        title="Asignar técnico"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-url="{{ route('tickets.update-estado', $ticket) }}"
                                        data-estado="{{ $ticket->estado }}"
                                        data-cliente="{{ $nombreClienteTicket }}"
                                        data-asunto="{{ $ticket->ticketAsunto?->nombre ?? '' }}">
                                        <span>Sin técnico</span>
                                    </button>
                                    <span class="ticket-sin-tecnico-movil items-center px-2 py-1 rounded-md text-xs font-semibold bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-200 dark:border-orange-800">Sin técnico</span>
                                @endif
                            </div>
                            <p class="mt-1.5 text-xs {{ ($ticket->prioridad ?? 'media') === 'alta' ? 'font-semibold text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-300' }}">Prioridad {{ $prioridades[$ticket->prioridad ?? 'media'] ?? ($ticket->prioridad ?? 'Media') }}</p>
                        </div>

                        {{-- Acciones: Resolver/Asignar + menú; en móvil el menú sube a la cabecera --}}
                        <div class="ticket-col-acciones-wrap md:col-span-3 flex items-center justify-end gap-1 flex-wrap">
                            <div class="ticket-col-acciones flex items-center justify-end gap-1 flex-wrap min-w-0 flex-1 md:flex-initial">
                                @if($esActivo && ! $ticket->asignado)
                                    <button type="button"
                                        class="btn-asignar-rapido btn-asignar-fila inline-flex items-center min-h-11 px-3 rounded-lg text-sm font-medium text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                                        data-url="{{ route('tickets.update-estado', $ticket) }}"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-estado="{{ $ticket->estado }}"
                                        data-cliente="{{ $nombreClienteTicket }}"
                                        data-asunto="{{ $ticket->ticketAsunto?->nombre ?? '' }}">
                                        Asignar
                                    </button>
                                @endif
                                @if($esActivo)
                                    <button type="button"
                                        class="btn-quick-resolver inline-flex items-center gap-1.5 min-h-11 px-3 rounded-lg text-sm font-medium text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                                        data-url="{{ route('tickets.update-estado', $ticket) }}"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-estado="{{ $ticket->estado }}"
                                        data-cliente="{{ $nombreClienteTicket }}"
                                        data-asunto="{{ $ticket->ticketAsunto?->nombre ?? '' }}"
                                        data-nota="{{ $lineaFila }}"
                                        data-via="{{ $viaTicket }}"
                                        data-sin-tecnico="{{ $ticket->asignado ? '0' : '1' }}"
                                        data-estado-etiqueta="{{ $estados[$ticket->estado] ?? $ticket->estado }}"
                                        data-tecnico="{{ $ticket->asignado?->name ?? '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Resolver
                                    </button>
                                @endif
                            </div>
                            <div class="ticket-col-overflow inline-flex items-center">
                                <button type="button"
                                    class="ticket-acciones-kebab inline-flex items-center justify-center min-h-11 min-w-11 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                                    title="Más opciones"
                                    aria-label="Menú de acciones del ticket #{{ $ticket->id }}"
                                    aria-expanded="false"
                                    data-menu-b64="{{ base64_encode(json_encode($menuCfg, JSON_UNESCAPED_UNICODE)) }}">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                </button>
                                <button type="button"
                                    class="ticket-expandir-btn inline-flex items-center justify-center min-h-11 min-w-11 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                                    title="Más detalles"
                                    aria-label="Mostrar más detalles del ticket #{{ $ticket->id }}"
                                    aria-expanded="false"
                                    aria-controls="ticket-extra-{{ $ticket->id }}">
                                    <svg class="ticket-expandir-icon w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Acordeón de datos secundarios colapsables --}}
                    <div id="ticket-extra-{{ $ticket->id }}" class="hidden mt-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-3 grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        @if($detalleFila !== '')
                            <div class="col-span-2 lg:col-span-4">
                                <p class="font-medium uppercase tracking-wide text-gray-400">Detalle</p>
                                <p class="mt-1 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words">{{ $detalleFila }}</p>
                            </div>
                        @endif
                        <div class="col-span-2 lg:col-span-4 flex flex-wrap gap-2">
                            @if($ipClienteTicket)
                                <a href="http://{{ $ipClienteTicket }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 hover:bg-cyan-50 dark:hover:bg-cyan-950/40">Equipo {{ $ipClienteTicket }}</a>
                            @endif
                            <button type="button"
                                class="btn-ver-detalle-ticket inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-white dark:hover:bg-gray-800"
                                data-detalle-b64="{{ base64_encode(json_encode($detalleTicket, JSON_UNESCAPED_UNICODE)) }}">
                                Notas{{ !empty($ticket->datos_diagnostico) ? ' y diagnóstico' : '' }}
                            </button>
                        </div>
                        <div>
                            <p class="font-medium uppercase tracking-wide text-gray-400">Reportado vía</p>
                            <p class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $ticket->reportado_desde ? ($reportado[$ticket->reportado_desde] ?? $ticket->reportado_desde) : '—' }}</p>
                        </div>
                        <div>
                            <p class="font-medium uppercase tracking-wide text-gray-400">Registrado por</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{!! $resaltar($ticket->usuario?->name ?? '—') !!}</p>
                        </div>
                        <div>
                            <p class="font-medium uppercase tracking-wide text-gray-400">Facturación Ticket</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                @if ($ticket->factura_interna_id)
                                    @if (auth()->user()?->tienePermiso('factura-interna.ver') ?? false)
                                        <a href="{{ route('factura-internas.show', $ticket->factura_interna_id) }}" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Factura #{{ $ticket->factura_interna_id }}</a>
                                    @else
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Facturado</span>
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400"> · {{ number_format((float) ($ticket->monto_cobro_ticket ?? 0), 0, ',', '.') }} Gs.</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Sin cobro extra</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="font-medium uppercase tracking-wide text-gray-400">Última actualización</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $ticket->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    @if($busqueda !== '')
                        <p class="text-base font-semibold text-gray-700 dark:text-gray-300">Ningún ticket coincide con la búsqueda "{{ $busqueda }}".</p>
                        <a href="{{ route('tickets.index') }}" class="mt-2 inline-block text-sm text-purple-600 dark:text-purple-400 hover:underline">Ver todos los tickets</a>
                    @else
                        <p class="text-base font-semibold text-gray-700 dark:text-gray-300">No hay tickets en esta cola.</p>
                        <a href="{{ route('tickets.create') }}" class="mt-2 inline-block text-sm text-purple-600 dark:text-purple-400 hover:underline">Crear nuevo ticket</a>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Paginación y Selector de filas por página --}}
        @if ($tickets->total() > 0)
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-b-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <span>Filas</span>
                    <select id="ticket-per-page" aria-label="Filas por página"
                        class="px-2 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                        @foreach ([10, 15, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($tickets->perPage() === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <span>por página · Mostrando {{ $tickets->firstItem() ?? 0 }}-{{ $tickets->lastItem() ?? 0 }} de {{ $tickets->total() }}</span>
                </label>
                @if ($tickets->hasPages())
                    <nav class="flex items-center gap-2 text-sm" aria-label="Paginación">
                        @if ($tickets->onFirstPage())
                            <span class="px-3 py-1.5 rounded-lg text-gray-400 dark:text-gray-500">Anterior</span>
                        @else
                            <a href="{{ $tickets->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-700">Anterior</a>
                        @endif
                        <span class="text-gray-600 dark:text-gray-300">Página {{ $tickets->currentPage() }} de {{ $tickets->lastPage() }}</span>
                        @if ($tickets->hasMorePages())
                            <a href="{{ $tickets->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-700">Siguiente</a>
                        @else
                            <span class="px-3 py-1.5 rounded-lg text-gray-400 dark:text-gray-500">Siguiente</span>
                        @endif
                    </nav>
                @endif
            </div>
        @endif
    </div>
</div>


{{-- Menú acciones kebab flotante --}}
<div id="ticket-acciones-dropdown" class="fixed z-[9999] hidden py-1 min-w-[220px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700" role="menu" aria-hidden="true"></div>

{{-- Modal táctil para Cambiar Estado (usado tanto individual como masivo) --}}
@php
    $claseEstadoTarjeta = function (string $estado): string {
        return match ($estado) {
            'resuelto', 'cerrado' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'cancelado' => 'bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
            'en_proceso' => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            'en_camino' => 'bg-cyan-50 dark:bg-cyan-950/40 text-cyan-800 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
            'no_realizado' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            default => 'bg-gray-50 dark:bg-gray-700/60 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-600',
        };
    };
    $iconoEstadoTarjeta = function (string $estado): string {
        return match ($estado) {
            'pendiente' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'en_camino' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>',
            'en_proceso' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'resuelto' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'no_realizado' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
            'cerrado' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
            'cancelado' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        };
    };
@endphp
<div id="ticket-estado-modal" class="fixed inset-0 z-[9999] hidden" role="dialog" aria-modal="true" aria-labelledby="ticket-estado-modal-title" aria-hidden="true">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="ticket-estado-modal-backdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-xl rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <h3 id="ticket-estado-modal-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">Cambiar estado</h3>
                    <p id="ticket-estado-modal-sub" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"></p>
                </div>
                <button type="button" id="ticket-estado-modal-cerrar" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="ticket-estado-cards-wrap">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2" id="ticket-estado-cards-label">Elegí el estado</p>
            <div id="ticket-estado-cards" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                @foreach (['pendiente', 'en_camino', 'en_proceso', 'no_realizado'] as $key)
                    @php $label = App\Models\Ticket::estados()[$key]; @endphp
                    <button type="button"
                        class="ticket-estado-card relative min-h-20 w-full rounded-lg border p-2 cursor-pointer flex flex-col items-center justify-center transition-all {{ $claseEstadoTarjeta($key) }}"
                        data-estado="{{ $key }}">
                        <span class="ticket-estado-check hidden absolute top-1 right-2 w-4 h-4 rounded-full bg-purple-600 text-white flex items-center justify-center pointer-events-none" aria-hidden="true">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">{!! $iconoEstadoTarjeta($key) !!}</svg>
                        <span class="mt-1 block text-xs font-semibold leading-tight text-center">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            </div>
            <label for="ticket-estado-tecnico" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Técnico Asignado</label>
            <select id="ticket-estado-tecnico"
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                <option value="">Sin técnico</option>
                @foreach ($tecnicosCampo as $t)
                    <option value="{{ $t->usuario_id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="ticket-estado-modal-cancelar" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                <button type="button" id="ticket-estado-modal-guardar" class="px-5 py-2 text-sm rounded-lg bg-purple-600 text-white font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:opacity-50 transition-colors shadow-sm">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<div id="ticket-resolver-modal" class="fixed inset-0 z-[10000] hidden" role="dialog" aria-modal="true" aria-labelledby="ticket-resolver-title" aria-describedby="ticket-resolver-nota ticket-resolver-sale ticket-resolver-deshacer" aria-hidden="true">
    <div class="absolute inset-0 bg-black/60" id="ticket-resolver-backdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
            <h3 id="ticket-resolver-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">Resolver</h3>
            <p id="ticket-resolver-nota" class="mt-3 text-sm text-gray-800 dark:text-gray-100 truncate"></p>
            <p id="ticket-resolver-sale" class="mt-2 text-sm text-gray-600 dark:text-gray-300">Sale de En curso.</p>
            <p id="ticket-resolver-aviso" class="mt-1 text-sm text-gray-600 dark:text-gray-300">Resolver avisa al cliente por la app y por WhatsApp.</p>
            <p id="ticket-resolver-deshacer" class="mt-1 text-sm text-gray-600 dark:text-gray-300">Se puede deshacer 4 segundos.</p>
            <p id="ticket-resolver-error" class="mt-3 text-sm text-red-600 dark:text-red-400 hidden" role="alert"></p>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <button type="button" id="ticket-resolver-cancelar" class="min-h-11 px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500">Cancelar</button>
                <button type="button" id="ticket-resolver-sin-avisar" class="min-h-11 px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 disabled:opacity-50">Resolver sin avisar</button>
                <button type="button" id="ticket-resolver-confirmar" class="min-h-11 px-5 py-2 text-sm rounded-lg bg-purple-600 text-white font-medium hover:bg-purple-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 disabled:opacity-50">Resolver</button>
            </div>
        </div>
    </div>
</div>
<div id="ticket-deshacer" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[10001] hidden w-[min(28rem,calc(100%-2rem))] rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between gap-3" role="status">
    <p id="ticket-deshacer-texto" class="text-sm text-gray-900 dark:text-gray-100"></p>
    <button type="button" id="ticket-deshacer-btn" class="min-h-11 px-3 text-sm font-medium text-purple-700 dark:text-purple-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 rounded-lg">Deshacer</button>
</div>

@push('scripts')
@include('partials.ticket-diagnostico-app-styles')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var filtroOcultarStorageKey = 'tickets_filtro_ocultar_resuelto_cerrado';
    var bulkUrl = "{{ route('tickets.bulk-update') }}";

    // Paginación
    var selPerPage = document.getElementById('ticket-per-page');
    if (selPerPage) {
        selPerPage.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    // Acordeón de datos secundarios
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ticket-expandir-btn');
        if (!btn) return;
        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;
        var abierto = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !abierto);
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        btn.setAttribute('title', abierto ? 'Ocultar detalles' : 'Más detalles');
        var fila = btn.closest('article');
        var idFila = fila ? fila.getAttribute('data-id') : '';
        btn.setAttribute('aria-label', (abierto ? 'Ocultar detalles' : 'Mostrar más detalles') + (idFila ? ' del ticket #' + idFila : ''));
        var icono = btn.querySelector('.ticket-expandir-icon');
        if (icono) icono.classList.toggle('rotate-180', abierto);
    });

    // Menú de filtros desplegable
    var wrapFiltros = document.getElementById('ticket-filtros-wrap');
    var btnFiltros = document.getElementById('ticket-filtros-btn');
    var menuFiltros = document.getElementById('ticket-filtros-menu');
    if (wrapFiltros && btnFiltros && menuFiltros) {
        function filtrosAbiertos() {
            return !menuFiltros.classList.contains('hidden');
        }
        function setFiltrosOpen(open) {
            menuFiltros.classList.toggle('hidden', !open);
            btnFiltros.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        btnFiltros.addEventListener('click', function (e) {
            e.stopPropagation();
            setFiltrosOpen(!filtrosAbiertos());
        });
        document.addEventListener('click', function (e) {
            if (!wrapFiltros.contains(e.target)) setFiltrosOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            setFiltrosOpen(false);
            document.querySelectorAll('details.ticket-mas-colas[open]').forEach(function (d) { d.open = false; });
            cerrarMenuTicketAcciones();
        });
    }

    // Persistencia del checkbox ocultar resuelto/cerrado
    var filtroOcultarCheck = document.querySelector('input[name="ocultar_resuelto_cerrado"]');
    document.querySelectorAll('.js-quitar-ocultar').forEach(function(enlace) {
        enlace.addEventListener('click', function() {
            localStorage.setItem(filtroOcultarStorageKey, '0');
        });
    });
    if (filtroOcultarCheck) {
        var formularioFiltros = filtroOcultarCheck.closest('form');
        var hasFiltroOcultarQuery = new URLSearchParams(window.location.search).has('ocultar_resuelto_cerrado');
        if (hasFiltroOcultarQuery) {
            localStorage.setItem(filtroOcultarStorageKey, filtroOcultarCheck.checked ? '1' : '0');
        } else {
            var filtroOcultarGuardado = localStorage.getItem(filtroOcultarStorageKey);
            if (filtroOcultarGuardado !== null && !new URLSearchParams(window.location.search).has('cola')) {
                filtroOcultarCheck.checked = filtroOcultarGuardado === '1';
                if (filtroOcultarCheck.checked && formularioFiltros) {
                    formularioFiltros.submit();
                }
            }
        }

        filtroOcultarCheck.addEventListener('change', function() {
            localStorage.setItem(filtroOcultarStorageKey, this.checked ? '1' : '0');
        });
    }

    // Decodificación UTF-8 segura para strings Base64
    function base64ToUtf8(b64) {
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new TextDecoder('utf-8').decode(bytes);
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function filaDetalle(etiqueta, valor, isDark) {
        if (valor === null || valor === undefined || valor === '') return '';
        var b = isDark ? 'border-gray-700' : 'border-gray-100';
        var lbl = isDark ? 'text-gray-400' : 'text-gray-500';
        var val = isDark ? 'text-gray-100' : 'text-gray-900';
        return '<div class="flex flex-col sm:flex-row sm:gap-2 py-1.5 border-b ' + b + ' last:border-0"><span class="' + lbl + ' shrink-0 min-w-[7.5rem] font-medium">' + escapeHtml(etiqueta) + '</span><span class="' + val + ' break-words font-semibold">' + escapeHtml(valor) + '</span></div>';
    }

    function toneClass(tone) {
        if (tone === 'good') return 'diag-app-metric__value--good';
        if (tone === 'ok') return 'diag-app-metric__value--ok';
        if (tone === 'warn') return 'diag-app-metric__value--warn';
        if (tone === 'bad') return 'diag-app-metric__value--bad';
        return '';
    }

    function renderDiagnosticoApp(secciones) {
        if (!Array.isArray(secciones) || secciones.length === 0) {
            return '<div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 text-center text-sm text-gray-500 dark:text-gray-400"><p>No se registraron métricas de diagnóstico móvil para este ticket.</p></div>';
        }
        var html = '<div class="diag-app-panel mt-1"><div class="diag-app-panel__head"><div><p class="diag-app-panel__title">Diagnóstico app cliente</p><p class="diag-app-panel__sub">Telemetría capturada al reportar el problema</p></div></div><div class="diag-app-panel__body">';
        secciones.forEach(function(sec) {
            html += '<div><p class="diag-app-section__title">' + escapeHtml(sec.titulo || '') + '</p>';
            if (sec.tipo === 'metricas' && Array.isArray(sec.items)) {
                html += '<div class="diag-app-metrics diag-app-metrics--wide">';
                sec.items.forEach(function(item) {
                    html += '<div class="diag-app-metric"><p class="diag-app-metric__label">' + escapeHtml(item.label) + '</p><p class="diag-app-metric__value ' + toneClass(item.tone) + '">' + escapeHtml(item.value) + '</p></div>';
                });
                html += '</div>';
            } else if (sec.tipo === 'ping' && Array.isArray(sec.items)) {
                html += '<div class="diag-app-ping-grid">';
                sec.items.forEach(function(item) {
                    html += '<div class="diag-app-ping"><p class="diag-app-ping__label">' + escapeHtml(item.label) + '</p><p class="diag-app-ping__value">' + escapeHtml(item.value) + '</p></div>';
                });
                html += '</div>';
            } else if (sec.tipo === 'traceroute' && Array.isArray(sec.filas)) {
                html += '<div class="diag-app-table-wrap"><table class="diag-app-table"><thead><tr><th>Salto</th><th>Destino</th><th>Latencia</th><th>Red</th><th>Estado</th></tr></thead><tbody>';
                sec.filas.forEach(function(f) {
                    html += '<tr class="' + (f.alcanzado ? 'diag-app-table__destino' : '') + '"><td class="font-mono">' + escapeHtml(f.ttl) + '</td><td class="break-all">' + escapeHtml(f.destino) + '</td><td>' + escapeHtml(f.latencia) + '</td><td>' + escapeHtml(f.marca) + '</td><td>' + (f.alcanzado ? '<span class="diag-app-badge diag-app-badge--destino">Destino</span>' : '<span class="diag-app-badge diag-app-badge--transito">Tránsito</span>') + '</td></tr>';
                });
                html += '</tbody></table></div>';
            } else if (sec.tipo === 'ubicacion') {
                html += '<div class="diag-app-location"><p class="diag-app-location__coords">' + escapeHtml(sec.texto) + '</p>';
                if (sec.maps_url) {
                    html += '<a href="' + escapeHtml(sec.maps_url) + '" target="_blank" rel="noopener" class="diag-app-link">Abrir en Google Maps →</a>';
                }
                html += '</div>';
            }
            html += '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function bloqueNota(titulo, texto, isDark) {
        var box = isDark ? 'bg-gray-900/50 border-gray-700' : 'bg-gray-50 border-gray-200';
        var lbl = isDark ? 'text-gray-400' : 'text-gray-500';
        var val = isDark ? 'text-gray-100' : 'text-gray-900';
        var cuerpo = (texto === null || texto === undefined || String(texto).trim() === '')
            ? '<p class="text-sm ' + (isDark ? 'text-gray-500' : 'text-gray-400') + ' italic">Sin notas registradas</p>'
            : '<p class="text-sm ' + val + ' whitespace-pre-wrap break-words leading-relaxed">' + escapeHtml(texto) + '</p>';
        return '<div class="rounded-xl border p-3.5 ' + box + ' shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider ' + lbl + ' mb-1.5">' + escapeHtml(titulo) + '</p>' + cuerpo + '</div>';
    }

    // Paquete C: Modal Pro de Telemetría con Pestañas
    function abrirDetalleTicketDesdeB64(raw) {
        if (!raw) return;
        var t;
        try {
            t = JSON.parse(base64ToUtf8(raw));
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo leer las observaciones del ticket.' });
            return;
        }
        var isDark = document.documentElement.classList.contains('dark');
        var tieneDiag = Array.isArray(t.diagnostico_vista) && t.diagnostico_vista.length > 0;

        var html = '<div class="text-left text-sm max-h-[68vh] overflow-y-auto ' + (isDark ? 'text-gray-100' : 'text-gray-900') + '">';

        // Pestañas del modal
        html += '<div class="flex border-b ' + (isDark ? 'border-gray-700' : 'border-gray-200') + ' mb-4 gap-2">';
        html += '<button type="button" class="swal-tab-btn active px-3.5 py-2 text-xs font-bold border-b-2 transition-colors cursor-pointer" data-target="swal-tab-notas">📝 Notas & Reporte</button>';
        html += '<button type="button" class="swal-tab-btn px-3.5 py-2 text-xs font-bold border-b-2 border-transparent ' + (isDark ? 'text-gray-400 hover:text-gray-200' : 'text-gray-500 hover:text-gray-800') + ' transition-colors cursor-pointer flex items-center gap-1.5" data-target="swal-tab-telemetria">🛰️ Diagnóstico & Red' + (tieneDiag ? ' <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>' : '') + '</button>';
        html += '<button type="button" class="swal-tab-btn px-3.5 py-2 text-xs font-bold border-b-2 border-transparent ' + (isDark ? 'text-gray-400 hover:text-gray-200' : 'text-gray-500 hover:text-gray-800') + ' transition-colors cursor-pointer" data-target="swal-tab-ficha">📋 Ficha & Cobro</button>';
        html += '</div>';

        // Panel 1: Notas
        html += '<div id="swal-tab-notas" class="swal-tab-pane space-y-3">';
        if (t.asunto) {
            var ico = t.asunto_icono ? ('<span class="text-sm">' + escapeHtml(t.asunto_icono) + '</span> ') : '';
            var bStyle = t.asunto_badge_style ? ('style="' + escapeHtml(t.asunto_badge_style) + '"') : '';
            html += '<div class="flex items-center gap-2 flex-wrap mb-2">' +
                '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold" ' + bStyle + '>' + ico + escapeHtml(t.asunto) + '</span>' +
                (t.cliente ? ' · <span class="' + (isDark ? 'text-gray-100' : 'text-gray-900') + ' font-semibold">' + escapeHtml(t.cliente) + '</span>' : '') +
                '</div>';
        }
        html += bloqueNota('Descripción del Reclamo', t.descripcion, isDark);
        html += bloqueNota('Observaciones de Atención', t.observaciones, isDark);
        if (t.nota_tecnico) html += bloqueNota('Nota del Técnico de Terreno', t.nota_tecnico, isDark);
        if (t.imagen_url) {
            var lk2 = isDark ? 'text-purple-400 hover:text-purple-300' : 'text-purple-600 hover:text-purple-800';
            html += '<div class="pt-1"><a href="' + escapeHtml(t.imagen_url) + '" target="_blank" rel="noopener" class="' + lk2 + ' font-semibold underline-offset-2 hover:underline text-sm inline-flex items-center gap-1.5">🖼️ Ver imagen adjunta →</a></div>';
        }
        html += '</div>';

        // Panel 2: Telemetría
        html += '<div id="swal-tab-telemetria" class="swal-tab-pane hidden space-y-3">';
        html += renderDiagnosticoApp(t.diagnostico_vista);
        html += '</div>';

        // Panel 3: Ficha y Cobro
        html += '<div id="swal-tab-ficha" class="swal-tab-pane hidden space-y-2.5 p-1">';
        html += filaDetalle('Cliente', t.cliente, isDark);
        if (t.cliente_cedula) html += filaDetalle('Cédula', t.cliente_cedula, isDark);
        if (t.cliente_telefono) html += filaDetalle('Teléfono', t.cliente_telefono, isDark);
        html += filaDetalle('Estado actual', t.estado, isDark);
        html += filaDetalle('Prioridad', t.prioridad, isDark);
        html += filaDetalle('Técnico asignado', t.asignado || 'Sin asignar', isDark);
        html += filaDetalle('Registrado por', t.creador || 'Sistema', isDark);
        html += filaDetalle('Canal de reporte', t.reportado || 'No especificado', isDark);
        html += filaDetalle('Fecha registro', t.created_at, isDark);
        if (t.fecha_cierre) html += filaDetalle('Fecha de cierre', t.fecha_cierre, isDark);
        html += filaDetalle('Cobro / Factura', t.monto_cobro_ticket ? ('Facturado · ' + t.monto_cobro_ticket) : 'Sin cobro extra', isDark);
        html += '</div>';

        html += '</div>';

        Swal.fire({
            title: 'Detalles — Ticket #' + t.id,
            html: html,
            width: tieneDiag ? '44rem' : '38rem',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#9333ea',
            background: isDark ? '#1f2937' : '#ffffff',
            color: isDark ? '#f9fafb' : '#111827',
            customClass: {
                popup: isDark ? '!bg-gray-800 !text-gray-100 !rounded-2xl !border !border-gray-700 !shadow-2xl' : '!rounded-2xl !border !border-gray-200 !shadow-2xl',
                title: isDark ? '!text-gray-100 font-bold' : '!text-gray-900 font-bold',
                htmlContainer: 'text-left !mt-2',
                confirmButton: isDark ? '!bg-purple-600 hover:!bg-purple-500 !text-white !shadow-lg' : ''
            },
            didOpen: function() {
                var tabBtns = document.querySelectorAll('.swal-tab-btn');
                tabBtns.forEach(function(b) {
                    b.addEventListener('click', function() {
                        var targetId = this.getAttribute('data-target');
                        tabBtns.forEach(function(btn) {
                            btn.classList.remove('active', 'border-purple-600', 'text-purple-600', 'dark:text-purple-400');
                            btn.classList.add('border-transparent');
                        });
                        this.classList.add('active', 'border-purple-600', 'text-purple-600', 'dark:text-purple-400');
                        this.classList.remove('border-transparent');

                        document.querySelectorAll('.swal-tab-pane').forEach(function(p) {
                            p.classList.add('hidden');
                        });
                        var targetPane = document.getElementById(targetId);
                        if (targetPane) targetPane.classList.remove('hidden');
                    });
                });
            }
        });
    }

    document.querySelectorAll('.btn-ver-detalle-ticket').forEach(function(btn) {
        btn.addEventListener('click', function() {
            cerrarMenuTicketAcciones();
            abrirDetalleTicketDesdeB64(this.getAttribute('data-detalle-b64'));
        });
    });

    // Menú de acciones kebab
    var ticketMenuEl = document.getElementById('ticket-acciones-dropdown');
    var ticketMenuOpenBtn = null;

    function cerrarMenuTicketAcciones() {
        if (!ticketMenuEl) return;
        ticketMenuEl.classList.add('hidden');
        ticketMenuEl.setAttribute('aria-hidden', 'true');
        if (ticketMenuOpenBtn) {
            ticketMenuOpenBtn.setAttribute('aria-expanded', 'false');
            ticketMenuOpenBtn = null;
        }
    }

    function posicionarMenuTicketAcciones(btn) {
        if (!ticketMenuEl) return;
        ticketMenuEl.classList.remove('hidden');
        ticketMenuEl.setAttribute('aria-hidden', 'false');
        var rect = btn.getBoundingClientRect();
        var mw = 228;
        var mh = ticketMenuEl.offsetHeight || 280;
        var left = Math.max(8, Math.min(rect.right - mw, window.innerWidth - mw - 8));
        var top = rect.bottom + 4;
        if (rect.bottom + mh + 12 > window.innerHeight) {
            top = Math.max(8, rect.top - mh - 4);
        }
        ticketMenuEl.style.left = left + 'px';
        ticketMenuEl.style.top = top + 'px';
    }

    function construirHtmlMenuTicket(cfg) {
        var icLista = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>';
        var icDoc = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
        var icEdit = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>';
        var icCal = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        var icImg = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        var icTrash = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
        var icCheck = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        var icWifi = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-2.912a10 10 0 0114.16 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path></svg>';
        var baseBtn = 'w-full min-h-11 px-4 py-2.5 text-left text-sm flex items-center gap-2 transition-colors';
        var sep = '<div class="my-1 border-t border-gray-200 dark:border-gray-700" role="separator"></div>';
        var datosEstado = ' data-url="' + escapeHtml(cfg.update_estado_url) + '" data-estado="' + escapeHtml(cfg.estado || '') + '" data-asignado-id="' + escapeHtml(cfg.asignado_id != null ? String(cfg.asignado_id) : '') + '" data-asignado-nombre="' + escapeHtml(cfg.asignado_nombre || '') + '" data-ticket-id="' + escapeHtml(cfg.ticket_id != null ? String(cfg.ticket_id) : '') + '" data-cliente="' + escapeHtml(cfg.cliente || '') + '" data-asunto="' + escapeHtml(cfg.asunto || '') + '"';
        var h = '';
        h += '<button type="button" role="menuitem" class="' + baseBtn + ' text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 ticket-menu-item" data-accion="estado"' + datosEstado + '>' + icLista + ' Cambiar estado</button>';
        if (!cfg.asignar_en_fila) {
            h += '<button type="button" role="menuitem" class="' + baseBtn + ' text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 ticket-menu-item" data-accion="asignar"' + datosEstado + '>' + icLista + ' Asignar técnico</button>';
        }
        h += sep;
        if (cfg.herramientas_red_url) {
            h += '<a href="' + escapeHtml(cfg.herramientas_red_url) + '" role="menuitem" class="block ' + baseBtn + ' text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">' + icWifi + ' Herramientas de red</a>';
        }
        if (cfg.puede_facturar_ticket && cfg.facturar_url) {
            h += '<button type="button" role="menuitem" class="' + baseBtn + ' text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 ticket-menu-item" data-accion="facturar-ticket" data-facturar-url="' + escapeHtml(cfg.facturar_url) + '">' + icDoc + ' Crear factura por ticket</button>';
        }
        h += '<a href="' + escapeHtml(cfg.edit_ticket_url) + '" role="menuitem" class="block ' + baseBtn + ' text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">' + icEdit + ' Editar</a>';
        h += '<a href="' + escapeHtml(cfg.agenda_url) + '" role="menuitem" class="block ' + baseBtn + ' text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700">' + icCal + ' Crear cita en agenda</a>';
        if (cfg.imagen_url) {
            h += '<a href="' + escapeHtml(cfg.imagen_url) + '" role="menuitem" target="_blank" rel="noopener" class="block ' + baseBtn + ' text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30">' + icImg + ' Ver imagen</a>';
        }
        h += sep;
        h += '<form method="POST" action="' + escapeHtml(cfg.destroy_url) + '" class="block ticket-menu-eliminar-form" onsubmit="return confirm(\'¿Eliminar este ticket?\');"><input type="hidden" name="_token" value="' + escapeHtml(cfg.csrf) + '"><input type="hidden" name="_method" value="DELETE"><button type="submit" role="menuitem" class="' + baseBtn + ' text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">' + icTrash + ' Eliminar</button></form>';
        return h;
    }

    document.querySelectorAll('.ticket-acciones-kebab').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!ticketMenuEl) return;
            var raw = this.getAttribute('data-menu-b64');
            if (!raw) return;
            var cfg;
            try {
                cfg = JSON.parse(base64ToUtf8(raw));
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo abrir el menú.' });
                return;
            }
            var cardMenu = this.closest('article');
            cfg.asignar_en_fila = !!(cardMenu && cardMenu.querySelector('.btn-asignar-fila'));
            if (ticketMenuOpenBtn === this) {
                cerrarMenuTicketAcciones();
                return;
            }
            cerrarMenuTicketAcciones();
            ticketMenuOpenBtn = this;
            this.setAttribute('aria-expanded', 'true');
            ticketMenuEl.innerHTML = construirHtmlMenuTicket(cfg);
            posicionarMenuTicketAcciones(this);
            var primeroMenu = ticketMenuEl.querySelector('[role="menuitem"]');
            if (primeroMenu) primeroMenu.focus();
        });
    });

    document.addEventListener('click', function(e) {
        if (!ticketMenuEl || ticketMenuEl.classList.contains('hidden')) return;
        if (ticketMenuOpenBtn && (ticketMenuOpenBtn.contains(e.target) || ticketMenuEl.contains(e.target))) return;
        cerrarMenuTicketAcciones();
    });

    window.addEventListener('scroll', function() { cerrarMenuTicketAcciones(); }, true);
    window.addEventListener('resize', cerrarMenuTicketAcciones);

    if (ticketMenuEl) {
        ticketMenuEl.addEventListener('click', function(e) {
            var t = e.target.closest('.ticket-menu-item');
            if (!t) return;
            var accion = t.getAttribute('data-accion');
            if (accion === 'facturar-ticket') {
                e.preventDefault();
                var urlFact = t.getAttribute('data-facturar-url');
                cerrarMenuTicketAcciones();
                Swal.fire({
                    title: 'Facturar ticket',
                    html: '<p class="text-sm text-gray-600 dark:text-gray-400 mb-2 text-left">Monto en guaraníes (entero).</p>' +
                        '<input id="swal-monto-ticket" type="number" min="1" step="1" class="swal2-input" placeholder="Ej. 50000" autocomplete="off">',
                    showCancelButton: true,
                    confirmButtonText: 'Generar factura',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#9333ea',
                    focusConfirm: false,
                    preConfirm: function() {
                        var el = document.getElementById('swal-monto-ticket');
                        var n = Number(el && el.value ? el.value : '');
                        if (!Number.isFinite(n) || n < 1) {
                            Swal.showValidationMessage('Ingrese un monto válido (mínimo 1).');
                            return false;
                        }
                        return Math.round(n);
                    }
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    var monto = result.value;
                    fetch(urlFact, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ monto: monto })
                    }).then(function(r) {
                        return r.json().then(function(data) {
                            if (!r.ok) {
                                var msg = data.message || (data.errors && data.errors.monto && data.errors.monto[0]) || 'Error al generar la factura.';
                                throw new Error(msg);
                            }
                            return data;
                        });
                    }).then(function() {
                        return Swal.fire({ icon: 'success', title: 'Listo', text: 'Factura interna generada correctamente.' });
                    }).then(function() {
                        window.location.reload();
                    }).catch(function(err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo generar la factura.' });
                    });
                });
            } else if (accion === 'estado' || accion === 'asignar') {
                e.preventDefault();
                cerrarMenuTicketAcciones();
                abrirModalEstadoTicket({
                    url: t.getAttribute('data-url'),
                    estado: t.getAttribute('data-estado') || '',
                    asignadoId: t.getAttribute('data-asignado-id') || '',
                    asignadoNombre: t.getAttribute('data-asignado-nombre') || '',
                    ticketId: t.getAttribute('data-ticket-id') || '',
                    cliente: t.getAttribute('data-cliente') || '',
                    asunto: t.getAttribute('data-asunto') || '',
                    soloAsignar: accion === 'asignar'
                });
            } else if (accion === 'resuelto') {
                e.preventDefault();
                cerrarMenuTicketAcciones();
                confirmarYResolverTicket({
                    url: t.getAttribute('data-url'),
                    ticketId: t.getAttribute('data-ticket-id'),
                    estado: t.getAttribute('data-estado') || '',
                    cliente: t.getAttribute('data-cliente') || '',
                    asunto: t.getAttribute('data-asunto') || '',
                    nota: t.getAttribute('data-nota') || ''
                });
            }
        });
    }

    function colaOcultaResueltos() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('ocultar_resuelto_cerrado') === '1') return true;
        return ['en_curso', 'abiertos', 'pendientes', 'en_camino', 'en_proceso', 'alta', 'sin_asignar'].indexOf(params.get('cola') || '') !== -1;
    }

    function filaTicket(ticketId) {
        return document.querySelector('article[data-id="' + ticketId + '"]');
    }

    function claseBadgeEstado(estado) {
        if (estado === 'resuelto' || estado === 'cerrado') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
        if (estado === 'cancelado') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300';
        if (estado === 'en_proceso') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300';
        if (estado === 'en_camino') return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-300';
        return 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
    }

    function quitarFilaOContar(card) {
        card.remove();
        var conteo = document.getElementById('tickets-resumen-conteo');
        if (!conteo) return;
        var n = parseInt(conteo.textContent, 10);
        if (!isNaN(n) && n > 0) conteo.textContent = String(n - 1);
    }

    function aplicarEstadoEnFila(ticketId, estado, etiqueta) {
        var card = filaTicket(ticketId);
        if (!card) return;
        var sale = (estado === 'resuelto' || estado === 'cerrado' || estado === 'cancelado') && colaOcultaResueltos();
        if (sale) {
            quitarFilaOContar(card);
            return;
        }
        var badge = card.querySelector('[data-estado-badge]');
        if (badge) {
            var textoEstado = etiqueta || estado;
            badge.textContent = textoEstado;
            badge.className = claseBadgeEstado(estado);
        }
        card.querySelectorAll('.btn-quick-resolver').forEach(function(b) {
            b.setAttribute('data-estado', estado || '');
            if (etiqueta) b.setAttribute('data-estado-etiqueta', etiqueta);
        });
        if (estado === 'resuelto' || estado === 'cerrado' || estado === 'cancelado') {
            card.querySelectorAll('.btn-quick-resolver').forEach(function(b) { b.remove(); });
        }
    }

    function pintarTecnicoEnFila(ticketId, nombre) {
        var card = filaTicket(ticketId);
        if (!card) return;
        var slot = card.querySelector('[data-tecnico-slot]');
        if (!slot) return;
        slot.innerHTML = '<span class="inline-flex items-start gap-1.5 max-w-full px-2 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700/60 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600" title="' + escapeHtml(nombre || 'Sin técnico') + '"><span class="min-w-0 break-words whitespace-normal leading-snug">' + escapeHtml(nombre || 'Sin técnico') + '</span></span>';
        card.querySelectorAll('.btn-asignar-fila').forEach(function(b) { b.remove(); });
        card.querySelectorAll('.btn-quick-resolver').forEach(function(b) {
            b.setAttribute('data-tecnico', nombre || '');
            b.setAttribute('data-sin-tecnico', nombre ? '0' : '1');
        });
    }

    function confirmarYResolverTicket(opts) {
        abrirModalResolver(opts);
    }

    document.querySelectorAll('.btn-quick-resolver').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            cerrarMenuTicketAcciones();
            abrirModalResolver({
                url: this.getAttribute('data-url'),
                ticketId: this.getAttribute('data-ticket-id'),
                estado: this.getAttribute('data-estado') || '',
                cliente: this.getAttribute('data-cliente') || '',
                asunto: this.getAttribute('data-asunto') || '',
                nota: this.getAttribute('data-nota') || '',
                via: this.getAttribute('data-via') || '',
                sinTecnico: this.getAttribute('data-sin-tecnico') === '1',
                estadoEtiqueta: this.getAttribute('data-estado-etiqueta') || '',
                tecnico: this.getAttribute('data-tecnico') || ''
            });
        });
    });

    document.querySelectorAll('.btn-asignar-rapido').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            abrirModalEstadoTicket({
                url: this.getAttribute('data-url'),
                estado: this.getAttribute('data-estado') || '',
                asignadoId: '',
                ticketId: this.getAttribute('data-ticket-id') || '',
                cliente: this.getAttribute('data-cliente') || '',
                asunto: this.getAttribute('data-asunto') || '',
                soloAsignar: true
            });
        });
    });

    function enviarEstadoTicket(url, nuevoEstado, asignadoId, sinAvisar) {
        var body = { estado: nuevoEstado };
        if (typeof asignadoId !== 'undefined') {
            body.asignado_id = (asignadoId === '' || asignadoId === null) ? null : Number(asignadoId);
        }
        if (sinAvisar) body.sin_avisar = true;
        return fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function(r) {
            if (!r.ok) throw new Error();
            return r.json();
        });
    }

    // Modal de cambio de estado (Individual y Masivo)
    var estadoModalEl = document.getElementById('ticket-estado-modal');
    var estadoModalTitle = document.getElementById('ticket-estado-modal-title');
    var estadoModalSub = document.getElementById('ticket-estado-modal-sub');
    var estadoModalSelect = document.getElementById('ticket-estado-tecnico');
    var estadoModalGuardar = document.getElementById('ticket-estado-modal-guardar');
    var estadoCardsWrap = document.getElementById('ticket-estado-cards-wrap');
    var estadoModalCtx = { url: '', estado: '', bulkIds: [], ticketId: '', soloAsignar: false };
    var focoAlCerrarEstado = null;
    var estadoCardSel = 'ring-2 ring-purple-500 border-purple-500';

    function marcarTarjetaEstado(estado) {
        estadoModalCtx.estado = estado || '';
        document.querySelectorAll('.ticket-estado-card').forEach(function(card) {
            var activo = card.getAttribute('data-estado') === estado;
            estadoCardSel.split(' ').forEach(function(cls) {
                card.classList.toggle(cls, activo);
            });
            var check = card.querySelector('.ticket-estado-check');
            if (check) check.classList.toggle('hidden', !activo);
            card.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
    }

    function cerrarModalEstadoTicket() {
        if (!estadoModalEl) return;
        estadoModalEl.classList.add('hidden');
        estadoModalEl.setAttribute('aria-hidden', 'true');
        estadoModalCtx = { url: '', estado: '', bulkIds: [], ticketId: '', soloAsignar: false };
        if (estadoModalSelect) {
            estadoModalSelect.querySelectorAll('option[data-extra="1"]').forEach(function(o) { o.remove(); });
        }
        if (estadoCardsWrap) estadoCardsWrap.classList.remove('hidden');
        if (estadoModalTitle) estadoModalTitle.textContent = 'Cambiar estado';
        if (estadoModalGuardar) estadoModalGuardar.disabled = false;
        var volver = focoAlCerrarEstado;
        focoAlCerrarEstado = null;
        if (volver && typeof volver.focus === 'function') volver.focus();
    }

    function abrirModalEstadoTicket(opts) {
        if (!estadoModalEl) return;
        var solo = !!opts.soloAsignar;
        var estadosDespacho = ['pendiente', 'en_camino', 'en_proceso', 'no_realizado'];
        var estadoInicial = estadosDespacho.indexOf(opts.estado || '') !== -1 ? opts.estado : '';
        estadoModalCtx = {
            url: opts.url || '',
            estado: estadoInicial,
            bulkIds: opts.bulkIds || [],
            ticketId: opts.ticketId || '',
            soloAsignar: solo
        };
        var partes = [];
        if (opts.bulkIds && opts.bulkIds.length > 0) {
            partes.push(opts.bulkIds.length + ' tickets seleccionados');
        } else {
            if (opts.ticketId) partes.push('Ticket #' + opts.ticketId);
            if (opts.cliente) partes.push(opts.cliente);
            if (opts.asunto) partes.push(opts.asunto);
        }
        if (estadoModalTitle) estadoModalTitle.textContent = solo ? 'Asignar técnico' : 'Cambiar estado';
        if (estadoCardsWrap) estadoCardsWrap.classList.toggle('hidden', solo);
        if (estadoModalSub) estadoModalSub.textContent = partes.join(' · ');
        marcarTarjetaEstado(estadoInicial);
        if (estadoModalSelect) {
            estadoModalSelect.querySelectorAll('option[data-extra="1"]').forEach(function(o) { o.remove(); });
            if (opts.asignadoId) {
                var existe = Array.prototype.some.call(estadoModalSelect.options, function(o) {
                    return o.value === String(opts.asignadoId);
                });
                if (!existe && opts.asignadoNombre) {
                    var extra = document.createElement('option');
                    extra.value = String(opts.asignadoId);
                    extra.textContent = opts.asignadoNombre;
                    extra.setAttribute('data-extra', '1');
                    estadoModalSelect.appendChild(extra);
                }
            }
            estadoModalSelect.value = opts.asignadoId || '';
        }
        if (solo) focoAlCerrarEstado = document.activeElement;
        estadoModalEl.classList.remove('hidden');
        estadoModalEl.setAttribute('aria-hidden', 'false');
        if (solo && estadoModalSelect) estadoModalSelect.focus();
    }

    document.querySelectorAll('.ticket-estado-card').forEach(function(card) {
        card.addEventListener('click', function() {
            marcarTarjetaEstado(this.getAttribute('data-estado') || '');
        });
    });

    function cerrarSiModalEstado(el) {
        if (el) el.addEventListener('click', cerrarModalEstadoTicket);
    }
    cerrarSiModalEstado(document.getElementById('ticket-estado-modal-backdrop'));
    cerrarSiModalEstado(document.getElementById('ticket-estado-modal-cerrar'));
    cerrarSiModalEstado(document.getElementById('ticket-estado-modal-cancelar'));

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (estadoModalEl && !estadoModalEl.classList.contains('hidden')) {
            cerrarModalEstadoTicket();
        }
    });

    if (estadoModalGuardar) {
        estadoModalGuardar.addEventListener('click', function() {
            if (!estadoModalCtx.estado && !estadoModalCtx.soloAsignar) {
                Swal.fire({ icon: 'warning', title: 'Elegí un estado', text: 'Marcá el estado de los tickets seleccionados.' });
                return;
            }
            var asignadoId = estadoModalSelect ? estadoModalSelect.value : '';
            if (estadoModalCtx.soloAsignar && !asignadoId) {
                Swal.fire({ icon: 'warning', title: 'Elegí un técnico', text: 'El ticket sigue sin asignación.' });
                return;
            }
            estadoModalGuardar.disabled = true;

            // Masivo
            if (estadoModalCtx.bulkIds && estadoModalCtx.bulkIds.length > 0) {
                ejecutarBulkUpdate({
                    accion: 'cambiar_estado',
                    ticket_ids: estadoModalCtx.bulkIds,
                    estado: estadoModalCtx.estado,
                    asignado_id: asignadoId ? Number(asignadoId) : null
                }).then(function() {
                    cerrarModalEstadoTicket();
                }).catch(function() {
                    estadoModalGuardar.disabled = false;
                });
                return;
            }

            // Individual
            if (!estadoModalCtx.url) return;
            var estadoGuardado = estadoModalCtx.estado;
            var ticketGuardado = estadoModalCtx.ticketId;
            var solo = estadoModalCtx.soloAsignar;
            var etiqueta = estadoGuardado;
            var cardActiva = document.querySelector('.ticket-estado-card[data-estado="' + estadoGuardado + '"] span.mt-1');
            if (cardActiva) etiqueta = cardActiva.textContent.trim();
            var nombreTecnico = '';
            if (estadoModalSelect && estadoModalSelect.selectedIndex >= 0 && asignadoId) {
                nombreTecnico = estadoModalSelect.options[estadoModalSelect.selectedIndex].text;
            }
            enviarEstadoTicket(estadoModalCtx.url, estadoGuardado, asignadoId).then(function() {
                if (asignadoId) pintarTecnicoEnFila(ticketGuardado, nombreTecnico);
                if (!solo) aplicarEstadoEnFila(ticketGuardado, estadoGuardado, etiqueta);
                cerrarModalEstadoTicket();
                Swal.fire({ icon: 'success', title: solo ? 'Técnico asignado' : 'Guardado', timer: 1200, showConfirmButton: false });
            }).catch(function() {
                estadoModalGuardar.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el ticket.' });
            });
        });
    }

    function ejecutarBulkUpdate(payload) {
        return fetch(bulkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function(r) {
            return r.json().then(function(data) {
                if (!r.ok) {
                    var msg = data.message || 'Error en la operación masiva.';
                    throw new Error(msg);
                }
                return data;
            });
        }).then(function(res) {
            return Swal.fire({
                icon: 'success',
                title: 'Operación masiva exitosa',
                text: res.message || 'Tickets actualizados correctamente.'
            }).then(function() {
                window.location.reload();
            });
        }).catch(function(err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || 'No se pudo completar la operación masiva.'
            });
            throw err;
        });
    }

    var resolverModalEl = document.getElementById('ticket-resolver-modal');
    var resolverTitleEl = document.getElementById('ticket-resolver-title');
    var resolverNotaEl = document.getElementById('ticket-resolver-nota');
    var resolverSaleEl = document.getElementById('ticket-resolver-sale');
    var resolverDeshacerEl = document.getElementById('ticket-resolver-deshacer');
    var resolverErrorEl = document.getElementById('ticket-resolver-error');
    var resolverCancelarEl = document.getElementById('ticket-resolver-cancelar');
    var resolverConfirmarEl = document.getElementById('ticket-resolver-confirmar');
    var resolverSinAvisarEl = document.getElementById('ticket-resolver-sin-avisar');
    var resolverAvisoEl = document.getElementById('ticket-resolver-aviso');
    var deshacerEl = document.getElementById('ticket-deshacer');
    var deshacerTextoEl = document.getElementById('ticket-deshacer-texto');
    var deshacerBtnEl = document.getElementById('ticket-deshacer-btn');
    var resolverCtx = null;
    var deshacerCtx = null;
    var deshacerTimer = null;

    function ajustarNumero(el, delta) {
        var n = parseInt(el.textContent, 10);
        if (isNaN(n)) return;
        el.textContent = String(Math.max(0, n + delta));
    }

    function ajustarChip(id, delta) {
        document.querySelectorAll('[data-chip-count="' + id + '"]').forEach(function(el) {
            ajustarNumero(el, delta);
        });
    }

    function ajustarBadgeMenu(delta) {
        document.querySelectorAll('a[href="/tickets"], a[href$="/tickets"]').forEach(function(a) {
            if (!a.pathname || a.pathname !== '/tickets') return;
            a.querySelectorAll('span.rounded-full').forEach(function(el) {
                ajustarNumero(el, delta);
            });
        });
    }

    function colaVisible() {
        var params = new URLSearchParams(window.location.search);
        var cola = params.get('cola') || '';
        if (!cola && params.get('ocultar_resuelto_cerrado') === '1' && !params.get('estado')) return 'en_curso';
        return cola;
    }

    function cerrarDeshacer(commit) {
        if (deshacerTimer) {
            clearTimeout(deshacerTimer);
            deshacerTimer = null;
        }
        if (deshacerEl) deshacerEl.classList.add('hidden');
        if (commit && deshacerCtx && !deshacerCtx.conteoHecho) {
            deshacerCtx.conteoHecho = true;
            ajustarChip('en_curso', -1);
            ajustarBadgeMenu(-1);
            if (deshacerCtx.estado === 'no_realizado') {
                var nr = document.getElementById('tickets-resumen-no-realizados');
                var nrPalabra = document.getElementById('tickets-resumen-no-realizados-palabra');
                if (nr) {
                    ajustarNumero(nr, -1);
                    var nNo = parseInt(nr.textContent, 10);
                    if (nrPalabra && !isNaN(nNo)) nrPalabra.textContent = nNo === 1 ? 'no realizado' : 'no realizados';
                }
            }
            if (deshacerCtx.estado === 'en_camino') ajustarChip('en_camino', -1);
            if (deshacerCtx.sinTecnico) {
                ajustarChip('sin_asignar', -1);
                var st = document.getElementById('tickets-resumen-sin-tecnico');
                if (st) ajustarNumero(st, -1);
            }
            var cola = colaVisible();
            if (cola && cola !== 'en_curso' && cola !== 'todos' && cola !== 'resueltos') {
                ajustarChip(cola, -1);
            }
        }
        if (commit && deshacerCtx && deshacerCtx.card && deshacerCtx.card.isConnected) {
            if (colaOcultaResueltos()) {
                quitarFilaOContar(deshacerCtx.card);
            } else {
                deshacerCtx.card.querySelectorAll('.btn-quick-resolver').forEach(function(b) { b.remove(); });
            }
        }
        deshacerCtx = null;
    }

    function iniciarDeshacer(ctx) {
        cerrarDeshacer(true);
        var card = filaTicket(ctx.ticketId);
        var badge = card && card.querySelector('[data-estado-badge]');
        var snapshot = badge ? { text: badge.textContent, className: badge.className } : null;
        if (badge) {
            badge.textContent = 'Resuelto';
            badge.className = claseBadgeEstado('resuelto');
        }
        if (card) {
            card.querySelectorAll('.btn-quick-resolver').forEach(function(b) { b.classList.add('hidden'); });
        }
        deshacerCtx = {
            url: ctx.url,
            estado: ctx.estado || 'pendiente',
            ticketId: ctx.ticketId,
            cliente: ctx.cliente || '',
            card: card,
            snapshot: snapshot,
            conteoHecho: false,
            sinTecnico: !!ctx.sinTecnico,
            sinAvisar: !!ctx.sinAvisar
        };
        var quien = deshacerCtx.cliente ? ' de ' + deshacerCtx.cliente : '';
        if (deshacerTextoEl) deshacerTextoEl.textContent = 'Ticket #' + (ctx.ticketId || '') + quien + (deshacerCtx.sinAvisar ? ' resuelto, sin aviso al cliente.' : ' resuelto.');
        if (deshacerEl) deshacerEl.classList.remove('hidden');
        if (deshacerBtnEl) deshacerBtnEl.focus();
        deshacerTimer = setTimeout(function() { cerrarDeshacer(true); }, 4000);
    }

    function deshacerResolucion() {
        if (!deshacerCtx) return;
        var ctx = deshacerCtx;
        if (deshacerTimer) {
            clearTimeout(deshacerTimer);
            deshacerTimer = null;
        }
        if (deshacerBtnEl) deshacerBtnEl.disabled = true;
        enviarEstadoTicket(ctx.url, ctx.estado, undefined, ctx.sinAvisar).then(function() {
            var badge = ctx.card && ctx.card.querySelector('[data-estado-badge]');
            if (badge && ctx.snapshot) {
                badge.textContent = ctx.snapshot.text;
                badge.className = ctx.snapshot.className;
            }
            if (ctx.card) {
                ctx.card.querySelectorAll('.btn-quick-resolver').forEach(function(b) { b.classList.remove('hidden'); });
            }
            deshacerCtx = null;
            if (deshacerEl) deshacerEl.classList.add('hidden');
        }).catch(function() {
            if (deshacerTextoEl) deshacerTextoEl.textContent = 'No se pudo deshacer. El ticket #' + (ctx.ticketId || '') + (ctx.cliente ? ' de ' + ctx.cliente : '') + ' sigue resuelto.';
            deshacerTimer = setTimeout(function() { cerrarDeshacer(true); }, 2500);
        }).finally(function() {
            if (deshacerBtnEl) deshacerBtnEl.disabled = false;
        });
    }

    function cerrarModalResolver() {
        if (!resolverModalEl) return;
        resolverModalEl.classList.add('hidden');
        resolverModalEl.setAttribute('aria-hidden', 'true');
        resolverCtx = null;
        if (resolverConfirmarEl) resolverConfirmarEl.disabled = false;
        if (resolverSinAvisarEl) resolverSinAvisarEl.disabled = false;
    }

    function abrirModalResolver(opts) {
        if (!resolverModalEl) return;
        resolverCtx = opts || {};
        var esLote = resolverCtx.bulkIds && resolverCtx.bulkIds.length > 0;
        var titulo = esLote
            ? ('Resolver ' + resolverCtx.bulkIds.length + (resolverCtx.bulkIds.length === 1 ? ' ticket' : ' tickets'))
            : ('Resolver #' + (resolverCtx.ticketId || ''));
        if (!esLote && resolverCtx.cliente) titulo += ' · ' + resolverCtx.cliente;
        if (!esLote && resolverCtx.asunto) titulo += ' · ' + resolverCtx.asunto;
        if (resolverTitleEl) resolverTitleEl.textContent = titulo;
        if (resolverNotaEl) {
            var partesNota = [];
            if (esLote) {
                partesNota.push('Pasan a Resuelto.');
            } else {
                var calle = '';
                var etiquetaEstado = resolverCtx.estadoEtiqueta || '';
                if (etiquetaEstado && etiquetaEstado !== 'Pendiente') calle = etiquetaEstado;
                var quien = resolverCtx.tecnico || (resolverCtx.sinTecnico ? 'Sin técnico' : '');
                if (quien) calle = calle ? (calle + ' · ' + quien) : quien;
                if (calle) partesNota.unshift(calle);
                if (resolverCtx.nota) partesNota.push(resolverCtx.nota);
                if (resolverCtx.via) partesNota.push('Vía ' + resolverCtx.via);
            }
            var nota = partesNota.join(' · ');
            resolverNotaEl.textContent = nota;
            resolverNotaEl.title = nota;
            resolverNotaEl.classList.toggle('hidden', nota === '');
        }
        if (resolverSaleEl) resolverSaleEl.textContent = esLote ? 'Salen de En curso.' : 'Sale de En curso.';
        if (resolverAvisoEl) resolverAvisoEl.textContent = esLote ? 'Este lote no avisa al cliente.' : 'Resolver avisa al cliente por la app y por WhatsApp.';
        if (resolverSinAvisarEl) {
            resolverSinAvisarEl.classList.toggle('hidden', !!esLote);
            resolverSinAvisarEl.disabled = false;
        }
        if (resolverDeshacerEl) resolverDeshacerEl.classList.toggle('hidden', !!esLote);
        if (resolverErrorEl) {
            resolverErrorEl.textContent = '';
            resolverErrorEl.classList.add('hidden');
        }
        resolverModalEl.classList.remove('hidden');
        resolverModalEl.setAttribute('aria-hidden', 'false');
        if (resolverCancelarEl) resolverCancelarEl.focus();
    }

    if (resolverCancelarEl) resolverCancelarEl.addEventListener('click', cerrarModalResolver);
    var resolverBackdrop = document.getElementById('ticket-resolver-backdrop');
    if (resolverBackdrop) resolverBackdrop.addEventListener('click', cerrarModalResolver);
    if (deshacerBtnEl) deshacerBtnEl.addEventListener('click', deshacerResolucion);
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (resolverModalEl && !resolverModalEl.classList.contains('hidden')) {
            e.preventDefault();
            cerrarModalResolver();
        }
    });
    function pedirResolver(sinAvisar) {
        if (!resolverCtx) return;
        var ctx = resolverCtx;
        var esLote = ctx.bulkIds && ctx.bulkIds.length;
        if (!esLote && !ctx.url) return;
        ctx.sinAvisar = !!sinAvisar;
        if (resolverConfirmarEl) resolverConfirmarEl.disabled = true;
        if (resolverSinAvisarEl) resolverSinAvisarEl.disabled = true;
        if (resolverErrorEl) resolverErrorEl.classList.add('hidden');
        var promesa = esLote
            ? ejecutarBulkUpdate({ accion: 'marcar_resuelto', ticket_ids: ctx.bulkIds })
            : enviarEstadoTicket(ctx.url, 'resuelto', undefined, sinAvisar).then(function() {
                cerrarModalResolver();
                iniciarDeshacer(ctx);
            });
        promesa.then(function() {
            if (esLote) cerrarModalResolver();
        }).catch(function() {
            if (resolverConfirmarEl) resolverConfirmarEl.disabled = false;
            if (resolverSinAvisarEl) resolverSinAvisarEl.disabled = false;
            if (resolverErrorEl) {
                resolverErrorEl.textContent = 'No se pudo actualizar el estado del ticket.';
                resolverErrorEl.classList.remove('hidden');
            }
        });
    }

    if (resolverConfirmarEl) resolverConfirmarEl.addEventListener('click', function() { pedirResolver(false); });
    if (resolverSinAvisarEl) resolverSinAvisarEl.addEventListener('click', function() { pedirResolver(true); });

})();
</script>
@endpush
@endsection
