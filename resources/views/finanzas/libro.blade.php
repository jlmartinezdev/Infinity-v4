@extends('layouts.app')

@section('title', 'Libro financiero')

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
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $busqueda = (string) ($filtros['q'] ?? '');
    $resaltar = function (?string $texto) use ($busqueda): string {
        if (! filled($texto)) return '—';
        if (! filled($busqueda)) return e($texto);
        return \App\Support\SearchHighlight::html($texto, $busqueda);
    };

    $tipos = [
        'ingreso' => 'Ingreso',
        'egreso' => 'Egreso',
        'transferencia_entrada' => 'Transferencia de entrada',
        'transferencia_salida' => 'Transferencia de salida',
        'ajuste' => 'Ajuste',
    ];

    $tipoBadgeClase = function (?string $tipo): string {
        return match ($tipo) {
            'ingreso' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'egreso' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            'transferencia_entrada' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 dark:border-sky-800',
            'transferencia_salida' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            'ajuste' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-300 border-gray-200 dark:border-gray-600',
        };
    };

    $filtrosActivos = (int) (($filtros['q'] ?? '') !== '')
        + (int) (($filtros['cuenta_id'] ?? 0) > 0)
        + (int) (($filtros['tipo'] ?? '') !== '')
        + (int) (($filtros['categoria_id'] ?? 0) > 0)
        + (int) (($filtros['desde'] ?? '') !== '')
        + (int) (($filtros['hasta'] ?? '') !== '');

    $resumenDatos = $resumen ?? [
        'cantidad' => $movimientos->total(),
        'entradas' => 0.0,
        'salidas' => 0.0,
        'neto' => 0.0,
    ];
@endphp

