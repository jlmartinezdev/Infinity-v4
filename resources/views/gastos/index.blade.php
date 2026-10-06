@extends('layouts.app')

@section('title', 'Gastos')

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
</style>
@endpush

@section('content')
@php
    $busqueda = request('q', '');
    $resaltar = function (?string $texto) use ($busqueda): string {
        if (! filled($texto)) return '—';
        if (! filled($busqueda)) return e($texto);
        return \App\Support\SearchHighlight::html($texto, $busqueda);
    };

    $formatoGs = fn ($monto) => 'Gs. ' . number_format((float) ($monto ?? 0), 0, ',', '.');

    $categoriaBadgeClase = function (?string $nombre): string {
        $n = mb_strtolower(trim($nombre ?? ''));
        return match (true) {
            str_contains($n, 'combustible') => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            str_contains($n, 'operativo') => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            str_contains($n, 'mantenimiento') => 'bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300 border-teal-200 dark:border-teal-800',
            str_contains($n, 'servicio') => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            str_contains($n, 'comida') => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-300 border-gray-200 dark:border-gray-600',
        };
    };

    $metodoPagoIcono = function (?string $metodo): string {
        return match (mb_strtolower(trim($metodo ?? ''))) {
            'efectivo' => '💵 Efectivo',
            'transferencia' => '🏦 Transferencia',
            'tarjeta' => '💳 Tarjeta',
            'cheque' => '📝 Cheque',
            default => ucfirst($metodo ?? 'Pagado'),
        };
    };

    $filtrosActivos = (int) (
        request()->filled('q')
        + request()->filled('desde')
        + request()->filled('hasta')
        + request()->filled('categoria_gasto_id')
        + request()->filled('proveedor_id')
        + (request()->filled('pagado') && request('pagado') !== 'todos')
    );
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    {{-- Header Principal --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                Control de Gastos
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">
                    {{ $cantidadFiltrada }} registro{{ $cantidadFiltrada === 1 ? '' : 's' }}
                </span>
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Egresos operativos, compras menores, viáticos y pagos a proveedores</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('categorias-gasto.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm transition-colors"
                title="Administrar categorías">
                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                <span>Categorías</span>
            </a>
            <a href="{{ route('gastos.exportar-excel', request()->query()) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm transition-colors">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Exportar Excel</span>
            </a>
            <a href="{{ route('gastos.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo gasto</span>
            </a>
        </div>
    </div>

    {{-- Paquete A: Deck Financiero de KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- Card 1: Gastos Hoy -->
        <a href="{{ route('gastos.index', ['desde' => now()->toDateString(), 'hasta' => now()->toDateString()]) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-blue-400 dark:hover:border-blue-600 hover:shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400">Gastos Hoy</span>
                <span class="p-1 rounded-md bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 font-mono">{{ $formatoGs($gastoHoy) }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ now()->format('d/m/Y') }}</p>
        </a>

        <!-- Card 2: Esta Semana -->
        <a href="{{ route('gastos.index', ['desde' => now()->startOfWeek()->toDateString(), 'hasta' => now()->endOfWeek()->toDateString()]) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-amber-400 dark:hover:border-amber-600 hover:shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400">Esta Semana</span>
                <span class="p-1 rounded-md bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 font-mono">{{ $formatoGs($gastoSemana) }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Semana actual</p>
        </a>

        <!-- Card 3: Este Mes -->
        <a href="{{ route('gastos.index', ['desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->endOfMonth()->toDateString()]) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-rose-400 dark:hover:border-rose-600 hover:shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-rose-600 dark:group-hover:text-rose-400">Este Mes</span>
                <span class="p-1 rounded-md bg-rose-100 dark:bg-rose-900/50 text-rose-600 dark:text-rose-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 font-mono">{{ $formatoGs($gastoMes) }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 capitalize">{{ now()->translatedFormat('F Y') }}</p>
        </a>

        <!-- Card 4: Pendientes de Pago -->
        <a href="{{ route('gastos.index', ['pagado' => 'no']) }}"
           class="group relative overflow-hidden rounded-xl border p-3.5 transition-all shadow-sm {{ $countPendientes > 0 ? 'border-orange-300 dark:border-orange-800 bg-orange-50/60 dark:bg-orange-950/30' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' }} hover:shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-orange-600 dark:group-hover:text-orange-400">Por Pagar</span>
                <span class="p-1 rounded-md bg-orange-100 dark:bg-orange-900/50 text-orange-600 dark:text-orange-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold {{ $countPendientes > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-gray-100' }} mt-2 font-mono">{{ $formatoGs($gastoPendiente) }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $countPendientes }} gasto{{ $countPendientes === 1 ? '' : 's' }} sin saldar</p>
        </a>

        <!-- Card 5: Total Filtrado Dinámico -->
        <div class="relative overflow-hidden rounded-xl border p-3.5 shadow-sm border-purple-200 dark:border-purple-800 bg-purple-50/70 dark:bg-purple-950/40 col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-purple-700 dark:text-purple-300">Total Filtrado</span>
                <span class="p-1 rounded-md bg-purple-200 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-purple-900 dark:text-purple-200 mt-2 font-mono">{{ $formatoGs($totalFiltrado) }}</p>
            <p class="text-[11px] text-purple-600 dark:text-purple-400 mt-0.5">{{ $cantidadFiltrada }} registro{{ $cantidadFiltrada === 1 ? '' : 's' }} listados</p>
        </div>
    </div>

    {{-- Paquete A: Desglose por Categoría del Mes (Interactivo con 1-clic) --}}
    @if(isset($desgloseCategoriasMes) && $desgloseCategoriasMes->count() > 0 && $gastoMes > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Distribución de Gastos — {{ ucfirst(now()->translatedFormat('F Y')) }}</span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Total mes: <strong class="font-mono text-gray-900 dark:text-gray-100">{{ $formatoGs($gastoMes) }}</strong></span>
            </div>

            {{-- Barra de progreso segmentada --}}
            <div class="w-full h-3 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden flex shadow-inner">
                @php
                    $coloresBarra = ['bg-amber-500', 'bg-blue-500', 'bg-teal-500', 'bg-purple-500', 'bg-emerald-500', 'bg-rose-500', 'bg-gray-400'];
                @endphp
                @foreach($desgloseCategoriasMes as $idx => $d)
                    @php
                        $porc = round(($d->total / $gastoMes) * 100, 1);
                        $bg = $coloresBarra[$idx % count($coloresBarra)];
                    @endphp
                    @if($porc > 0)
                        <div class="{{ $bg }} h-full transition-all duration-300" style="width: {{ $porc }}%" title="{{ $d->categoria?->nombre ?? 'Otros' }}: {{ $porc }}% ({{ $formatoGs($d->total) }})"></div>
                    @endif
                @endforeach
            </div>

            {{-- Chips interactivos de categorías --}}
            <div class="mt-3 flex items-center gap-2 flex-wrap">
                @foreach($desgloseCategoriasMes as $idx => $d)
                    @php
                        $porc = round(($d->total / $gastoMes) * 100, 1);
                        $dotBg = $coloresBarra[$idx % count($coloresBarra)];
                        $esFiltroActivo = (string) request('categoria_gasto_id') === (string) $d->categoria_gasto_id;
                    @endphp
                    <a href="{{ route('gastos.index', array_merge(request()->except(['categoria_gasto_id', 'page']), $esFiltroActivo ? [] : ['categoria_gasto_id' => $d->categoria_gasto_id])) }}"
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs transition-colors border {{ $esFiltroActivo ? 'border-purple-600 bg-purple-50 dark:bg-purple-950/60 font-bold text-purple-700 dark:text-purple-300 ring-2 ring-purple-500/20' : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                       title="Click para filtrar por {{ $d->categoria?->nombre ?? 'Otros' }}">
                        <span class="w-2 h-2 rounded-full {{ $dotBg }}"></span>
                        <span>{{ $d->categoria?->nombre ?? 'Sin categoría' }}</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100">{{ $porc }}%</span>
                        <span class="text-gray-400 dark:text-gray-500 font-mono">({{ $formatoGs($d->total) }})</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Paquete B: Contenedor Principal con Búsqueda y Filtros Inteligentes --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('gastos.index') }}" id="form-filtros-gastos" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/30 space-y-3">
            <input type="hidden" name="per_page" value="{{ $gastos->perPage() }}">

            {{-- Fila superior: Input de búsqueda rápida y botón filtrar --}}
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex-1 min-w-[260px] relative rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-500/20 shadow-sm flex items-center">
                    <span class="pl-3 text-gray-400 dark:text-gray-500 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    </span>
                    <input type="search" name="q" id="filtro-q" value="{{ request('q') }}"
                        placeholder="Buscar por concepto, detalle, referencia o proveedor..."
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2 pr-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0">
                    @if(request('q'))
                        <a href="{{ route('gastos.index', request()->except(['q', 'page'])) }}" class="pr-3 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Borrar búsqueda">✕</a>
                    @endif
                </div>

                {{-- Presets de Rango de Fechas (1-clic) --}}
                <div class="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0 scrollbar-thin">
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->toDateString() }}" data-hasta="{{ now()->toDateString() }}">Hoy</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->startOfWeek()->toDateString() }}" data-hasta="{{ now()->endOfWeek()->toDateString() }}">Semana</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->startOfMonth()->toDateString() }}" data-hasta="{{ now()->endOfMonth()->toDateString() }}">Mes</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->subMonth()->startOfMonth()->toDateString() }}" data-hasta="{{ now()->subMonth()->endOfMonth()->toDateString() }}">Mes pasado</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="" data-hasta="">Ver todo</button>
                </div>

                <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span>Filtrar</span>
                </button>

                @if($filtrosActivos > 0)
                    <a href="{{ route('gastos.index') }}"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Restablecer filtros">
                        ✕ Limpiar
                    </a>
                @endif
            </div>

            {{-- Fila inferior de filtros: Fechas, Categoría, Proveedor, Pagado --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 pt-1">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Fecha Desde</label>
                    <input type="date" name="desde" id="filtro-desde" value="{{ request('desde') }}"
                        class="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Fecha Hasta</label>
                    <input type="date" name="hasta" id="filtro-hasta" value="{{ request('hasta') }}"
                        class="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Categoría</label>
                    <select name="categoria_gasto_id" class="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                        <option value="">Todas las categorías</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" {{ (string) request('categoria_gasto_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Proveedor</label>
                    <select name="proveedor_id" class="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                        <option value="">Todos los proveedores</option>
                        @foreach($proveedores as $prov)
                            <option value="{{ $prov->id }}" {{ (string) request('proveedor_id') === (string) $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Estado de Pago</label>
                    <select name="pagado" class="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                        <option value="todos" {{ request('pagado') === 'todos' || !request('pagado') ? 'selected' : '' }}>Todos</option>
                        <option value="si" {{ request('pagado') === 'si' ? 'selected' : '' }}>Solo Pagados</option>
                        <option value="no" {{ request('pagado') === 'no' ? 'selected' : '' }}>Solo Pendientes</option>
                    </select>
                </div>
            </div>
        </form>

        {{-- Paquete B: Tabla Ergonómica de Gastos --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Fecha</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Categoría</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Proveedor</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Descripción / Ref</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Monto</th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Estado</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($gastos as $gasto)
                        @php
                            $pago = $gasto->pagos->first();
                            $gastoJson = [
                                'id' => $gasto->id,
                                'fecha' => $gasto->fecha?->format('d/m/Y') ?? '—',
                                'categoria' => $gasto->categoria?->nombre ?? 'Sin categoría',
                                'proveedor' => $gasto->proveedor?->nombre ?? 'Sin proveedor',
                                'descripcion' => $gasto->descripcion ?? '—',
                                'monto_raw' => (float) $gasto->monto,
                                'monto_formateado' => $formatoGs($gasto->monto),
                                'referencia' => $gasto->referencia ?? '—',
                                'pagado' => (bool) $gasto->pagado,
                                'pago' => $pago ? [
                                    'fecha' => $pago->fecha?->format('d/m/Y') ?? '—',
                                    'monto' => $formatoGs($pago->monto),
                                    'metodo_pago' => $metodoPagoIcono($pago->metodo_pago),
                                    'referencia_pago' => $pago->referencia_pago ?? '—',
                                    'notas' => $pago->notas ?? '—',
                                    'usuario' => $pago->usuario?->name ?? 'Sistema',
                                ] : null,
                                'edit_url' => route('gastos.edit', $gasto),
                                'delete_url' => route('gastos.destroy', $gasto),
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            {{-- ID --}}
                            <td class="px-4 py-3 text-xs font-mono font-bold text-gray-400 dark:text-gray-500">
                                #{!! $resaltar((string) $gasto->id) !!}
                            </td>

                            {{-- Fecha --}}
                            <td class="px-4 py-3 text-xs text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                <span class="font-semibold">{{ $gasto->fecha?->format('d/m/Y') ?? '—' }}</span>
                                @if($gasto->fecha)
                                    <span class="block text-[10px] text-gray-400 dark:text-gray-500">{{ $gasto->fecha->diffForHumans() }}</span>
                                @endif
                            </td>

                            {{-- Categoría con Badge Armónico --}}
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold border {{ $categoriaBadgeClase($gasto->categoria?->nombre) }}">
                                    {!! $resaltar($gasto->categoria?->nombre ?? 'Sin categoría') !!}
                                </span>
                            </td>

                            {{-- Proveedor --}}
                            <td class="px-4 py-3 text-xs text-gray-900 dark:text-gray-100">
                                @if($gasto->proveedor)
                                    <span class="inline-flex items-center gap-1.5 font-medium">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span class="truncate max-w-[150px]">{!! $resaltar($gasto->proveedor->nombre) !!}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 italic">—</span>
                                @endif
                            </td>

                            {{-- Descripción & Referencia --}}
                            <td class="px-4 py-3 text-xs text-gray-900 dark:text-gray-100 max-w-sm">
                                <p class="line-clamp-2 leading-relaxed">{!! $resaltar($gasto->descripcion ?? '—') !!}</p>
                                @if($gasto->referencia)
                                    <span class="mt-1 inline-flex items-center gap-1 font-mono text-[10px] text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/60 px-1.5 py-0.5 rounded">
                                        Ref: {!! $resaltar($gasto->referencia) !!}
                                    </span>
                                @endif
                            </td>

                            {{-- Monto en Gs. alineado a la derecha --}}
                            <td class="px-4 py-3 text-sm text-right font-mono font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $formatoGs($gasto->monto) }}
                            </td>

                            {{-- Estado y Método de Pago --}}
                            <td class="px-4 py-3 text-xs text-center whitespace-nowrap">
                                @if($gasto->pagado)
                                    <div class="inline-flex flex-col items-center gap-0.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            Pagado
                                        </span>
                                        @if($pago)
                                            <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ $metodoPagoIcono($pago->metodo_pago) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Pendiente
                                    </span>
                                @endif
                            </td>

                            {{-- Paquete C: Acciones Rápidas (Pagar en línea, Ver detalle, Editar, Eliminar) --}}
                            <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    {{-- Botón Registrar Pago en línea (si está pendiente) --}}
                                    @if(!$gasto->pagado)
                                        <button type="button"
                                            class="btn-abrir-pago-modal p-1.5 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 transition-colors"
                                            title="Registrar pago de este gasto"
                                            data-gasto="{{ json_encode($gastoJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </button>
                                    @endif

                                    {{-- Botón Ver Comprobante / Detalle --}}
                                    <button type="button"
                                        class="btn-abrir-detalle-modal p-1.5 rounded-lg text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/50 border border-purple-200 dark:border-purple-800 transition-colors"
                                        title="Ver comprobante y detalle del gasto"
                                        data-gasto="{{ json_encode($gastoJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>

                                    {{-- Botón Editar --}}
                                    <a href="{{ route('gastos.edit', $gasto) }}"
                                        class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        title="Editar gasto">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>

                                    {{-- Botón Eliminar --}}
                                    <form action="{{ route('gastos.destroy', $gasto) }}" method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar el gasto #{{ $gasto->id }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                            title="Eliminar gasto">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m0 0l-6-6m6 6H3"/></svg>
                                @if($filtrosActivos > 0)
                                    <p class="text-base font-semibold text-gray-700 dark:text-gray-300">No hay gastos que coincidan con los filtros seleccionados.</p>
                                    <a href="{{ route('gastos.index') }}" class="mt-2 inline-block text-sm text-purple-600 dark:text-purple-400 hover:underline">Limpiar todos los filtros</a>
                                @else
                                    <p class="text-base font-semibold text-gray-700 dark:text-gray-300">No hay gastos registrados en el sistema.</p>
                                    <a href="{{ route('gastos.create') }}" class="mt-2 inline-block text-sm text-purple-600 dark:text-purple-400 hover:underline">Registrar el primer gasto</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Selector de Filas y Paginación --}}
        @if ($gastos->total() > 0)
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-b-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                    <span>Mostrar</span>
                    <select id="gastos-per-page" aria-label="Filas por página"
                        class="px-2 py-1 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                        @foreach ([10, 15, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($gastos->perPage() === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <span>filas por página · Mostrando {{ $gastos->firstItem() ?? 0 }}-{{ $gastos->lastItem() ?? 0 }} de {{ $gastos->total() }}</span>
                </label>
                @if ($gastos->hasPages())
                    <div class="min-w-0">{{ $gastos->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- Paquete C: Modal de Registrar Pago en Línea (Sin salir de la pantalla) --}}
<div id="modal-pagar-gasto" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="modal-pagar-gasto-title">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="modal-pagar-gasto-backdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h3 id="modal-pagar-gasto-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">Registrar Pago de Gasto</h3>
                    <p id="modal-pagar-subtitulo" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"></p>
                </div>
                <button type="button" id="modal-pagar-cerrar" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Resumen del Gasto a Pagar --}}
            <div class="p-3.5 rounded-xl border border-purple-100 dark:border-purple-900/40 bg-purple-50/60 dark:bg-purple-950/30 mb-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-purple-700 dark:text-purple-300" id="modal-pagar-resumen-cat"></span>
                    <span class="text-base font-bold font-mono text-purple-900 dark:text-purple-100" id="modal-pagar-resumen-monto"></span>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1" id="modal-pagar-resumen-desc"></p>
            </div>

            <form action="{{ route('pagos.store') }}" method="POST" id="form-modal-pagar">
                @csrf
                <input type="hidden" name="tipo" value="gasto">
                <input type="hidden" name="referencia_id" id="modal-pagar-referencia-id" value="">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">Fecha de Pago *</label>
                        <input type="date" name="fecha" id="modal-pagar-fecha" value="{{ date('Y-m-d') }}" required
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">Monto (Gs) *</label>
                        <input type="number" name="monto" id="modal-pagar-monto" step="0.01" min="0.01" required
                            class="w-full px-3 py-2 text-sm font-mono font-bold rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">Método de Pago *</label>
                        <select name="metodo_pago" id="modal-pagar-metodo" required
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="transferencia">🏦 Transferencia bancaria</option>
                            <option value="tarjeta">💳 Tarjeta de débito/crédito</option>
                            <option value="cheque">📝 Cheque</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">Nº Comprobante / Ref. Pago</label>
                        <input type="text" name="referencia_pago" maxlength="100" placeholder="Ej: Nº de operación bancaria o recibo"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">Notas Contables (Opcional)</label>
                        <textarea name="notas" rows="2" placeholder="Observaciones sobre este egreso..."
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none resize-none"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" id="modal-pagar-cancelar" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm transition-colors">Confirmar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Paquete C: Modal de Ficha / Detalle Completo del Gasto --}}
<div id="modal-detalle-gasto" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="modal-detalle-gasto-title">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="modal-detalle-gasto-backdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div>
                        <h3 id="modal-detalle-gasto-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">Comprobante de Gasto</h3>
                        <p id="modal-detalle-sub" class="text-xs text-gray-500 dark:text-gray-400"></p>
                    </div>
                </div>
                <button type="button" id="modal-detalle-cerrar" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Bloque de Datos Generales --}}
            <div class="space-y-3 divide-y divide-gray-100 dark:divide-gray-700/60 text-sm">
                <div class="flex items-center justify-between pt-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Monto Total</span>
                    <span class="text-xl font-bold font-mono text-purple-700 dark:text-purple-300" id="det-monto"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Categoría</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100" id="det-categoria"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Proveedor</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100" id="det-proveedor"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Fecha Emisión</span>
                    <span class="text-gray-900 dark:text-gray-100" id="det-fecha"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Referencia / Factura</span>
                    <span class="font-mono text-gray-900 dark:text-gray-100" id="det-referencia"></span>
                </div>
                <div class="pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold block mb-1">Descripción del Concepto</span>
                    <p class="text-xs text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-700/40 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700 leading-relaxed whitespace-pre-wrap" id="det-descripcion"></p>
                </div>
            </div>

            {{-- Bloque de Liquidación / Pago --}}
            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700" id="det-bloque-pago">
                <!-- Se inyecta dinámicamente según si está pagado o pendiente -->
            </div>

            <div class="mt-5 flex items-center justify-between gap-2 border-t border-gray-100 dark:border-gray-700/60 pt-4">
                <a href="#" id="det-link-editar" class="text-xs text-purple-600 dark:text-purple-400 hover:underline font-semibold">Editar Gasto</a>
                <button type="button" id="modal-detalle-btn-cerrar" class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    // Paginación por página
    var selPerPage = document.getElementById('gastos-per-page');
    if (selPerPage) {
        selPerPage.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    // Presets rápidos de fechas
    var formFiltros = document.getElementById('form-filtros-gastos');
    var inputDesde = document.getElementById('filtro-desde');
    var inputHasta = document.getElementById('filtro-hasta');

    document.querySelectorAll('.btn-fecha-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var d = this.getAttribute('data-desde');
            var h = this.getAttribute('data-hasta');
            if (inputDesde) inputDesde.value = d;
            if (inputHasta) inputHasta.value = h;
            if (formFiltros) formFiltros.submit();
        });
    });

    // ==========================================
    // Paquete C: Modal de Registrar Pago en Línea
    // ==========================================
    var modalPagar = document.getElementById('modal-pagar-gasto');
    var modalPagarRefId = document.getElementById('modal-pagar-referencia-id');
    var modalPagarMonto = document.getElementById('modal-pagar-monto');
    var modalPagarSub = document.getElementById('modal-pagar-subtitulo');
    var modalPagarCat = document.getElementById('modal-pagar-resumen-cat');
    var modalPagarDesc = document.getElementById('modal-pagar-resumen-desc');
    var modalPagarMontoText = document.getElementById('modal-pagar-resumen-monto');

    function cerrarModalPagar() {
        if (!modalPagar) return;
        modalPagar.classList.add('hidden');
    }

    function abrirModalPagar(gasto) {
        if (!modalPagar || !gasto) return;
        modalPagarRefId.value = gasto.id;
        modalPagarMonto.value = gasto.monto_raw;
        modalPagarSub.textContent = 'Gasto #' + gasto.id + ' · ' + gasto.fecha;
        modalPagarCat.textContent = gasto.categoria + (gasto.proveedor !== 'Sin proveedor' ? ' · ' + gasto.proveedor : '');
        modalPagarDesc.textContent = gasto.descripcion;
        modalPagarMontoText.textContent = gasto.monto_formateado;
        modalPagar.classList.remove('hidden');
    }

    document.querySelectorAll('.btn-abrir-pago-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var raw = this.getAttribute('data-gasto');
            if (!raw) return;
            try {
                var gasto = JSON.parse(raw);
                abrirModalPagar(gasto);
            } catch (e) {
                console.error(e);
            }
        });
    });

    if (modalPagar) {
        document.getElementById('modal-pagar-backdrop')?.addEventListener('click', cerrarModalPagar);
        document.getElementById('modal-pagar-cerrar')?.addEventListener('click', cerrarModalPagar);
        document.getElementById('modal-pagar-cancelar')?.addEventListener('click', cerrarModalPagar);
    }

    // ==========================================
    // Paquete C: Modal de Detalle de Gasto
    // ==========================================
    var modalDetalle = document.getElementById('modal-detalle-gasto');
    var detSub = document.getElementById('modal-detalle-sub');
    var detMonto = document.getElementById('det-monto');
    var detCategoria = document.getElementById('det-categoria');
    var detProveedor = document.getElementById('det-proveedor');
    var detFecha = document.getElementById('det-fecha');
    var detReferencia = document.getElementById('det-referencia');
    var detDescripcion = document.getElementById('det-descripcion');
    var detBloquePago = document.getElementById('det-bloque-pago');
    var detLinkEditar = document.getElementById('det-link-editar');

    function cerrarModalDetalle() {
        if (!modalDetalle) return;
        modalDetalle.classList.add('hidden');
    }

    function abrirModalDetalle(gasto) {
        if (!modalDetalle || !gasto) return;
        detSub.textContent = 'Gasto #' + gasto.id + ' registrado el ' + gasto.fecha;
        detMonto.textContent = gasto.monto_formateado;
        detCategoria.textContent = gasto.categoria;
        detProveedor.textContent = gasto.proveedor;
        detFecha.textContent = gasto.fecha;
        detReferencia.textContent = gasto.referencia;
        detDescripcion.textContent = gasto.descripcion;
        detLinkEditar.href = gasto.edit_url;

        if (gasto.pagado && gasto.pago) {
            detBloquePago.innerHTML = '<div class="rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/60 dark:bg-emerald-950/30 p-3 space-y-1.5 text-xs">' +
                '<div class="flex items-center justify-between font-bold text-emerald-800 dark:text-emerald-300">' +
                '<span>✓ Gasto Pagado (' + gasto.pago.metodo_pago + ')</span>' +
                '<span>' + gasto.pago.fecha + '</span>' +
                '</div>' +
                '<p class="text-gray-600 dark:text-gray-400">Registrado por: <strong class="text-gray-800 dark:text-gray-200">' + gasto.pago.usuario + '</strong></p>' +
                (gasto.pago.referencia_pago !== '—' ? '<p class="text-gray-600 dark:text-gray-400">Ref. Pago: ' + gasto.pago.referencia_pago + '</p>' : '') +
                (gasto.pago.notas !== '—' ? '<p class="text-gray-600 dark:text-gray-400">Notas: ' + gasto.pago.notas + '</p>' : '') +
                '</div>';
        } else {
            detBloquePago.innerHTML = '<div class="flex items-center justify-between rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/60 dark:bg-amber-950/30 p-3 text-xs">' +
                '<div><p class="font-bold text-amber-800 dark:text-amber-300">⏳ Estado: Pendiente de Pago</p><p class="text-gray-500 dark:text-gray-400 mt-0.5">Este egreso aún no tiene un comprobante de pago registrado.</p></div>' +
                '<button type="button" class="btn-abrir-pago-desde-det px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-sm transition-colors">Pagar Ahora</button>' +
                '</div>';

            var btnPagarDesdeDet = detBloquePago.querySelector('.btn-abrir-pago-desde-det');
            if (btnPagarDesdeDet) {
                btnPagarDesdeDet.addEventListener('click', function() {
                    cerrarModalDetalle();
                    abrirModalPagar(gasto);
                });
            }
        }

        modalDetalle.classList.remove('hidden');
    }

    document.querySelectorAll('.btn-abrir-detalle-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var raw = this.getAttribute('data-gasto');
            if (!raw) return;
            try {
                var gasto = JSON.parse(raw);
                abrirModalDetalle(gasto);
            } catch (e) {
                console.error(e);
            }
        });
    });

    if (modalDetalle) {
        document.getElementById('modal-detalle-gasto-backdrop')?.addEventListener('click', cerrarModalDetalle);
        document.getElementById('modal-detalle-cerrar')?.addEventListener('click', cerrarModalDetalle);
        document.getElementById('modal-detalle-btn-cerrar')?.addEventListener('click', cerrarModalDetalle);
    }

    // Escape para cerrar cualquier modal abierto
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalPagar();
            cerrarModalDetalle();
        }
    });
})();
</script>
@endpush
@endsection
