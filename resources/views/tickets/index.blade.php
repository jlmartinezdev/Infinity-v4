@extends('layouts.app')

@section('title', 'Tickets')

@push('styles')
<style>
    mark.search-mark {
        background-color: #facc15;
        color: inherit;
        padding: 0 0.12em;
        border-radius: 0.15rem;
        box-decoration-break: clone;
        -webkit-box-decoration-break: clone;
    }
    .dark mark.search-mark {
        background-color: rgba(250, 204, 21, 0.45);
        color: inherit;
    }
    @keyframes fadeInUpBulk {
        from {
            opacity: 0;
            transform: translate(-50%, 20px);
        }
        to {
            opacity: 1;
            transform: translate(-50%, 0);
        }
    }
    .animate-bulk-bar {
        animation: fadeInUpBulk 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
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
    $cantidadFiltrosPanel = (int) (
        request()->filled('estado')
        + request()->filled('ticket_asunto_id')
        + request()->filled('asignado_id')
        + request()->filled('prioridad')
        + request()->boolean('sin_asignar')
        + request()->boolean('ocultar_resuelto_cerrado')
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
    $esSlaVencido = function (?string $estado, ?\Illuminate\Support\Carbon $fecha): bool {
        if (! $fecha || in_array($estado, ['resuelto', 'cerrado', 'cancelado'], true)) {
            return false;
        }
        return $fecha->diffInHours(now()) >= 24;
    };
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    {{-- Header principal --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                Tickets de Soporte
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">
                    {{ $kpis->abiertos ?? 0 }} en atención
                </span>
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mesa de ayuda, despacho a terreno y diagnóstico de red</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm" title="Recargar vista">
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

    {{-- Paquete A: Deck de Métricas NOC (KPIs) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Card 1: Abiertos Activos -->
        <a href="{{ route('tickets.index', ['cola' => 'abiertos']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $cola === 'abiertos' ? 'border-purple-500 bg-purple-50/70 dark:bg-purple-950/40 ring-2 ring-purple-500/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-purple-300 dark:hover:border-purple-700 hover:shadow' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-purple-600 dark:group-hover:text-purple-400">Abiertos</span>
                <span class="p-1 rounded-md bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $kpis->abiertos ?? 0 }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">En atención</p>
        </a>

        <!-- Card 2: Pendientes -->
        <a href="{{ route('tickets.index', ['cola' => 'pendientes']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $cola === 'pendientes' || $cola === 'pendiente' ? 'border-amber-500 bg-amber-50/70 dark:bg-amber-950/40 ring-2 ring-amber-500/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-amber-300 dark:hover:border-amber-700 hover:shadow' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400">Pendientes</span>
                <span class="p-1 rounded-md bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $kpis->pendientes ?? 0 }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Por despachar</p>
        </a>

        <!-- Card 3: En Camino -->
        <a href="{{ route('tickets.index', ['cola' => 'en_camino']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $cola === 'en_camino' ? 'border-cyan-500 bg-cyan-50/70 dark:bg-cyan-950/40 ring-2 ring-cyan-500/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-cyan-300 dark:hover:border-cyan-700 hover:shadow' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-cyan-600 dark:group-hover:text-cyan-400">En Camino</span>
                <span class="p-1 rounded-md bg-cyan-100 dark:bg-cyan-900/50 text-cyan-600 dark:text-cyan-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $kpis->en_camino ?? 0 }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">En terreno</p>
        </a>

        <!-- Card 4: Prioridad Alta -->
        <a href="{{ route('tickets.index', ['cola' => 'alta']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $cola === 'alta' ? 'border-red-500 bg-red-50/70 dark:bg-red-950/40 ring-2 ring-red-500/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-red-300 dark:hover:border-red-700 hover:shadow' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-red-600 dark:group-hover:text-red-400">Prioridad Alta</span>
                <span class="p-1 rounded-md bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-2 flex items-center gap-1.5">
                {{ $kpis->alta ?? 0 }}
                @if(($kpis->alta ?? 0) > 0)
                    <span class="inline-flex w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                @endif
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Urgentes</p>
        </a>

        <!-- Card 5: Sin Técnico Asignado -->
        <a href="{{ route('tickets.index', ['cola' => 'sin_asignar']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $cola === 'sin_asignar' ? 'border-orange-500 bg-orange-50/70 dark:bg-orange-950/40 ring-2 ring-orange-500/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-orange-300 dark:hover:border-orange-700 hover:shadow' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-orange-600 dark:group-hover:text-orange-400">Sin Técnico</span>
                <span class="p-1 rounded-md bg-orange-100 dark:bg-orange-900/50 text-orange-600 dark:text-orange-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-2">{{ $kpis->sin_asignar ?? 0 }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Por asignar</p>
        </a>

        <!-- Card 6: Resueltos -->
        <a href="{{ route('tickets.index', ['cola' => 'resueltos']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $cola === 'resueltos' ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/40 ring-2 ring-emerald-500/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">Resueltos</span>
                <span class="p-1 rounded-md bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">{{ $kpis->resueltos ?? 0 }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Hoy: +{{ $kpis->resueltos_hoy ?? 0 }}</p>
        </a>
    </div>

    {{-- Paquete A: Segmented Queue Tabs (1-Click) --}}
    @php
        $tabActiva = match(true) {
            $cola === 'abiertos' => 'abiertos',
            $cola === 'pendientes' || $cola === 'pendiente' || request('estado') === 'pendiente' => 'pendientes',
            $cola === 'en_camino' || request('estado') === 'en_camino' => 'en_camino',
            $cola === 'en_proceso' || request('estado') === 'en_proceso' => 'en_proceso',
            $cola === 'alta' => 'alta',
            $cola === 'sin_asignar' => 'sin_asignar',
            $cola === 'resueltos' || request('estado') === 'resuelto' => 'resueltos',
            default => 'todos',
        };
        $linkTab = fn ($c) => route('tickets.index', array_merge(request()->except(['cola', 'page', 'estado']), $c ? ['cola' => $c] : []));
    @endphp
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
        <a href="{{ $linkTab('abiertos') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'abiertos' ? 'bg-purple-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>⚡ Abiertos</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'abiertos' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->abiertos ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('pendientes') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'pendientes' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>⏳ Pendientes</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'pendientes' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->pendientes ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('en_camino') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'en_camino' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>🚗 En camino</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'en_camino' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->en_camino ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('en_proceso') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'en_proceso' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>⚙️ En proceso</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'en_proceso' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->en_proceso ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('alta') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'alta' ? 'bg-red-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>🚨 Prioridad Alta</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'alta' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->alta ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('sin_asignar') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'sin_asignar' ? 'bg-orange-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>👤 Sin técnico</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'sin_asignar' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->sin_asignar ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('resueltos') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'resueltos' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>✅ Resueltos</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'resueltos' ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->resueltos ?? 0 }}</span>
        </a>

        <a href="{{ $linkTab('') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors shrink-0 {{ $tabActiva === 'todos' ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900 shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            <span>📋 Todos</span>
            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] {{ $tabActiva === 'todos' ? 'bg-white/20 text-white dark:bg-black/20 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $kpis->total ?? 0 }}</span>
        </a>
    </div>

    {{-- Contenedor principal de lista y filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
        {{-- Barra de búsqueda y selector de filtros --}}
        <form method="GET" action="{{ route('tickets.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-t-xl">
            @if($clienteFiltro && $busqueda === '')
                <input type="hidden" name="cliente_id" value="{{ $clienteFiltro->cliente_id }}">
            @endif
            @if($cola)
                <input type="hidden" name="cola" value="{{ $cola }}">
            @endif
            <input type="hidden" name="per_page" value="{{ $tickets->perPage() }}">

            <div class="flex items-center gap-3">
                {{-- Paquete C: Checkbox Seleccionar Todos --}}
                <div class="flex items-center shrink-0 pl-1" title="Seleccionar todos los tickets de esta página">
                    <input type="checkbox" id="ticket-select-all"
                        class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 w-4 h-4 cursor-pointer"
                        aria-label="Seleccionar todos los tickets">
                </div>

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
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold bg-white text-purple-700">{{ $cantidadFiltrosPanel }}</span>
                        @endif
                    </button>
                    <div
                        id="ticket-filtros-menu"
                        class="hidden absolute right-0 mt-2 w-80 max-w-sm py-3 px-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl z-30"
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
                                        <option value="{{ $a->id }}" {{ request('ticket_asunto_id') == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Técnico Asignado</label>
                                <select name="asignado_id" aria-label="Técnico"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos los técnicos</option>
                                    @foreach ($tecnicos as $t)
                                        <option value="{{ $t->usuario_id }}" {{ (string) request('asignado_id') === (string) $t->usuario_id ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
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
        <div class="divide-y divide-gray-200 dark:divide-gray-700" id="tickets-lista-container">
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
                    $estados = App\Models\Ticket::estados();
                    $prioridades = App\Models\Ticket::prioridades();
                    $reportado = App\Models\Ticket::reportadoDesdeOpciones();
                    $esActivo = ! in_array($ticket->estado, ['resuelto', 'cerrado', 'cancelado'], true);
                    $mapsUrl = $mapsUrlCliente($ticket->cliente);

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
                        'cliente' => $nombreClienteTicket !== '' ? $nombreClienteTicket : null,
                        'cliente_cedula' => $ticket->cliente?->cedula,
                        'cliente_telefono' => $ticket->cliente?->telefono,
                        'ip_cliente' => $ipClienteTicket,
                        'pedido_id' => $ticket->pedido_id,
                        'descripcion' => $ticket->descripcion,
                        'observaciones' => $ticket->observaciones,
                        'nota_tecnico' => $ticket->nota_tecnico,
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
                        'ticket_id' => $ticket->id,
                        'cliente' => $nombreClienteTicket !== '' ? $nombreClienteTicket : null,
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
                    ];
                @endphp

                <article class="ticket-row p-3.5 sm:p-4 transition-colors {{ $loop->even ? 'bg-gray-50/70 dark:bg-gray-800/40' : 'bg-white dark:bg-gray-800' }} hover:bg-gray-100/80 dark:hover:bg-gray-700/50" data-id="{{ $ticket->id }}">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        {{-- Columna 1: Checkbox + ID + Asunto + Badges de Estado y Prioridad --}}
                        <div class="md:col-span-4 min-w-0 flex items-start gap-2.5">
                            {{-- Checkbox selección masiva --}}
                            <div class="pt-0.5 shrink-0">
                                <input type="checkbox"
                                    class="ticket-row-check rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 w-4 h-4 cursor-pointer"
                                    value="{{ $ticket->id }}"
                                    aria-label="Seleccionar ticket #{{ $ticket->id }}">
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-mono text-xs font-semibold text-gray-400 dark:text-gray-500">#{!! $resaltar((string) $ticket->id) !!}</span>
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" title="{{ $ticket->ticketAsunto?->nombre ?? '—' }}">
                                        {!! $resaltar($ticket->ticketAsunto?->nombre ?? '—') !!}
                                    </p>
                                </div>

                                {{-- Badges en fila principal: Estado + Prioridad + SLA --}}
                                <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                    {{-- Estado --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        @if($ticket->estado === 'resuelto' || $ticket->estado === 'cerrado') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300
                                        @elseif($ticket->estado === 'cancelado') bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300
                                        @elseif($ticket->estado === 'en_proceso') bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300
                                        @elseif($ticket->estado === 'en_camino') bg-cyan-100 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-300
                                        @else bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 @endif">
                                        {{ $estados[$ticket->estado] ?? $ticket->estado }}
                                    </span>

                                    {{-- Prioridad visible directamente --}}
                                    @if(($ticket->prioridad ?? 'media') === 'alta')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-bold bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200 border border-red-200 dark:border-red-800" title="Prioridad Alta">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></span>
                                            Alta
                                        </span>
                                    @elseif(($ticket->prioridad ?? '') === 'baja')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300" title="Prioridad Baja">
                                            Baja
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="Prioridad Media">
                                            Media
                                        </span>
                                    @endif

                                    {{-- Alerta de SLA (>24h sin resolver) --}}
                                    @if($esSlaVencido($ticket->estado, $ticket->created_at))
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200" title="Ticket activo creado hace más de 24 horas">
                                            ⏰ >24h
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Columna 2: Cliente + Cédula + Teléfono + IP --}}
                        <div class="md:col-span-4 min-w-0">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <div class="min-w-0">
                                    @if($ticket->cliente)
                                        <a href="{{ route('clientes.detalle', $ticket->cliente) }}" class="inline-block text-sm font-semibold text-purple-600 dark:text-purple-400 hover:underline truncate max-w-full" title="Ver ficha del cliente">{!! $resaltar($nombreClienteTicket) !!}</a>
                                        <div class="flex items-center gap-2 flex-wrap text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            @if($ticket->cliente->cedula)
                                                <span>CI: {!! $resaltar($ticket->cliente->cedula) !!}</span>
                                            @endif
                                            @if($ticket->cliente->telefono)
                                                <span>· Tel: {!! $resaltar($ticket->cliente->telefono) !!}</span>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">—</p>
                                    @endif

                                    @if($ticket->pedido_id)
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Pedido #{!! $resaltar((string) $ticket->pedido_id) !!}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Columna 3: Técnico Asignado + Fecha y Antigüedad --}}
                        <div class="md:col-span-2 min-w-0">
                            {{-- Técnico Asignado Chip directo --}}
                            <div>
                                @if($ticket->asignado)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700/60 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600" title="Técnico asignado">
                                        <svg class="w-3.5 h-3.5 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span class="truncate max-w-[120px]">{!! $resaltar($ticket->asignado->name) !!}</span>
                                    </span>
                                @else
                                    <button type="button" class="btn-asignar-rapido inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border border-orange-200 dark:border-orange-800 hover:bg-orange-200 transition-colors"
                                        title="Click para asignar técnico"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-url="{{ route('tickets.update-estado', $ticket) }}"
                                        data-estado="{{ $ticket->estado }}"
                                        data-cliente="{{ $nombreClienteTicket }}">
                                        <span>⚠️ Sin técnico</span>
                                    </button>
                                @endif
                            </div>

                            {{-- Fecha / Antigüedad --}}
                            <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                @if($ticket->created_at)
                                    <span class="text-gray-400 dark:text-gray-500">· {{ $etiquetaAntiguedad($ticket->created_at) }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Columna 4: Barra de Herramientas y Acciones --}}
                        <div class="md:col-span-2 flex items-center justify-end gap-1 flex-wrap">
                            {{-- Botón rápido: Resolver ticket (solo si está activo) --}}
                            @if($esActivo)
                                <button type="button"
                                    class="btn-quick-resolver p-2 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 transition-colors"
                                    title="Marcar como resuelto"
                                    aria-label="Marcar como resuelto"
                                    data-url="{{ route('tickets.update-estado', $ticket) }}"
                                    data-ticket-id="{{ $ticket->id }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            @endif

                            {{-- IP / CPE Link --}}
                            @if($ipClienteTicket)
                                <a href="http://{{ $ipClienteTicket }}" target="_blank" rel="noopener noreferrer"
                                    class="p-2 rounded-lg text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 transition-colors"
                                    title="Abrir equipo {{ $ipClienteTicket }}"
                                    aria-label="Abrir IP del cliente">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                </a>
                            @endif

                            {{-- Maps GPS Link --}}
                            @if($mapsUrl)
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                                    class="p-2 rounded-lg text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors"
                                    title="Abrir ubicación en Google Maps"
                                    aria-label="Abrir ubicación en Google Maps">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </a>
                            @endif

                            {{-- Botón: Ver observaciones / Telemetría --}}
                            <button type="button"
                                class="btn-ver-detalle-ticket p-2 rounded-lg relative {{ filled($ticket->observaciones) || filled($ticket->descripcion) || !empty($ticket->datos_diagnostico) ? 'text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700' }} transition-colors"
                                title="Ver notas y telemetría"
                                aria-label="Ver notas y telemetría"
                                data-detalle-b64="{{ base64_encode(json_encode($detalleTicket, JSON_UNESCAPED_UNICODE)) }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                @if(!empty($ticket->datos_diagnostico))
                                    <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-emerald-500" title="Telemetría de app disponible"></span>
                                @endif
                            </button>

                            {{-- Kebab Menú de Acciones completas --}}
                            <button type="button"
                                class="ticket-acciones-kebab p-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                title="Más opciones"
                                aria-label="Menú de acciones"
                                aria-expanded="false"
                                data-menu-b64="{{ base64_encode(json_encode($menuCfg, JSON_UNESCAPED_UNICODE)) }}">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                            </button>

                            {{-- Chevron desplegar datos secundarios --}}
                            <button type="button"
                                class="ticket-expandir-btn p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                title="Más detalles"
                                aria-label="Mostrar más detalles"
                                aria-expanded="false"
                                aria-controls="ticket-extra-{{ $ticket->id }}">
                                <svg class="ticket-expandir-icon w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Acordeón de datos secundarios colapsables --}}
                    <div id="ticket-extra-{{ $ticket->id }}" class="hidden mt-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-3 grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
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
                    <div class="min-w-0">{{ $tickets->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- Paquete C: Barra Flotante de Acciones Masivas --}}
<div id="tickets-bulk-bar" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden max-w-2xl w-[94vw] bg-gray-900/95 text-white backdrop-blur-md rounded-2xl px-5 py-3.5 shadow-2xl border border-gray-700 flex flex-wrap items-center justify-between gap-3 animate-bulk-bar">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-600 text-xs font-bold" id="bulk-selected-badge">0</span>
        <span id="bulk-selected-count" class="font-bold text-sm text-gray-100">0 tickets seleccionados</span>
        <button type="button" id="bulk-btn-deseleccionar" class="text-xs text-gray-400 hover:text-white underline cursor-pointer">Deseleccionar</button>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <button type="button" id="bulk-btn-asignar" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Asignar técnico
        </button>
        <button type="button" id="bulk-btn-estado" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-xs font-semibold text-white shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            Cambiar estado
        </button>
        <button type="button" id="bulk-btn-resolver" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Resolver seleccionados
        </button>
    </div>
</div>

{{-- Menú acciones kebab flotante --}}
<div id="ticket-acciones-dropdown" class="fixed z-[9999] hidden py-1 min-w-[220px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-xl" role="menu" aria-hidden="true"></div>

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
        <div class="w-full max-w-xl rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <h3 id="ticket-estado-modal-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">Cambiar estado</h3>
                    <p id="ticket-estado-modal-sub" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"></p>
                </div>
                <button type="button" id="ticket-estado-modal-cerrar" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Seleccione el estado</p>
            <div id="ticket-estado-cards" class="grid grid-cols-4 gap-2 mb-4">
                @foreach (App\Models\Ticket::estados() as $key => $label)
                    <button type="button"
                        class="ticket-estado-card relative h-20 w-full rounded-xl border p-2 cursor-pointer flex flex-col items-center justify-center transition-all {{ $claseEstadoTarjeta($key) }}"
                        data-estado="{{ $key }}">
                        <span class="ticket-estado-check hidden absolute top-1 right-2 w-4 h-4 rounded-full bg-purple-600 text-white flex items-center justify-center pointer-events-none" aria-hidden="true">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">{!! $iconoEstadoTarjeta($key) !!}</svg>
                        <span class="mt-1 block text-xs font-semibold leading-tight text-center">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            <label for="ticket-estado-tecnico" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Técnico Asignado</label>
            <select id="ticket-estado-tecnico"
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                <option value="">Sin asignar / Mantener asignación</option>
                @foreach ($tecnicos as $t)
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
        btn.setAttribute('aria-label', abierto ? 'Ocultar detalles' : 'Mostrar más detalles');
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
            if (e.key === 'Escape') setFiltrosOpen(false);
        });
    }

    // Persistencia del checkbox ocultar resuelto/cerrado
    var filtroOcultarCheck = document.querySelector('input[name="ocultar_resuelto_cerrado"]');
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
            html += '<p class="' + (isDark ? 'text-gray-300' : 'text-gray-600') + ' font-medium">' + escapeHtml(t.asunto) + (t.cliente ? ' · <span class="text-purple-500 font-bold">' + escapeHtml(t.cliente) + '</span>' : '') + '</p>';
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
        var baseBtn = 'w-full px-4 py-2.5 text-left text-sm flex items-center gap-2 transition-colors';
        var h = '';
        if (cfg.herramientas_red_url) {
            h += '<a href="' + escapeHtml(cfg.herramientas_red_url) + '" class="block ' + baseBtn + ' text-teal-700 dark:text-teal-300 hover:bg-teal-50 dark:hover:bg-teal-900/30">' + icWifi + ' Herramientas de red</a>';
        }
        h += '<button type="button" class="' + baseBtn + ' text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 ticket-menu-item" data-accion="estado" data-url="' + escapeHtml(cfg.update_estado_url) + '" data-estado="' + escapeHtml(cfg.estado || '') + '" data-asignado-id="' + escapeHtml(cfg.asignado_id != null ? String(cfg.asignado_id) : '') + '" data-ticket-id="' + escapeHtml(cfg.ticket_id != null ? String(cfg.ticket_id) : '') + '" data-cliente="' + escapeHtml(cfg.cliente || '') + '">' + icLista + ' Cambiar estado</button>';
        if (cfg.puede_facturar_ticket && cfg.facturar_url) {
            h += '<button type="button" class="' + baseBtn + ' text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 ticket-menu-item" data-accion="facturar-ticket" data-facturar-url="' + escapeHtml(cfg.facturar_url) + '">' + icDoc + ' Crear factura por ticket</button>';
        }
        if (cfg.puede_marcar_resuelto) {
            h += '<button type="button" class="' + baseBtn + ' text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 ticket-menu-item" data-accion="resuelto" data-url="' + escapeHtml(cfg.update_estado_url) + '" data-ticket-id="' + escapeHtml(cfg.ticket_id != null ? String(cfg.ticket_id) : '') + '">' + icCheck + ' Marcar como resuelto</button>';
        }
        h += '<a href="' + escapeHtml(cfg.edit_ticket_url) + '" class="block ' + baseBtn + ' text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30">' + icEdit + ' Editar</a>';
        h += '<a href="' + escapeHtml(cfg.agenda_url) + '" class="block ' + baseBtn + ' text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">' + icCal + ' Crear cita en agenda</a>';
        if (cfg.imagen_url) {
            h += '<a href="' + escapeHtml(cfg.imagen_url) + '" target="_blank" rel="noopener" class="block ' + baseBtn + ' text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30">' + icImg + ' Ver imagen</a>';
        }
        h += '<form method="POST" action="' + escapeHtml(cfg.destroy_url) + '" class="block ticket-menu-eliminar-form" onsubmit="return confirm(\'¿Eliminar este ticket?\');"><input type="hidden" name="_token" value="' + escapeHtml(cfg.csrf) + '"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="' + baseBtn + ' text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">' + icTrash + ' Eliminar</button></form>';
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
            if (ticketMenuOpenBtn === this) {
                cerrarMenuTicketAcciones();
                return;
            }
            cerrarMenuTicketAcciones();
            ticketMenuOpenBtn = this;
            this.setAttribute('aria-expanded', 'true');
            ticketMenuEl.innerHTML = construirHtmlMenuTicket(cfg);
            posicionarMenuTicketAcciones(this);
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
            } else if (accion === 'estado') {
                e.preventDefault();
                cerrarMenuTicketAcciones();
                abrirModalEstadoTicket({
                    url: t.getAttribute('data-url'),
                    estado: t.getAttribute('data-estado') || '',
                    asignadoId: t.getAttribute('data-asignado-id') || '',
                    ticketId: t.getAttribute('data-ticket-id') || '',
                    cliente: t.getAttribute('data-cliente') || ''
                });
            } else if (accion === 'resuelto') {
                e.preventDefault();
                var urlR = t.getAttribute('data-url');
                var tid = t.getAttribute('data-ticket-id');
                cerrarMenuTicketAcciones();
                confirmarYResolverTicket(urlR, tid);
            }
        });
    }

    // Paquete B: Botón Rápido "Resolver" directo en fila
    function confirmarYResolverTicket(url, ticketId) {
        Swal.fire({
            title: '¿Marcar como resuelto?',
            text: 'El ticket #' + (ticketId || '') + ' pasará al estado Resuelto.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, marcar resuelto',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            enviarEstadoTicket(url, 'resuelto').then(function() {
                return Swal.fire({ icon: 'success', title: '¡Resuelto!', text: 'El ticket fue marcado como resuelto correctamente.' });
            }).then(function() {
                window.location.reload();
            }).catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el estado del ticket.' });
            });
        });
    }

    document.querySelectorAll('.btn-quick-resolver').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var url = this.getAttribute('data-url');
            var tid = this.getAttribute('data-ticket-id');
            confirmarYResolverTicket(url, tid);
        });
    });

    // Clic rápido en "Sin técnico" para asignar inmediatamente
    document.querySelectorAll('.btn-asignar-rapido').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            abrirModalEstadoTicket({
                url: this.getAttribute('data-url'),
                estado: this.getAttribute('data-estado') || '',
                asignadoId: '',
                ticketId: this.getAttribute('data-ticket-id') || '',
                cliente: this.getAttribute('data-cliente') || ''
            });
        });
    });

    function enviarEstadoTicket(url, nuevoEstado, asignadoId) {
        var body = { estado: nuevoEstado };
        if (typeof asignadoId !== 'undefined') {
            body.asignado_id = (asignadoId === '' || asignadoId === null) ? null : Number(asignadoId);
        }
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
    var estadoModalSub = document.getElementById('ticket-estado-modal-sub');
    var estadoModalSelect = document.getElementById('ticket-estado-tecnico');
    var estadoModalGuardar = document.getElementById('ticket-estado-modal-guardar');
    var estadoModalCtx = { url: '', estado: '', bulkIds: [] };
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
        estadoModalCtx = { url: '', estado: '', bulkIds: [] };
        if (estadoModalGuardar) estadoModalGuardar.disabled = false;
    }

    function abrirModalEstadoTicket(opts) {
        if (!estadoModalEl) return;
        estadoModalCtx = {
            url: opts.url || '',
            estado: opts.estado || '',
            bulkIds: opts.bulkIds || []
        };
        var partes = [];
        if (opts.bulkIds && opts.bulkIds.length > 0) {
            partes.push(opts.bulkIds.length + ' tickets seleccionados');
        } else {
            if (opts.ticketId) partes.push('Ticket #' + opts.ticketId);
            if (opts.cliente) partes.push(opts.cliente);
        }
        if (estadoModalSub) estadoModalSub.textContent = partes.join(' · ');
        marcarTarjetaEstado(opts.estado || '');
        if (estadoModalSelect) estadoModalSelect.value = opts.asignadoId || '';
        estadoModalEl.classList.remove('hidden');
        estadoModalEl.setAttribute('aria-hidden', 'false');
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
            if (!estadoModalCtx.estado) {
                Swal.fire({ icon: 'warning', title: 'Seleccione un estado', text: 'Elija el estado para los tickets.' });
                return;
            }
            var asignadoId = estadoModalSelect ? estadoModalSelect.value : '';
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
            enviarEstadoTicket(estadoModalCtx.url, estadoModalCtx.estado, asignadoId).then(function() {
                cerrarModalEstadoTicket();
                return Swal.fire({ icon: 'success', title: 'Guardado', text: 'Estado y técnico actualizados.' });
            }).then(function() {
                window.location.reload();
            }).catch(function() {
                estadoModalGuardar.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el ticket.' });
            });
        });
    }

    // ==========================================
    // Paquete C: Operaciones Masivas (Bulk Ops)
    // ==========================================
    var selectAllCheck = document.getElementById('ticket-select-all');
    var rowChecks = document.querySelectorAll('.ticket-row-check');
    var bulkBar = document.getElementById('tickets-bulk-bar');
    var bulkCountText = document.getElementById('bulk-selected-count');
    var bulkBadge = document.getElementById('bulk-selected-badge');
    var btnDeseleccionar = document.getElementById('bulk-btn-deseleccionar');
    var btnBulkAsignar = document.getElementById('bulk-btn-asignar');
    var btnBulkEstado = document.getElementById('bulk-btn-estado');
    var btnBulkResolver = document.getElementById('bulk-btn-resolver');

    function getSelectedTicketIds() {
        var ids = [];
        rowChecks.forEach(function(c) {
            if (c.checked) ids.push(Number(c.value));
        });
        return ids;
    }

    function actualizarBarraMasiva() {
        var ids = getSelectedTicketIds();
        var n = ids.length;
        if (n > 0) {
            bulkBar.classList.remove('hidden');
            bulkCountText.textContent = n + ' ticket' + (n === 1 ? '' : 's') + ' seleccionado' + (n === 1 ? '' : 's');
            if (bulkBadge) bulkBadge.textContent = n;
        } else {
            bulkBar.classList.add('hidden');
        }
        if (selectAllCheck) {
            selectAllCheck.checked = (n > 0 && n === rowChecks.length);
            selectAllCheck.indeterminate = (n > 0 && n < rowChecks.length);
        }
    }

    if (selectAllCheck) {
        selectAllCheck.addEventListener('change', function() {
            var val = this.checked;
            rowChecks.forEach(function(c) {
                c.checked = val;
            });
            actualizarBarraMasiva();
        });
    }

    rowChecks.forEach(function(c) {
        c.addEventListener('change', function() {
            actualizarBarraMasiva();
        });
    });

    if (btnDeseleccionar) {
        btnDeseleccionar.addEventListener('click', function() {
            rowChecks.forEach(function(c) { c.checked = false; });
            if (selectAllCheck) {
                selectAllCheck.checked = false;
                selectAllCheck.indeterminate = false;
            }
            actualizarBarraMasiva();
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

    // Masivo: Asignar a técnico
    if (btnBulkAsignar) {
        btnBulkAsignar.addEventListener('click', function() {
            var ids = getSelectedTicketIds();
            if (ids.length === 0) return;

            // Clonar opciones de técnicos
            var tecnicoSelectHtml = '<select id="swal-bulk-tecnico" class="swal2-input !mt-2 !w-full"><option value="">-- Sin asignar (Desasignar) --</option>';
            @foreach($tecnicos as $t)
                tecnicoSelectHtml += '<option value="{{ $t->usuario_id }}">{{ addslashes($t->name) }}</option>';
            @endforeach
            tecnicoSelectHtml += '</select>';

            Swal.fire({
                title: 'Asignar técnico masivo',
                html: '<p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Seleccione el técnico para ' + ids.length + ' tickets:</p>' + tecnicoSelectHtml,
                showCancelButton: true,
                confirmButtonText: 'Asignar a cuadrilla',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#6366f1',
                preConfirm: function() {
                    var el = document.getElementById('swal-bulk-tecnico');
                    return el ? el.value : '';
                }
            }).then(function(res) {
                if (!res.isConfirmed) return;
                var asignadoId = res.value ? Number(res.value) : null;
                ejecutarBulkUpdate({
                    accion: 'asignar_tecnico',
                    ticket_ids: ids,
                    asignado_id: asignadoId
                });
            });
        });
    }

    // Masivo: Cambiar estado
    if (btnBulkEstado) {
        btnBulkEstado.addEventListener('click', function() {
            var ids = getSelectedTicketIds();
            if (ids.length === 0) return;
            abrirModalEstadoTicket({
                bulkIds: ids,
                estado: 'en_camino'
            });
        });
    }

    // Masivo: Resolver seleccionados
    if (btnBulkResolver) {
        btnBulkResolver.addEventListener('click', function() {
            var ids = getSelectedTicketIds();
            if (ids.length === 0) return;

            Swal.fire({
                title: '¿Resolver ' + ids.length + ' tickets?',
                text: 'Todos los tickets seleccionados pasarán inmediatamente al estado Resuelto.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, resolver lote',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280'
            }).then(function(res) {
                if (!res.isConfirmed) return;
                ejecutarBulkUpdate({
                    accion: 'marcar_resuelto',
                    ticket_ids: ids
                });
            });
        });
    }

})();
</script>
@endpush
@endsection