<div class="max-w-7xl mx-auto">
    @include('finanzas._nav')

    {{-- Header Principal --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                Libro de movimientos
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                    {{ $fmt($resumenDatos['cantidad']) }} movimiento{{ $resumenDatos['cantidad'] === 1 ? '' : 's' }}
                </span>
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Registro contable inmutable de ingresos, egresos, transferencias entre cuentas, liquidaciones y ajustes.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('finanzas.libro.excel', request()->query()) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm transition-colors">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Excel</span>
            </a>
            <a href="{{ route('finanzas.egresos') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Registrar gasto</span>
            </a>
        </div>
    </div>

    {{-- Deck de KPIs del Libro Filtrado --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Movimientos</span>
                <span class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 font-mono">{{ $fmt($resumenDatos['cantidad']) }}</p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $filtrosActivos > 0 ? 'Con filtros aplicados' : 'Total en el libro' }}
            </p>
        </div>

        <div class="rounded-xl border border-emerald-200/80 dark:border-emerald-800/50 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Entradas (+)</span>
                <span class="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mt-2 font-mono">{{ $fmt($resumenDatos['entradas']) }} <span class="text-xs font-semibold">PYG</span></p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Ingresos, transf. entrada y ajustes +</p>
        </div>

        <div class="rounded-xl border border-rose-200/80 dark:border-rose-800/50 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">Salidas (−)</span>
                <span class="p-1.5 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-2 font-mono">{{ $fmt($resumenDatos['salidas']) }} <span class="text-xs font-semibold">PYG</span></p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Egresos, retiros, transf. salida y ajustes −</p>
        </div>

        <div class="rounded-xl border {{ $resumenDatos['neto'] < 0 ? 'border-rose-200 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-950/30' : 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/30' }} p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider {{ $resumenDatos['neto'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">Variación Neta</span>
                <span class="p-1.5 rounded-lg {{ $resumenDatos['neto'] < 0 ? 'bg-rose-200/70 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300' : 'bg-emerald-200/70 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold {{ $resumenDatos['neto'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }} mt-2 font-mono">
                {{ $resumenDatos['neto'] < 0 ? '−' : '' }}{{ $fmt(abs($resumenDatos['neto'])) }} <span class="text-xs font-semibold">PYG</span>
            </p>
            <p class="text-[11px] {{ $resumenDatos['neto'] < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} mt-0.5">
                Entradas menos salidas del filtro
            </p>
        </div>
    </div>

    {{-- Contenedor Principal: Filtros + Tabla + Paginación --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="get" action="{{ route('finanzas.libro') }}" id="form-filtros-libro" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/30 space-y-3">
            <input type="hidden" name="per_page" value="{{ $movimientos->perPage() }}">

            {{-- Fila superior: Búsqueda rápida + Presets de fecha + Botón Filtrar --}}
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex-1 min-w-[240px] relative rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 shadow-sm flex items-center">
                    <span class="pl-3 text-gray-400 dark:text-gray-500 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                    </span>
                    <input type="search" name="q" id="filtro-q" value="{{ $filtros['q'] ?? '' }}"
                        placeholder="Buscar por concepto, recibo, referencia, observación o #ID..."
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2 pr-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0">
                    @if(!empty($filtros['q']))
                        <a href="{{ route('finanzas.libro', request()->except(['q', 'page'])) }}" class="pr-3 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Borrar búsqueda">✕</a>
                    @endif
                </div>

                {{-- Presets rápidos de fechas --}}
                <div class="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0">
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->toDateString() }}" data-hasta="{{ now()->toDateString() }}">Hoy</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->startOfWeek()->toDateString() }}" data-hasta="{{ now()->endOfWeek()->toDateString() }}">Semana</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->startOfMonth()->toDateString() }}" data-hasta="{{ now()->endOfMonth()->toDateString() }}">Mes</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="{{ now()->subMonth()->startOfMonth()->toDateString() }}" data-hasta="{{ now()->subMonth()->endOfMonth()->toDateString() }}">Mes pasado</button>
                    <button type="button" class="btn-fecha-preset px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm"
                        data-desde="" data-hasta="">Todo</button>
                </div>

                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filtrar</span>
                </button>

                @if($filtrosActivos > 0)
                    <a href="{{ route('finanzas.libro') }}"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Restablecer todos los filtros">
                        ✕ Limpiar
                    </a>
                @endif
            </div>

            {{-- Fila inferior: Selectores de Cuenta, Tipo, Categoría y Rango de Fechas --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 pt-1">
                <div>
                    <label for="filtro-cuenta" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Cuenta</label>
                    <select id="filtro-cuenta" name="cuenta_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs px-3 py-1.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                        <option value="">Todas las cuentas</option>
                        @foreach($cuentas as $cuenta)
                            <option value="{{ $cuenta->id }}" @selected($filtros['cuenta_id'] === $cuenta->id)>{{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filtro-tipo" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Tipo de movimiento</label>
                    <select id="filtro-tipo" name="tipo" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs px-3 py-1.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                        <option value="">Todos los tipos</option>
                        @foreach($tipos as $tipo => $etiqueta)
                            <option value="{{ $tipo }}" @selected($filtros['tipo'] === $tipo)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filtro-categoria" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Categoría</label>
                    <select id="filtro-categoria" name="categoria_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs px-3 py-1.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                        <option value="">Todas las categorías</option>
                        @foreach($categorias ?? [] as $cat)
                            <option value="{{ $cat->id }}" @selected(($filtros['categoria_id'] ?? 0) === $cat->id)>{{ strtoupper($cat->clase) }} — {{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filtro-desde" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Desde</label>
                    <input id="filtro-desde" type="date" name="desde" value="{{ $filtros['desde'] }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs px-3 py-1.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                </div>

                <div>
                    <label for="filtro-hasta" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Hasta</label>
                    <input id="filtro-hasta" type="date" name="hasta" value="{{ $filtros['hasta'] }}"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs px-3 py-1.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                </div>
            </div>
        </form>

        {{-- Tabla de Movimientos --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Fecha</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cuenta</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Categoría</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Concepto / Referencia</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Importe</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">Detalle</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($movimientos as $movimiento)
                        @php
                            $tipoEtiqueta = $tipos[$movimiento->tipo] ?? ucfirst(str_replace('_', ' ', (string) $movimiento->tipo));
                            $refCompuesta = trim(($movimiento->referencia_tipo ?? '').($movimiento->referencia_id ? ' #'.$movimiento->referencia_id : '').($movimiento->referencia_texto ? ' · '.$movimiento->referencia_texto : ''));
                            $urlOrigen = null;
                            if ($movimiento->referencia_tipo === 'cobro' && $movimiento->referencia_id) {
                                $urlOrigen = route('cobros.show', $movimiento->referencia_id);
                            }

                            $movJson = [
                                'id' => $movimiento->id,
                                'fecha' => $movimiento->fecha_efectiva?->format('d/m/Y') ?? '—',
                                'registrado' => $movimiento->created_at?->format('d/m/Y H:i') ?? '—',
                                'cuenta' => $movimiento->cuenta?->nombre ?? '—',
                                'cuenta_tipo' => ucfirst((string) ($movimiento->cuenta?->tipo ?? '')),
                                'tipo' => $tipoEtiqueta,
                                'signo' => (int) $movimiento->signo,
                                'importe_formateado' => ($movimiento->signo < 0 ? '−' : '+').$fmt($movimiento->importe).' PYG',
                                'categoria' => $movimiento->categoria ? strtoupper($movimiento->categoria->clase).' — '.$movimiento->categoria->nombre : '—',
                                'concepto' => $movimiento->concepto ?? '—',
                                'referencia' => $refCompuesta !== '' ? $refCompuesta : '—',
                                'observaciones' => $movimiento->observaciones ?: '—',
                                'usuario' => $movimiento->usuario?->name ?? ($movimiento->usuario_id ? 'Usuario #'.$movimiento->usuario_id : 'Sistema'),
                                'revertido_por_id' => $movimiento->revertido_por_id,
                                'url_origen' => $urlOrigen,
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors {{ $movimiento->revertido_por_id ? 'opacity-65 bg-gray-50/40 dark:bg-gray-900/20' : '' }}">
                            {{-- ID --}}
                            <td class="px-4 py-3 text-xs font-mono font-bold text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                #{!! $resaltar((string) $movimiento->id) !!}
                            </td>

                            {{-- Fecha --}}
                            <td class="px-4 py-3 text-xs text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                <span class="font-semibold">{{ $movimiento->fecha_efectiva?->format('d/m/Y') }}</span>
                                @if($movimiento->created_at)
                                    <span class="block text-[10px] text-gray-400 dark:text-gray-500">Reg. {{ $movimiento->created_at->format('d/m H:i') }}</span>
                                @endif
                            </td>

                            {{-- Cuenta --}}
                            <td class="px-4 py-3 text-xs font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $movimiento->cuenta?->nombre ?? '—' }}
                            </td>

                            {{-- Tipo con Badge --}}
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold border {{ $tipoBadgeClase($movimiento->tipo) }}">
                                    {{ str_replace('_', ' ', $movimiento->tipo) }}
                                </span>
                            </td>

                            {{-- Categoría --}}
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                @if($movimiento->categoria)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $movimiento->categoria->clase }}</span>
                                        <span>{{ $movimiento->categoria->nombre }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>

                            {{-- Concepto / Referencia / Observaciones --}}
                            <td class="px-4 py-3 text-xs text-gray-900 dark:text-gray-100 max-w-md">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-medium">{!! $resaltar($movimiento->concepto) !!}</span>
                                    @if($movimiento->revertido_por_id)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            revertido #{{ $movimiento->revertido_por_id }}
                                        </span>
                                    @endif
                                </div>
                                @if($refCompuesta !== '' || $movimiento->observaciones)
                                    <div class="mt-0.5 flex items-center gap-2 flex-wrap text-[11px] text-gray-500 dark:text-gray-400">
                                        @if($refCompuesta !== '')
                                            @if($urlOrigen)
                                                <a href="{{ $urlOrigen }}" class="font-mono text-emerald-600 dark:text-emerald-400 hover:underline">
                                                    {!! $resaltar($refCompuesta) !!}
                                                </a>
                                            @else
                                                <span class="font-mono">{!! $resaltar($refCompuesta) !!}</span>
                                            @endif
                                        @endif
                                        @if($movimiento->observaciones)
                                            <span class="truncate max-w-xs" title="{{ $movimiento->observaciones }}">· {!! $resaltar($movimiento->observaciones) !!}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            {{-- Importe --}}
                            <td class="px-4 py-3 text-sm text-right font-mono font-bold whitespace-nowrap {{ $movimiento->signo < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                {{ $movimiento->signo < 0 ? '−' : '' }}{{ $fmt($movimiento->importe) }}
                            </td>

                            {{-- Acción Ver Ficha --}}
                            <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
                                <button type="button"
                                    class="btn-abrir-detalle-mov p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-transparent hover:border-emerald-200 dark:hover:border-emerald-800 transition-colors"
                                    title="Ver detalle del movimiento #{{ $movimiento->id }}"
                                    aria-label="Ver detalle del movimiento #{{ $movimiento->id }}"
                                    data-movimiento="{{ json_encode($movJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-10 h-10 mx-auto text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="font-medium text-gray-700 dark:text-gray-300">Todavía no hay movimientos.</p>
                                @if($filtrosActivos > 0)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ningún movimiento coincide con los filtros actuales.</p>
                                    <a href="{{ route('finanzas.libro') }}" class="mt-2 inline-block text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Limpiar todos los filtros</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer de Tabla: Selector de filas por página y Links de Paginación --}}
        @if($movimientos->total() > 0)
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                    <span>Mostrar</span>
                    <select id="libro-per-page" aria-label="Movimientos por página"
                        class="px-2 py-1 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                        @foreach([20, 40, 100, 200] as $n)
                            <option value="{{ $n }}" @selected($movimientos->perPage() === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <span>por página · Mostrando {{ $movimientos->firstItem() ?? 0 }}–{{ $movimientos->lastItem() ?? 0 }} de {{ $fmt($movimientos->total()) }}</span>
                </label>
                @if($movimientos->hasPages())
                    <div class="min-w-0">{{ $movimientos->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- Modal de Ficha de Movimiento Contable --}}
<div id="modal-detalle-mov" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="modal-detalle-mov-title">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="modal-detalle-mov-backdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div>
                        <h3 id="modal-detalle-mov-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">Detalle del movimiento</h3>
                        <p id="det-mov-sub" class="text-xs text-gray-500 dark:text-gray-400"></p>
                    </div>
                </div>
                <button type="button" id="modal-detalle-mov-cerrar" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-3 divide-y divide-gray-100 dark:divide-gray-700/60 text-sm">
                <div class="flex items-center justify-between pt-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Importe</span>
                    <span class="text-xl font-bold font-mono" id="det-mov-importe"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Cuenta</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100" id="det-mov-cuenta"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Tipo</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100" id="det-mov-tipo"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Categoría</span>
                    <span class="text-gray-900 dark:text-gray-100" id="det-mov-categoria"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Fecha efectiva</span>
                    <span class="text-gray-900 dark:text-gray-100" id="det-mov-fecha"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Referencia</span>
                    <span class="font-mono text-xs text-gray-900 dark:text-gray-100" id="det-mov-referencia"></span>
                </div>
                <div class="flex items-center justify-between pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Registrado por</span>
                    <span class="text-gray-900 dark:text-gray-100" id="det-mov-usuario"></span>
                </div>
                <div class="pt-2.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold block mb-1">Concepto</span>
                    <p class="text-xs text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-700/40 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700" id="det-mov-concepto"></p>
                </div>
                <div class="pt-2.5" id="det-mov-obs-wrap">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold block mb-1">Observaciones</span>
                    <p class="text-xs text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-700/40 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700 whitespace-pre-wrap" id="det-mov-observaciones"></p>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between gap-2 border-t border-gray-100 dark:border-gray-700/60 pt-4">
                <a href="#" id="det-mov-link-origen" class="hidden text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Ver comprobante origen →</a>
                <button type="button" id="modal-detalle-mov-btn-cerrar" class="ml-auto px-4 py-2 text-xs font-semibold rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selPerPage = document.getElementById('libro-per-page');
    if (selPerPage) {
        selPerPage.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    var formFiltros = document.getElementById('form-filtros-libro');
    var inputDesde = document.getElementById('filtro-desde');
    var inputHasta = document.getElementById('filtro-hasta');

    document.querySelectorAll('.btn-fecha-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (inputDesde) inputDesde.value = this.getAttribute('data-desde') || '';
            if (inputHasta) inputHasta.value = this.getAttribute('data-hasta') || '';
            if (formFiltros) formFiltros.submit();
        });
    });

    var modal = document.getElementById('modal-detalle-mov');
    var sub = document.getElementById('det-mov-sub');
    var importe = document.getElementById('det-mov-importe');
    var cuenta = document.getElementById('det-mov-cuenta');
    var tipo = document.getElementById('det-mov-tipo');
    var categoria = document.getElementById('det-mov-categoria');
    var fecha = document.getElementById('det-mov-fecha');
    var referencia = document.getElementById('det-mov-referencia');
    var usuario = document.getElementById('det-mov-usuario');
    var concepto = document.getElementById('det-mov-concepto');
    var obs = document.getElementById('det-mov-observaciones');
    var linkOrigen = document.getElementById('det-mov-link-origen');

    function cerrarModal() {
        if (modal) modal.classList.add('hidden');
    }

    document.querySelectorAll('.btn-abrir-detalle-mov').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var raw = this.getAttribute('data-movimiento');
            if (!raw || !modal) return;
            try {
                var m = JSON.parse(raw);
                sub.textContent = 'Movimiento #' + m.id + ' · Registrado ' + m.registrado;
                importe.textContent = m.importe_formateado;
                importe.className = 'text-xl font-bold font-mono ' + (m.signo < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400');
                cuenta.textContent = m.cuenta + (m.cuenta_tipo ? ' (' + m.cuenta_tipo + ')' : '');
                tipo.textContent = m.tipo + (m.revertido_por_id ? ' (Revertido por #' + m.revertido_por_id + ')' : '');
                categoria.textContent = m.categoria;
                fecha.textContent = m.fecha;
                referencia.textContent = m.referencia;
                usuario.textContent = m.usuario;
                concepto.textContent = m.concepto;
                obs.textContent = m.observaciones;

                if (m.url_origen) {
                    linkOrigen.href = m.url_origen;
                    linkOrigen.classList.remove('hidden');
                } else {
                    linkOrigen.classList.add('hidden');
                }

                modal.classList.remove('hidden');
            } catch (e) {
                console.error(e);
            }
        });
    });

    document.getElementById('modal-detalle-mov-backdrop')?.addEventListener('click', cerrarModal);
    document.getElementById('modal-detalle-mov-cerrar')?.addEventListener('click', cerrarModal);
    document.getElementById('modal-detalle-mov-btn-cerrar')?.addEventListener('click', cerrarModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            cerrarModal();
        }
    });
})();
</script>
@endpush
