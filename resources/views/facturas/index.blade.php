@extends('layouts.app')

@section('title', 'Facturación Electrónica SIFEN')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    {{-- Header principal --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Facturación electrónica</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    SIFEN DNIT
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Gestión oficial de Documentos Tributarios Electrónicos (DTE), KuDE, XML firmado y lotes de envío.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('facturas.pdf-resumen', request()->query()) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors"
               title="Exportar listado actual a PDF">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                PDF Resumen
            </a>

            @can('facturas.crear')
                @if(($lotesPendientesCount ?? 0) > 0)
                    <form method="POST" action="{{ route('facturas.consultar-lotes') }}" class="inline"
                          onsubmit="return confirm('¿Consultar los {{ $lotesPendientesCount }} lote(s) pendiente(s) en SIFEN?');">
                        @csrf
                        <input type="hidden" name="todos" value="1">
                        <button type="submit" class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-sm transition-colors animate-pulse"
                                title="Consultar todos los lotes pendientes ante la SET/DNIT">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Consultar lotes ({{ $lotesPendientesCount }})
                        </button>
                    </form>
                @endif
                <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700 shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Datos manuales
                </a>
                <a href="{{ route('facturas.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva factura
                </a>
            @endcan
        </div>
    </div>

    {{-- Notificaciones de sesión --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800 flex items-center gap-3 text-sm">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('warning'))
        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800 flex items-center gap-3 text-sm">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800 flex items-center gap-3 text-sm">
            <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- PAQUETE A: Deck de KPIs Ejecutivos Fiscales & Control de Topes --}}
    @php
        $esAdminTope = (bool) auth()->user()?->esAdministrador();
        $puedeVerMontoFacturado = $esAdminTope;
        $tope = $limiteMes ?? [
            'mes' => $mesDashboard->format('Y-m'),
            'limite' => null,
            'usado' => (float) ($statsEmitidasMes->monto_total ?? 0),
            'restante' => null,
            'porcentaje' => null,
            'excedido' => false,
            'sin_tope' => true,
        ];
        $pctTope = min(100, (float) ($tope['porcentaje'] ?? 0));
        $bordeTope = $tope['excedido']
            ? 'border-rose-200 dark:border-rose-800/50 bg-rose-50/20'
            : ($tope['sin_tope'] ? 'border-gray-200 dark:border-gray-700' : 'border-purple-200 dark:border-purple-800/40');
        $colorBarra = $tope['excedido'] ? '#dc2626' : ($pctTope >= 80 ? '#d97706' : '#7c3aed');
        $topeSwal = [
            'mes' => $tope['mes'],
            'label' => $mesDashboardLabel,
            'limite' => $tope['limite'],
            'limites' => $limitesMesMapa ?? [],
        ];
        $fechaTope = $fechaTopeEmision ?? [
            'fecha' => null,
            'label' => null,
            'sin_tope' => true,
            'vencida' => false,
        ];
        $bordeFechaTope = $fechaTope['vencida']
            ? 'border-rose-200 dark:border-rose-800/50 bg-rose-50/20'
            : ($fechaTope['sin_tope'] ? 'border-gray-200 dark:border-gray-700' : 'border-sky-200 dark:border-sky-800/40');
    @endphp

    <div class="space-y-3">
        {{-- Selector de mes rápido --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white dark:bg-gray-800 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 capitalize">
                        Resumen fiscal de {{ $mesDashboardLabel }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Indicadores de emisión oficial, IVA débito fiscal y control de cupo mensual SIFEN.
                    </p>
                </div>
            </div>

            <form method="GET" action="{{ route('facturas.index') }}" class="flex items-center gap-2">
                @foreach (request()->except(['mes', 'page']) as $k => $v)
                    @if(is_array($v))
                        @foreach ($v as $subV)
                            <input type="hidden" name="{{ $k }}[]" value="{{ $subV }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach

                @php
                    $mesPrev = $mesDashboard->copy()->subMonth()->format('Y-m');
                    $mesNext = $mesDashboard->copy()->addMonth()->format('Y-m');
                    $mesActual = now()->format('Y-m');
                @endphp

                <a href="{{ route('facturas.index', array_merge(request()->except(['mes', 'page']), ['mes' => $mesPrev])) }}"
                   class="p-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                   title="Mes anterior">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>

                <input type="month" name="mes" id="mes" value="{{ $mesDashboard->format('Y-m') }}" onchange="this.form.submit()"
                       class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500">

                <a href="{{ route('facturas.index', array_merge(request()->except(['mes', 'page']), ['mes' => $mesNext])) }}"
                   class="p-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                   title="Mes siguiente">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>

                @if($mesDashboard->format('Y-m') !== $mesActual)
                    <a href="{{ route('facturas.index', array_merge(request()->except(['mes', 'page']), ['mes' => $mesActual])) }}"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition-colors">
                        Hoy
                    </a>
                @endif
            </form>
        </div>

        {{-- Grid de KPIs del mes --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- KPI 1: Facturación Total del Mes --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-purple-200/80 dark:border-purple-800/40 p-4 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-purple-700 dark:text-purple-400 uppercase tracking-wider">Monto Emitido</span>
                    <span class="p-1.5 rounded-lg bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">
                        {{ number_format((float) ($statsEmitidasMes->monto_total ?? 0), 0, ',', '.') }}
                        <span class="text-xs font-semibold text-purple-600 dark:text-purple-400">PYG</span>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center justify-between">
                        <span>IVA Débito: <strong class="text-gray-700 dark:text-gray-300">{{ number_format((float) ($statsEmitidasMes->monto_iva ?? 0), 0, ',', '.') }}</strong></span>
                        <span>{{ number_format((int) ($statsEmitidasMes->cantidad ?? 0), 0, ',', '.') }} docs</span>
                    </p>
                </div>
            </div>

            {{-- KPI 2: Facturas Emitidas / Aprobadas --}}
            <a href="{{ route('facturas.index', array_merge(request()->query(), ['tab' => 'emitidas'])) }}"
               class="bg-white dark:bg-gray-800 rounded-xl border border-emerald-200/80 dark:border-emerald-800/40 p-4 shadow-sm hover:border-emerald-400 transition-all block group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Aprobadas SIFEN</span>
                    <span class="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-300 group-hover:scale-110 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <p class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-300">
                        {{ number_format((int) ($statsEmitidasMes->cantidad ?? 0), 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1">
                        <span>En {{ $mesDashboardLabel }}</span>
                        <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </p>
                </div>
            </a>

            {{-- KPI 3: Borradores Pendientes --}}
            <a href="{{ route('facturas.index', array_merge(request()->query(), ['tab' => 'borradores'])) }}"
               class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200/80 dark:border-amber-800/40 p-4 shadow-sm hover:border-amber-400 transition-all block group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Borradores Mes</span>
                    <span class="p-1.5 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-300 group-hover:scale-110 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <p class="text-2xl font-extrabold text-amber-700 dark:text-amber-300">
                        {{ number_format($borradoresMes, 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 truncate" title="{{ number_format($montoBorradoresMes, 0, ',', '.') }} PYG">
                        {{ number_format($montoBorradoresMes, 0, ',', '.') }} PYG por emitir
                    </p>
                </div>
            </a>

            {{-- KPI 4: Lotes y Estado SIFEN --}}
            <a href="{{ route('facturas.index', array_merge(request()->query(), ['tab' => 'lotes'])) }}"
               class="bg-white dark:bg-gray-800 rounded-xl border {{ ($lotesPendientesCount ?? 0) > 0 ? 'border-indigo-400 dark:border-indigo-600 bg-indigo-50/20' : 'border-indigo-200/80 dark:border-indigo-800/40' }} p-4 shadow-sm hover:border-indigo-400 transition-all block group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-400 uppercase tracking-wider">Lotes SIFEN</span>
                    <span class="p-1.5 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 group-hover:scale-110 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="flex items-center gap-2">
                        <p class="text-2xl font-extrabold text-indigo-700 dark:text-indigo-300">
                            {{ number_format($lotesPendientesCount ?? 0, 0, ',', '.') }}
                        </p>
                        @if(($lotesPendientesCount ?? 0) > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-600 text-white animate-pulse">
                                Pendientes
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">
                        {{ ($lotesPendientesCount ?? 0) > 0 ? 'Esperando resolución DNIT' : 'Todos procesados' }}
                    </p>
                </div>
            </a>
        </div>

        {{-- Tarjetas de Tope Mensual y Fecha Tope SIFEN (Compactas y Claras) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Tarjeta Tope Mensual --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $bordeTope }} p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $tope['excedido'] ? 'bg-red-500' : ($pctTope >= 80 ? 'bg-amber-500' : 'bg-purple-500') }}"></span>
                            <span class="text-xs font-bold uppercase tracking-wider {{ $tope['excedido'] ? 'text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                Límite Mensual de Facturación
                            </span>
                        </div>
                        @if($esAdminTope)
                            <button type="button" id="btn-tope-mes"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    data-tope='@json($topeSwal)'>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                {{ $tope['sin_tope'] ? 'Fijar tope' : 'Ajustar' }}
                            </button>
                        @endif
                    </div>

                    @if($tope['sin_tope'])
                        <div class="mt-2.5 flex items-baseline gap-2">
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Sin tope fijado</p>
                            <span class="text-xs text-gray-500 dark:text-gray-400">· Emisión libre sin techo fiscal</span>
                        </div>
                    @else
                        <div class="mt-2.5 flex items-baseline justify-between gap-2">
                            <p class="text-base font-extrabold text-gray-900 dark:text-gray-100">
                                {{ number_format((float) $tope['usado'], 0, ',', '.') }}
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">/ {{ number_format((float) $tope['limite'], 0, ',', '.') }} PYG</span>
                            </p>
                            <span class="text-xs font-bold {{ $tope['excedido'] ? 'text-red-600 dark:text-red-400' : 'text-purple-600 dark:text-purple-400' }}">
                                {{ number_format($pctTope, 1) }}%
                            </span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden mt-2">
                            <div style="width: {{ $pctTope }}%; background: {{ $colorBarra }}; height: 100%; transition: width 0.4s ease;"></div>
                        </div>
                        <p class="text-xs mt-1.5 {{ $tope['excedido'] ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                            @if($tope['excedido'])
                                ⚠️ Tope superado por {{ number_format((float) $tope['usado'] - (float) $tope['limite'], 0, ',', '.') }} PYG.
                            @else
                                Restan {{ number_format((float) $tope['restante'], 0, ',', '.') }} PYG para emitir en este período.
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            {{-- Tarjeta Fecha Tope SIFEN --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $bordeFechaTope }} p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $fechaTope['vencida'] ? 'bg-red-500' : ($fechaTope['sin_tope'] ? 'bg-gray-400' : 'bg-sky-500') }}"></span>
                            <span class="text-xs font-bold uppercase tracking-wider {{ $fechaTope['vencida'] ? 'text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                Fecha Tope de Emisión SIFEN
                            </span>
                        </div>
                        @if($esAdminTope)
                            <button type="button" id="btn-fecha-tope"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    data-fecha="{{ $fechaTope['fecha'] ?? '' }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ $fechaTope['sin_tope'] ? 'Fijar fecha' : 'Cambiar' }}
                            </button>
                        @endif
                    </div>

                    @if($fechaTope['sin_tope'])
                        <div class="mt-2.5 flex items-baseline gap-2">
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Sin fecha tope</p>
                            <span class="text-xs text-gray-500 dark:text-gray-400">· Se admite cualquier fecha de emisión válida</span>
                        </div>
                    @else
                        <div class="mt-2.5 flex items-baseline gap-2">
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $fechaTope['label'] }}</p>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $fechaTope['vencida'] ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' }}">
                                {{ $fechaTope['vencida'] ? 'Vencida' : 'Activa' }}
                            </span>
                        </div>
                        <p class="text-xs mt-1 text-gray-500 dark:text-gray-400">
                            {{ $fechaTope['vencida'] ? 'Se exige fecha igual o anterior al límite fijado para el envío a la SET.' : 'Solo se admitirán emisiones con fecha igual o anterior.' }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Formularios administrativos ocultos --}}
        @if($esAdminTope)
            <form id="form-limite-mes" method="POST" action="{{ route('facturas.limite-mes') }}" class="hidden">
                @csrf
                <input type="hidden" name="mes" id="input-mes-limite" value="{{ $tope['mes'] }}">
                <input type="hidden" name="monto_limite" id="input-monto-limite" value="">
            </form>
            <form id="form-fecha-tope" method="POST" action="{{ route('facturas.fecha-tope-emision') }}" class="hidden">
                @csrf
                @if(request('mes'))
                    <input type="hidden" name="mes" value="{{ request('mes') }}">
                @endif
                <input type="hidden" name="fecha_tope" id="input-fecha-tope" value="">
            </form>
        @endif
    </div>

    {{-- PAQUETE A & B: Pestañas de Cola SIFEN 1-Clic, Buscador Global y Tabla --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{-- Pestañas de Cola de Facturas --}}
        <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/80 px-4 pt-3 flex flex-wrap items-center justify-between gap-3">
            <nav class="flex flex-wrap gap-1 -mb-px" aria-label="Pestañas de Facturas">
                @php
                    $tabsConfig = [
                        'todas' => ['label' => 'Todas', 'icon' => '📋', 'count' => $tabCounts['todas'] ?? 0],
                        'emitidas' => ['label' => 'Emitidas SIFEN', 'icon' => '✅', 'count' => $tabCounts['emitidas'] ?? 0],
                        'borradores' => ['label' => 'Borradores', 'icon' => '📝', 'count' => $tabCounts['borradores'] ?? 0],
                        'lotes' => ['label' => 'Lotes en Proceso', 'icon' => '⏳', 'count' => $tabCounts['lotes'] ?? 0, 'alert' => ($tabCounts['lotes'] ?? 0) > 0],
                        'rechazadas' => ['label' => 'Rechazadas', 'icon' => '⚠️', 'count' => $tabCounts['rechazadas'] ?? 0, 'danger' => ($tabCounts['rechazadas'] ?? 0) > 0],
                        'anuladas' => ['label' => 'Anuladas', 'icon' => '🚫', 'count' => $tabCounts['anuladas'] ?? 0],
                    ];
                @endphp

                @foreach($tabsConfig as $tabKey => $tabData)
                    @php
                        $isActive = ($activeTab === $tabKey);
                        $tabUrl = route('facturas.index', array_merge(request()->except(['page']), ['tab' => $tabKey]));
                    @endphp
                    <a href="{{ $tabUrl }}"
                       class="inline-flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold rounded-t-lg border-b-2 transition-all
                              {{ $isActive
                                    ? 'border-purple-600 text-purple-700 dark:text-purple-300 bg-white dark:bg-gray-800 shadow-sm'
                                    : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:border-gray-300' }}">
                        <span>{{ $tabData['icon'] }}</span>
                        <span>{{ $tabData['label'] }}</span>
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold
                                     @if(!empty($tabData['danger'])) bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300 animate-pulse
                                     @elseif(!empty($tabData['alert'])) bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 animate-pulse
                                     @elseif($isActive) bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300
                                     @else bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 @endif">
                            {{ $tabData['count'] }}
                        </span>
                    </a>
                @endforeach
            </nav>

            {{-- Selector de cantidad por página --}}
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 pb-2">
                <span>Ver:</span>
                @foreach([15, 25, 50, 100] as $n)
                    <a href="{{ route('facturas.index', array_merge(request()->except(['page', 'per_page']), ['per_page' => $n])) }}"
                       class="px-2 py-1 rounded text-xs font-medium {{ ($perPage ?? 15) == $n ? 'bg-purple-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                        {{ $n }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Formulario de Búsqueda Omnipresente (q) y Filtros Avanzados --}}
        @php
            $filtrosActivosCount = collect([
                request('desde'),
                request('hasta'),
                request('aprobacion_desde'),
                request('aprobacion_hasta'),
                request('tipo_documento'),
                request('sifen_estado'),
                request()->boolean('lote_pendiente') ? '1' : null,
                request('cliente_id'),
            ])->filter(fn ($v) => filled($v))->count();
        @endphp

        <form id="form-filtros-fe" method="GET" action="{{ route('facturas.index') }}"
              class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40">
            @if(request('tab'))
                <input type="hidden" name="tab" value="{{ request('tab') }}">
            @endif
            @if(request('mes'))
                <input type="hidden" name="mes" value="{{ request('mes') }}">
            @endif
            @if(request('per_page'))
                <input type="hidden" name="per_page" value="{{ request('per_page') }}">
            @endif

            <div class="flex flex-col sm:flex-row items-center gap-2">
                {{-- Omnisearch input (CDC, Nro Factura, Documento, Cliente) --}}
                <div class="relative flex-1 w-full">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    </span>
                    <input type="search" name="q" id="input-busqueda-fe"
                           value="{{ request('q') }}"
                           placeholder="Buscar por CDC (44 dígitos), Factura N° (001-001-...), RUC, Cédula o Receptor..."
                           class="w-full h-10 pl-9 pr-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 shadow-sm transition-all">

                    @if(request('q'))
                        <a href="{{ route('facturas.index', request()->except(['q', 'page'])) }}"
                           class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                           title="Limpiar búsqueda">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>

                {{-- Botón de filtros avanzados popup --}}
                <div class="relative flex items-center gap-2 w-full sm:w-auto shrink-0 justify-end">
                    <button type="button" id="btn-filtros-avanzados"
                            class="inline-flex items-center gap-2 h-10 px-3.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 shadow-sm transition-colors">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4"/></svg>
                        <span>Filtros</span>
                        @if($filtrosActivosCount > 0)
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-600 text-white text-[10px] font-bold">
                                {{ $filtrosActivosCount }}
                            </span>
                        @endif
                    </button>

                    <button type="submit"
                            class="h-10 px-4 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700 shadow-sm transition-colors">
                        Buscar
                    </button>

                    @if(request('q') || $filtrosActivosCount > 0)
                        <a href="{{ route('facturas.index', array_filter(['mes' => request('mes'), 'tab' => request('tab')])) }}"
                           class="h-10 inline-flex items-center px-3 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors"
                           title="Quitar todos los filtros aplicados">
                            Limpiar todo
                        </a>
                    @endif
                </div>
            </div>

            {{-- Panel flotante de filtros avanzados --}}
            <div id="panel-filtros-avanzados" class="hidden mt-3 p-4 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Filtros Avanzados</span>
                    <button type="button" id="btn-cerrar-filtros-avanzados" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs font-semibold">Cerrar</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    {{-- Tipo de documento --}}
                    <div>
                        <label for="filtro-tipo-doc" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Tipo de Documento</label>
                        <select name="tipo_documento" id="filtro-tipo-doc" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">Todos los tipos</option>
                            @foreach(App\Models\Factura::tiposDocumento() as $tipoKey => $tipoLabel)
                                <option value="{{ $tipoKey }}" {{ request('tipo_documento') === $tipoKey ? 'selected' : '' }}>{{ $tipoLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Estado SIFEN específico --}}
                    <div>
                        <label for="filtro-sifen-estado" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Estado SIFEN</label>
                        <select name="sifen_estado" id="filtro-sifen-estado" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">Cualquier estado SIFEN</option>
                            <option value="autorizado" {{ request('sifen_estado') === 'autorizado' ? 'selected' : '' }}>Autorizado por DNIT</option>
                            <option value="rechazado" {{ request('sifen_estado') === 'rechazado' ? 'selected' : '' }}>Rechazado por DNIT</option>
                            <option value="lote_pendiente" {{ request('sifen_estado') === 'lote_pendiente' ? 'selected' : '' }}>Lote pendiente</option>
                            <option value="en_cola" {{ request('sifen_estado') === 'en_cola' ? 'selected' : '' }}>En cola / Emitiendo</option>
                        </select>
                    </div>

                    {{-- Fechas de Emisión --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha Emisión Desde</label>
                        <input type="date" name="desde" value="{{ request('desde') }}" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha Emisión Hasta</label>
                        <input type="date" name="hasta" value="{{ request('hasta') }}" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    </div>

                    {{-- Fechas de Aprobación SET --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Aprobación SET Desde</label>
                        <input type="date" name="aprobacion_desde" value="{{ request('aprobacion_desde') }}" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Aprobación SET Hasta</label>
                        <input type="date" name="aprobacion_hasta" value="{{ request('aprobacion_hasta') }}" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition-colors">
                        Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>

        {{-- PAQUETE C: Barra Flotante de Acciones Masivas SIFEN --}}
        <div id="barra-acciones-masivas" class="hidden sticky top-0 z-20 px-4 py-2.5 bg-indigo-900 text-white shadow-md flex flex-wrap items-center justify-between gap-3 transition-all">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-700 text-xs font-bold" id="badge-contador-seleccionados">0</span>
                <span class="text-xs font-medium">factura(s) seleccionada(s)</span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" id="btn-masivo-consultar-lotes" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Consultar Lotes
                </button>

                @can('facturas.crear')
                    <button type="button" id="btn-masivo-emitir" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Emitir Borradores a SIFEN
                    </button>
                @endcan

                <button type="button" id="btn-deseleccionar-todos" class="px-2.5 py-1.5 text-xs text-indigo-200 hover:text-white hover:underline">
                    Deseleccionar
                </button>
            </div>
        </div>

        {{-- Formularios masivos ocultos --}}
        <form method="POST" action="{{ route('facturas.consultar-lotes') }}" id="form-consultar-lotes-masivo" class="hidden">
            @csrf
        </form>
        <form method="POST" action="{{ route('facturas.emitir-lote') }}" id="form-emitir-lote-masivo" class="hidden">
            @csrf
        </form>

        {{-- PAQUETE B: Tabla de Facturas Enriquecida con Accesos Directos --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-xs">
                <thead class="bg-gray-50/80 dark:bg-gray-700/50 uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                        <th class="px-3 py-3 w-10 text-center">
                            <input type="checkbox" id="seleccionar-todos-fe"
                                   class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500"
                                   title="Seleccionar todas las visibles">
                        </th>
                        <th class="px-3 py-3">Comprobante</th>
                        <th class="px-3 py-3">Receptor / Cliente</th>
                        <th class="px-3 py-3">CDC & Estado SIFEN</th>
                        <th class="px-3 py-3">Emisión & SET</th>
                        <th class="px-3 py-3 text-right">Total PYG</th>
                        <th class="px-3 py-3 text-center">Accesos KuDE / XML</th>
                        <th class="px-3 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                    @forelse($facturas as $f)
                        @php
                            $pendienteLote = $f->lotePendienteSifen();
                            $enCola = $f->enColaSifen();
                            $esRechazada = ($f->set_estado_envio === 'rechazado');
                            $esAutorizada = ($f->set_estado_envio === 'autorizado');
                            $puedeKude = $f->puedeImprimirKude();
                            $tieneXml = (bool) $f->xml_path && ! str_contains((string) $f->xml_path, 'DE_borrador_');
                            $rechazoData = $esRechazada ? $f->respuestaSifenResumen() : null;
                        @endphp
                        <tr class="hover:bg-purple-50/30 dark:hover:bg-gray-700/40 transition-colors {{ $esRechazada ? 'bg-rose-50/20 dark:bg-rose-900/10' : ($pendienteLote || $enCola ? 'bg-indigo-50/25 dark:bg-indigo-900/10' : '') }}"
                            data-factura-id="{{ $f->id }}">
                            {{-- Checkbox --}}
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" value="{{ $f->id }}"
                                       data-estado="{{ $f->estado }}"
                                       data-lote="{{ $pendienteLote ? '1' : '0' }}"
                                       class="chk-factura rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                            </td>

                            {{-- Comprobante (Número, Tipo e ID) --}}
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-1.5 font-mono text-sm font-bold text-gray-900 dark:text-gray-100">
                                    <span>{{ $f->numero_completo ?? 'Sin número' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ App\Models\Factura::tiposDocumento()[$f->tipo_documento] ?? $f->tipo_documento }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">#{{ $f->id }}</span>
                                </div>
                            </td>

                            {{-- Receptor / Cliente --}}
                            <td class="px-3 py-3">
                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate max-w-[200px]" title="{{ $f->receptorNombreCompleto() }}">
                                    @if($f->esOcasional())
                                        <span>{{ $f->receptorNombreCompleto() }}</span>
                                        <span class="inline-flex ml-1 px-1 py-0.2 rounded text-[9px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">Ocasional</span>
                                    @elseif($f->cliente)
                                        <a href="{{ route('clientes.edit', $f->cliente) }}" class="text-purple-600 dark:text-purple-400 hover:underline">
                                            {{ $f->cliente->nombre }} {{ $f->cliente->apellido }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 font-mono mt-0.5">
                                    Doc: {{ $f->receptorDocumentoEfectivo() ?? '—' }}
                                </div>
                            </td>

                            {{-- CDC & Estado SIFEN --}}
                            <td class="px-3 py-3">
                                @if($f->set_cdc)
                                    <div class="flex items-center gap-1">
                                        <span class="font-mono text-[11px] text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded select-all" title="CDC: {{ $f->set_cdc }}">
                                            {{ substr($f->set_cdc, 0, 8) }}...{{ substr($f->set_cdc, -8) }}
                                        </span>
                                        <button type="button"
                                                class="js-btn-copy-cdc p-1 text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                data-cdc="{{ $f->set_cdc }}"
                                                title="Copiar CDC de 44 dígitos">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                    </div>
                                @endif

                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    {{-- Badge Estado General --}}
                                    @php $estados = App\Models\Factura::estados(); @endphp
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold
                                        @if($f->estado === 'emitida') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                        @elseif($f->estado === 'anulada') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                        {{ $estados[$f->estado] ?? $f->estado }}
                                    </span>

                                    {{-- Sub-estado SIFEN --}}
                                    @if($esAutorizada)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aprobado SET
                                        </span>
                                    @elseif($enCola)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse">
                                            Emitiendo…
                                        </span>
                                    @elseif($f->set_estado_envio === 'consultando')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse">
                                            Consultando…
                                        </span>
                                    @elseif($pendienteLote)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 animate-pulse" title="Lote SIFEN: {{ $f->set_nro_lote }}">
                                            Lote #{{ $f->set_nro_lote }}
                                        </span>
                                    @elseif($esRechazada)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200 cursor-help"
                                              title="{{ $rechazoData['mensaje'] ?? 'Documento rechazado por la SET' }}">
                                            ⚠️ Rechazo{{ !empty($rechazoData['codigo']) ? ' '.$rechazoData['codigo'] : '' }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Emisión & SET --}}
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="text-xs text-gray-900 dark:text-gray-100 font-medium">
                                    {{ $f->fecha_emision?->format('d/m/Y') }}
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                    @if($f->set_fecha_autorizacion)
                                        SET: {{ $f->set_fecha_autorizacion->format('d/m/Y H:i') }}
                                    @else
                                        Vence: {{ $f->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
                                    @endif
                                </div>
                            </td>

                            {{-- Total PYG --}}
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <div class="text-sm font-extrabold text-gray-900 dark:text-gray-100">
                                    {{ number_format($f->total, 0, ',', '.') }}
                                    <span class="text-[10px] font-normal text-gray-500">{{ $f->moneda }}</span>
                                </div>
                                <div class="text-[10px] text-gray-400">
                                    IVA: {{ number_format($f->total_impuestos, 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- Accesos Directos KuDE / POS / XML / WhatsApp --}}
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center justify-center gap-1 bg-gray-50 dark:bg-gray-700/60 p-1 rounded-lg border border-gray-200/60 dark:border-gray-600/60">
                                    {{-- Botón KuDE PDF --}}
                                    @if($puedeKude)
                                        <a href="{{ route('facturas.kude', $f) }}" target="_blank"
                                           class="p-1.5 rounded-md text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                                           title="Descargar KuDE Oficial en PDF">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </a>
                                        <a href="{{ route('facturas.kude-pos', $f) }}" target="_blank"
                                           class="p-1.5 rounded-md text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors"
                                           title="Imprimir KuDE Térmico POS 80mm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                    @endif

                                    {{-- Botón XML Oficial --}}
                                    @if($tieneXml)
                                        <a href="{{ route('facturas.xml', $f) }}"
                                           class="p-1.5 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                           title="Descargar XML firmado SIFEN">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        </a>
                                    @endif

                                    {{-- Botón WhatsApp KuDE --}}
                                    @if($puedeKude && config('whatsapp.enabled'))
                                        <button type="button"
                                                class="js-btn-whatsapp p-1.5 rounded-md text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors"
                                                data-id="{{ $f->id }}"
                                                data-numero="{{ $f->numero_completo ?? '#'.$f->id }}"
                                                data-telefono="{{ $f->receptorTelefonoEfectivo() ?? '' }}"
                                                title="Enviar KuDE por WhatsApp">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                                        </button>
                                    @endif

                                    @if(!$puedeKude && !$tieneXml)
                                        <span class="text-[10px] text-gray-400 px-1.5 py-0.5">Pendiente</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Acciones (Previsualizar, Emitir, Menú) --}}
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    {{-- Botón rápido: Previsualizar en Modal --}}
                                    <button type="button"
                                            class="js-btn-preview p-1.5 rounded-lg text-purple-600 dark:text-purple-400 hover:bg-purple-100 dark:hover:bg-purple-900/40 transition-colors"
                                            data-id="{{ $f->id }}"
                                            title="Previsualización rápida de la factura">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>

                                    {{-- Botón directo de Emitir si es Borrador --}}
                                    @can('facturas.crear')
                                        @if($f->estado === 'borrador' && !$enCola && !$pendienteLote)
                                            <form action="{{ route('facturas.emitir', $f) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('¿Emitir factura {{ $f->numero_completo ?? '#'.$f->id }} a SIFEN?');">
                                                @csrf
                                                <button type="submit"
                                                        class="p-1.5 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors"
                                                        title="Emitir directamente a SIFEN">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan

                                    {{-- Menú Kebab de Más Acciones --}}
                                    @php
                                        $esAdminFe = (bool) auth()->user()?->esAdministrador();
                                        $menuFe = [];
                                        $menuFe[] = [
                                            'type' => 'link',
                                            'label' => 'Ver ficha completa',
                                            'url' => route('facturas.show', $f),
                                        ];
                                        if ($pendienteLote && $f->set_estado_envio !== 'consultando') {
                                            $menuFe[] = [
                                                'type' => 'lote',
                                                'label' => 'Consultar lote SIFEN',
                                                'url' => route('facturas.consultar-lote', $f),
                                            ];
                                        } elseif ($f->estado === 'borrador' && ! $enCola && ! $pendienteLote) {
                                            $menuFe[] = [
                                                'type' => 'link',
                                                'label' => 'Editar borrador',
                                                'url' => route('facturas.edit', $f),
                                            ];
                                        }
                                        if ($esAdminFe && $f->puedeCancelarPorEvento()) {
                                            $menuFe[] = [
                                                'type' => 'cancelar',
                                                'label' => 'Cancelar en SIFEN',
                                                'url' => route('facturas.cancelar', $f),
                                                'numero' => $f->numero_completo ?? '#'.$f->id,
                                                'limite' => $f->fechaLimiteCancelacionEvento()?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                                            ];
                                        }
                                        if ($esAdminFe && $f->puedePrepararNotaCredito()) {
                                            $menuFe[] = [
                                                'type' => 'nc',
                                                'label' => 'Preparar Nota de crédito',
                                                'url' => route('facturas.nota-credito', $f),
                                                'numero' => $f->numero_completo ?? '#'.$f->id,
                                            ];
                                        }
                                    @endphp

                                    <button type="button"
                                            class="js-fe-acciones-menu p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                            title="Más opciones"
                                            aria-haspopup="menu"
                                            aria-expanded="false"
                                            data-menu-b64="{{ base64_encode(json_encode($menuFe, JSON_UNESCAPED_UNICODE)) }}">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No se encontraron facturas</p>
                                    <p class="text-xs text-gray-500">Prueba ajustando los filtros de búsqueda o el mes de emisión seleccionado.</p>
                                    @can('facturas.crear')
                                        <div class="pt-2">
                                            <a href="{{ route('facturas.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-700 transition-colors">
                                                + Nueva Factura
                                            </a>
                                        </div>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($facturas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                {{ $facturas->links() }}
            </div>
        @endif
    </div>
</div>

{{-- PAQUETE C: Modal de Previsualización Rápida (Quick View) --}}
<div id="modal-factura-quick" class="fixed inset-0 z-50 hidden" aria-hidden="true" role="dialog">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" data-cerrar-preview></div>
    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-10 flex items-center justify-center">
        <div class="relative w-full max-w-3xl rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xl overflow-hidden transition-all text-gray-900 dark:text-gray-100">
            {{-- Encabezado Modal --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-700/50">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold font-mono" id="qv-numero">Factura #...</h3>
                            <span id="qv-badge-estado" class="px-2 py-0.5 rounded text-[11px] font-bold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">...</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400" id="qv-tipo-doc">Tipo Documento</p>
                    </div>
                </div>
                <button type="button" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" data-cerrar-preview>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Contenido Modal --}}
            <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                {{-- Alerta de Rechazo SIFEN si existe --}}
                <div id="qv-alerta-rechazo" class="hidden p-3.5 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs">
                    <p class="font-bold flex items-center gap-1.5 text-rose-900 dark:text-rose-100 mb-1">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        SIFEN rechazó este documento
                    </p>
                    <p id="qv-rechazo-mensaje" class="text-rose-700 dark:text-rose-300">...</p>
                </div>

                {{-- Datos Receptor y Timbrado --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50/70 dark:bg-gray-700/30 p-4 rounded-xl border border-gray-200/60 dark:border-gray-700/60 text-xs">
                    <div>
                        <span class="text-gray-500 uppercase font-semibold text-[10px] tracking-wider">Receptor / Cliente</span>
                        <p class="font-bold text-sm text-gray-900 dark:text-gray-100 mt-0.5" id="qv-receptor-nombre">...</p>
                        <p class="text-gray-600 dark:text-gray-300 mt-0.5" id="qv-receptor-doc">RUC/CI: ...</p>
                        <p class="text-gray-500 dark:text-gray-400 mt-0.5 truncate" id="qv-receptor-dir">Dirección: ...</p>
                    </div>
                    <div class="md:text-right">
                        <span class="text-gray-500 uppercase font-semibold text-[10px] tracking-wider">Fechas y Timbrado</span>
                        <p class="text-gray-700 dark:text-gray-300 mt-0.5">Emisión: <strong id="qv-fecha-emision">...</strong></p>
                        <p class="text-gray-600 dark:text-gray-400 mt-0.5" id="qv-fecha-autorizacion">Autorización SET: ...</p>
                        <p class="text-gray-500 dark:text-gray-400 mt-0.5" id="qv-timbrado">Timbrado: ...</p>
                    </div>
                </div>

                {{-- Sección CDC SIFEN --}}
                <div id="qv-seccion-cdc" class="hidden p-3.5 rounded-xl bg-purple-50/60 dark:bg-purple-900/20 border border-purple-200/60 dark:border-purple-800/40 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300">Código de Control (CDC)</span>
                    <div class="flex items-center justify-between gap-2 mt-1">
                        <span class="font-mono text-xs text-purple-900 dark:text-purple-100 break-all select-all font-semibold" id="qv-cdc-texto">...</span>
                        <button type="button" id="qv-btn-copiar-cdc" class="px-2.5 py-1 rounded bg-purple-600 text-white font-semibold text-xs hover:bg-purple-700 shrink-0">
                            Copiar
                        </button>
                    </div>
                </div>

                {{-- Tabla de Ítems --}}
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Detalle de conceptos facturados</span>
                    <div class="mt-2 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 font-semibold text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-3 py-2 text-left">Descripción</th>
                                    <th class="px-3 py-2 text-right">Cant.</th>
                                    <th class="px-3 py-2 text-right">P. Unit.</th>
                                    <th class="px-3 py-2 text-right">IVA</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="qv-items-body" class="divide-y divide-gray-100 dark:divide-gray-700/50 bg-white dark:bg-gray-800">
                                {{-- Llenado dinámico --}}
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Resumen Totales --}}
                <div class="flex justify-end pt-2">
                    <div class="w-64 space-y-1 text-xs text-right">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Subtotal:</span>
                            <span id="qv-subtotal" class="font-semibold">0 PYG</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Total Impuestos:</span>
                            <span id="qv-impuestos" class="font-semibold">0 PYG</span>
                        </div>
                        <div class="flex justify-between text-base font-extrabold text-purple-700 dark:text-purple-300 pt-1 border-t border-gray-200 dark:border-gray-700">
                            <span>Total General:</span>
                            <span id="qv-total">0 PYG</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer Modal con accesos directos --}}
            <div class="px-6 py-3.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <a id="qv-link-pdf" href="#" target="_blank" class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 shadow-sm transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        KuDE PDF
                    </a>
                    <a id="qv-link-pos" href="#" target="_blank" class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 shadow-sm transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        KuDE POS 80mm
                    </a>
                    <a id="qv-link-xml" href="#" class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-600 text-white text-xs font-semibold hover:bg-gray-700 shadow-sm transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        XML Firmado
                    </a>
                </div>

                <div class="flex items-center gap-2">
                    <a id="qv-link-show" href="#" class="px-3.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                        Ver Ficha Completa →
                    </a>
                    <button type="button" class="px-3.5 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold hover:bg-gray-300 dark:hover:bg-gray-600" data-cerrar-preview>
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- PAQUETE B: Modal Universal de WhatsApp KuDE --}}
<div id="modal-wa-universal" class="fixed inset-0 z-50 hidden" aria-hidden="true" role="dialog">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-xs" data-cerrar-wa></div>
    <div class="fixed inset-0 z-10 overflow-y-auto p-4 flex items-center justify-center">
        <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-2xl p-6 text-gray-900 dark:text-gray-100">
            <h3 class="text-base font-bold flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                </span>
                Enviar KuDE por WhatsApp
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="wa-modal-subtitulo">
                Se enviará el comprobante electrónico KuDE en PDF al número indicado.
            </p>

            <form id="form-wa-universal-submit" method="POST" action="" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="guardar_telefono" id="wa-univ-guardar-tel" value="0">

                <div class="space-y-2">
                    <label class="flex items-start gap-2.5 p-3 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input type="radio" name="destino" value="registrado" checked id="wa-univ-opt-registrado" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                        <div class="text-xs">
                            <span class="font-bold block">Número registrado del cliente</span>
                            <span class="text-gray-500 font-mono" id="wa-univ-tel-label">...</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 p-3 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input type="radio" name="destino" value="otro" id="wa-univ-opt-otro" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                        <div class="text-xs flex-1">
                            <span class="font-bold block mb-1">Otro número de destino</span>
                            <input type="tel" name="telefono" id="wa-univ-input-otro" placeholder="Ej: 0981123456"
                                   class="w-full h-8 px-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs">
                        </div>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3">
                    <button type="button" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 font-semibold" data-cerrar-wa>Cancelar</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition-colors">
                        Enviar WhatsApp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Scripts de acciones SIFEN y control de Tope --}}
@include('facturas._sifen-acciones-script')

<script>
(function () {
    // ----------------------------------------------------
    // 1. Manejo del Tope Mensual y Fecha Tope SIFEN (Swal)
    // ----------------------------------------------------
    const btnTope = document.getElementById('btn-tope-mes');
    const formTope = document.getElementById('form-limite-mes');
    const inputMesTope = document.getElementById('input-mes-limite');
    const inputMontoTope = document.getElementById('input-monto-limite');

    function swalTheme() {
        return (window.infinitySwalTheme && window.infinitySwalTheme()) || {};
    }

    function parseMonto(raw) {
        const s = String(raw || '').trim().replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
        if (s === '') return null;
        const n = Number(s);
        return Number.isFinite(n) ? n : NaN;
    }

    if (btnTope && formTope && inputMesTope && inputMontoTope) {
        function leerCfg() {
            try {
                return JSON.parse(btnTope.getAttribute('data-tope') || '{}');
            } catch (e) {
                return {};
            }
        }

        btnTope.addEventListener('click', function () {
            const cfg = leerCfg();
            const mesInicial = cfg.mes || '';
            const actual = (cfg.limites && cfg.limites[mesInicial]) ? cfg.limites[mesInicial] : (cfg.limite || null);

            if (typeof Swal === 'undefined') {
                const monto = prompt('Monto tope en PYG (deje vacío para quitar):', actual || '');
                if (monto === null) return;
                inputMesTope.value = mesInicial;
                inputMontoTope.value = monto ? String(Math.round(parseMonto(monto))) : '';
                formTope.submit();
                return;
            }

            Swal.fire(Object.assign({
                title: 'Tope de Facturación Mensual',
                html: '<div class="text-left text-xs space-y-2">' +
                        '<p class="text-gray-500">Tope aplicable al mes <strong>' + (cfg.label || mesInicial) + '</strong>.</p>' +
                        '<label class="block font-bold">Monto máximo (PYG):</label>' +
                        '<input type="text" id="swal-tope-input" class="swal2-input" placeholder="Ej: 50000000" value="' + (actual ? Math.round(actual) : '') + '">' +
                        '<p class="text-gray-400 text-[11px]">Deje vacío para facturar sin límite en este período.</p>' +
                      '</div>',
                showCancelButton: true,
                showDenyButton: !!actual,
                confirmButtonText: 'Guardar',
                denyButtonText: 'Quitar tope',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#7c3aed',
                denyButtonColor: '#dc2626',
                preConfirm: function () {
                    const raw = document.getElementById('swal-tope-input')?.value;
                    if (!raw || raw.trim() === '') return { monto: null };
                    const n = parseMonto(raw);
                    if (!Number.isFinite(n) || n < 1) {
                        Swal.showValidationMessage('Ingrese un monto válido mayor a 0');
                        return false;
                    }
                    return { monto: n };
                }
            }, swalTheme())).then(function (res) {
                if (res.isDenied) {
                    inputMesTope.value = mesInicial;
                    inputMontoTope.value = '';
                    formTope.submit();
                } else if (res.isConfirmed && res.value) {
                    inputMesTope.value = mesInicial;
                    inputMontoTope.value = res.value.monto ? String(Math.round(res.value.monto)) : '';
                    formTope.submit();
                }
            });
        });
    }

    const btnFechaTope = document.getElementById('btn-fecha-tope');
    const formFechaTope = document.getElementById('form-fecha-tope');
    const inputFechaTope = document.getElementById('input-fecha-tope');

    if (btnFechaTope && formFechaTope && inputFechaTope) {
        btnFechaTope.addEventListener('click', function () {
            const actual = btnFechaTope.getAttribute('data-fecha') || '';
            if (typeof Swal === 'undefined') {
                const f = prompt('Fecha tope SIFEN (AAAA-MM-DD, vacío para quitar):', actual);
                if (f === null) return;
                inputFechaTope.value = f;
                formFechaTope.submit();
                return;
            }

            Swal.fire(Object.assign({
                title: 'Fecha Tope para SIFEN',
                html: '<div class="text-left text-xs space-y-2">' +
                        '<p class="text-gray-500">Tope de fecha de emisión exigido por la SET al enviar el DE.</p>' +
                        '<label class="block font-bold">Fecha máxima permitida:</label>' +
                        '<input type="date" id="swal-fecha-input" class="swal2-input" value="' + actual + '">' +
                      '</div>',
                showCancelButton: true,
                showDenyButton: !!actual,
                confirmButtonText: 'Guardar',
                denyButtonText: 'Quitar fecha tope',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0284c7',
                denyButtonColor: '#dc2626',
                preConfirm: function () {
                    const el = document.getElementById('swal-fecha-input');
                    return { fecha: el ? el.value : '' };
                }
            }, swalTheme())).then(function (res) {
                if (res.isDenied) {
                    inputFechaTope.value = '';
                    formFechaTope.submit();
                } else if (res.isConfirmed && res.value) {
                    inputFechaTope.value = res.value.fecha || '';
                    formFechaTope.submit();
                }
            });
        });
    }

    // ----------------------------------------------------
    // 2. Filtros avanzados colapsables
    // ----------------------------------------------------
    const btnFiltros = document.getElementById('btn-filtros-avanzados');
    const panelFiltros = document.getElementById('panel-filtros-avanzados');
    const btnCerrarFiltros = document.getElementById('btn-cerrar-filtros-avanzados');

    if (btnFiltros && panelFiltros) {
        btnFiltros.addEventListener('click', function (e) {
            e.preventDefault();
            panelFiltros.classList.toggle('hidden');
        });
    }
    if (btnCerrarFiltros && panelFiltros) {
        btnCerrarFiltros.addEventListener('click', function () {
            panelFiltros.classList.add('hidden');
        });
    }

    // ----------------------------------------------------
    // 3. Copiar CDC al portapapeles con Toast
    // ----------------------------------------------------
    function toastCopiado(msg) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-5 right-5 z-50 px-4 py-2 rounded-xl bg-purple-700 text-white text-xs font-bold shadow-xl flex items-center gap-2 transition-all transform translate-y-0 opacity-100';
        toast.innerHTML = '<svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
                          '<span>' + (msg || 'Copiado al portapapeles') + '</span>';
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(function () { toast.remove(); }, 300);
        }, 2200);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-btn-copy-cdc');
        if (!btn) return;
        const cdc = btn.getAttribute('data-cdc');
        if (!cdc) return;
        navigator.clipboard.writeText(cdc).then(function () {
            toastCopiado('¡CDC de 44 dígitos copiado!');
        }).catch(function () {
            prompt('Copie el CDC manualmente:', cdc);
        });
    });

    const qvBtnCopiarCdc = document.getElementById('qv-btn-copiar-cdc');
    if (qvBtnCopiarCdc) {
        qvBtnCopiarCdc.addEventListener('click', function () {
            const texto = document.getElementById('qv-cdc-texto')?.textContent?.trim();
            if (texto) {
                navigator.clipboard.writeText(texto).then(function () {
                    toastCopiado('¡CDC copiado!');
                });
            }
        });
    }

    // ----------------------------------------------------
    // 4. Previsualización Rápida en Modal (Quick View)
    // ----------------------------------------------------
    const modalPreview = document.getElementById('modal-factura-quick');
    const cerrarPreviewBtns = document.querySelectorAll('[data-cerrar-preview]');

    function cerrarPreview() {
        if (modalPreview) modalPreview.classList.add('hidden');
    }
    cerrarPreviewBtns.forEach(b => b.addEventListener('click', cerrarPreview));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-btn-preview');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        if (!id) return;

        // Abrir modal en estado cargando
        modalPreview.classList.remove('hidden');
        document.getElementById('qv-numero').textContent = 'Cargando Factura #' + id + '...';
        document.getElementById('qv-items-body').innerHTML = '<tr><td colspan="5" class="py-6 text-center text-gray-400">Obteniendo datos oficiales...</td></tr>';
        document.getElementById('qv-alerta-rechazo').classList.add('hidden');
        document.getElementById('qv-seccion-cdc').classList.add('hidden');
        document.getElementById('qv-link-pdf').classList.add('hidden');
        document.getElementById('qv-link-pos').classList.add('hidden');
        document.getElementById('qv-link-xml').classList.add('hidden');

        fetch('/facturas/' + id + '?json=1', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('qv-numero').textContent = 'Factura ' + data.numero;
            document.getElementById('qv-badge-estado').textContent = data.estado_label;
            document.getElementById('qv-tipo-doc').textContent = data.tipo_documento_label + (data.lote ? ' · Lote SIFEN: ' + data.lote : '');

            document.getElementById('qv-receptor-nombre').textContent = data.receptor?.nombre || 'Consumidor Final';
            document.getElementById('qv-receptor-doc').textContent = 'Doc: ' + (data.receptor?.documento || 'Sin doc') + (data.receptor?.telefono ? ' · Tel: ' + data.receptor.telefono : '');
            document.getElementById('qv-receptor-dir').textContent = 'Dirección: ' + (data.receptor?.direccion || 'No registrada');

            document.getElementById('qv-fecha-emision').textContent = data.fecha_emision || '—';
            document.getElementById('qv-fecha-autorizacion').textContent = data.set_fecha_autorizacion ? 'SET: ' + data.set_fecha_autorizacion : 'Estado: ' + (data.set_estado_envio || 'No enviado');
            document.getElementById('qv-timbrado').textContent = 'Timbrado: ' + (data.timbrado || '—') + ' (' + (data.timbrado_vigencia || '') + ')';

            if (data.cdc) {
                document.getElementById('qv-cdc-texto').textContent = data.cdc;
                document.getElementById('qv-seccion-cdc').classList.remove('hidden');
            }

            if (data.rechazo) {
                document.getElementById('qv-alerta-rechazo').classList.remove('hidden');
                document.getElementById('qv-rechazo-mensaje').textContent = (data.rechazo.codigo ? '[' + data.rechazo.codigo + '] ' : '') + (data.rechazo.mensaje || 'Error desconocido al validar en SIFEN');
            }

            // Llenar tabla de ítems
            let rowsHtml = '';
            (data.detalles || []).forEach(d => {
                rowsHtml += '<tr>' +
                    '<td class="px-3 py-2 text-gray-900 dark:text-gray-100 font-medium">' + d.descripcion + '</td>' +
                    '<td class="px-3 py-2 text-right">' + Number(d.cantidad).toLocaleString('es-PY') + '</td>' +
                    '<td class="px-3 py-2 text-right">' + Number(d.precio_unitario).toLocaleString('es-PY') + '</td>' +
                    '<td class="px-3 py-2 text-right text-gray-500">' + (d.porcentaje_impuesto ? d.porcentaje_impuesto + '%' : 'Exenta') + '</td>' +
                    '<td class="px-3 py-2 text-right font-bold">' + Number(d.total).toLocaleString('es-PY') + '</td>' +
                '</tr>';
            });
            document.getElementById('qv-items-body').innerHTML = rowsHtml || '<tr><td colspan="5" class="py-3 text-center text-gray-400">Sin ítems</td></tr>';

            // Totales
            document.getElementById('qv-subtotal').textContent = Number(data.subtotal || 0).toLocaleString('es-PY') + ' ' + data.moneda;
            document.getElementById('qv-impuestos').textContent = Number(data.total_impuestos || 0).toLocaleString('es-PY') + ' ' + data.moneda;
            document.getElementById('qv-total').textContent = Number(data.total || 0).toLocaleString('es-PY') + ' ' + data.moneda;

            // Links inferiores
            if (data.puede_imprimir_kude) {
                const linkPdf = document.getElementById('qv-link-pdf');
                linkPdf.href = data.kude_url;
                linkPdf.classList.remove('hidden');

                const linkPos = document.getElementById('qv-link-pos');
                linkPos.href = data.kude_pos_url;
                linkPos.classList.remove('hidden');
            }
            if (data.tiene_xml) {
                const linkXml = document.getElementById('qv-link-xml');
                linkXml.href = data.xml_url;
                linkXml.classList.remove('hidden');
            }
            document.getElementById('qv-link-show').href = data.show_url;
        })
        .catch(err => {
            document.getElementById('qv-items-body').innerHTML = '<tr><td colspan="5" class="py-4 text-center text-rose-500">Error al cargar datos de la factura.</td></tr>';
        });
    });

    // ----------------------------------------------------
    // 5. Modal de Envío por WhatsApp KuDE
    // ----------------------------------------------------
    const modalWa = document.getElementById('modal-wa-universal');
    const formWa = document.getElementById('form-wa-universal-submit');
    const waLabel = document.getElementById('wa-univ-tel-label');
    const waSubtitulo = document.getElementById('wa-modal-subtitulo');
    const waOptRegistrado = document.getElementById('wa-univ-opt-registrado');
    const waOptOtro = document.getElementById('wa-univ-opt-otro');
    const waInputOtro = document.getElementById('wa-univ-input-otro');

    function cerrarWa() {
        if (modalWa) modalWa.classList.add('hidden');
    }
    document.querySelectorAll('[data-cerrar-wa]').forEach(b => b.addEventListener('click', cerrarWa));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-btn-whatsapp');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        const numero = btn.getAttribute('data-numero');
        const tel = btn.getAttribute('data-telefono') || '';

        formWa.action = '/facturas/' + id + '/whatsapp';
        waSubtitulo.textContent = 'Envío de KuDE en PDF de la factura ' + numero;
        waLabel.textContent = tel ? tel : 'Sin teléfono registrado';

        if (tel) {
            waOptRegistrado.disabled = false;
            waOptRegistrado.checked = true;
        } else {
            waOptRegistrado.disabled = true;
            waOptOtro.checked = true;
        }
        waInputOtro.value = '';
        modalWa.classList.remove('hidden');
    });

    // ----------------------------------------------------
    // 6. Selección Masiva y Barra de Acciones Flotante
    // ----------------------------------------------------
    const chks = () => Array.from(document.querySelectorAll('.chk-factura'));
    const chkMaster = document.getElementById('seleccionar-todos-fe');
    const barraMasiva = document.getElementById('barra-acciones-masivas');
    const contadorMasivo = document.getElementById('badge-contador-seleccionados');
    const btnDeseleccionar = document.getElementById('btn-deseleccionar-todos');
    const btnMasivoConsultar = document.getElementById('btn-masivo-consultar-lotes');
    const btnMasivoEmitir = document.getElementById('btn-masivo-emitir');
    const formConsultarLotes = document.getElementById('form-consultar-lotes-masivo');
    const formEmitirLote = document.getElementById('form-emitir-lote-masivo');

    function actualizarBarraMasiva() {
        const seleccionados = chks().filter(c => c.checked);
        const count = seleccionados.length;
        if (contadorMasivo) contadorMasivo.textContent = String(count);
        if (barraMasiva) barraMasiva.classList.toggle('hidden', count === 0);

        if (chkMaster) {
            const total = chks().length;
            chkMaster.checked = total > 0 && count === total;
            chkMaster.indeterminate = count > 0 && count < total;
        }
    }

    if (chkMaster) {
        chkMaster.addEventListener('change', function () {
            chks().forEach(c => { c.checked = chkMaster.checked; });
            actualizarBarraMasiva();
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('chk-factura')) {
            actualizarBarraMasiva();
        }
    });

    if (btnDeseleccionar) {
        btnDeseleccionar.addEventListener('click', function () {
            chks().forEach(c => { c.checked = false; });
            actualizarBarraMasiva();
        });
    }

    if (btnMasivoConsultar && formConsultarLotes) {
        btnMasivoConsultar.addEventListener('click', function () {
            const seleccionados = chks().filter(c => c.checked).map(c => c.value);
            if (!seleccionados.length) {
                alert('Seleccione al menos una factura.');
                return;
            }
            if (!confirm('¿Consultar estado de ' + seleccionados.length + ' lote(s) en SIFEN?')) return;

            formConsultarLotes.innerHTML = '@csrf';
            seleccionados.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'factura_ids[]';
                inp.value = id;
                formConsultarLotes.appendChild(inp);
            });
            formConsultarLotes.submit();
        });
    }

    if (btnMasivoEmitir && formEmitirLote) {
        btnMasivoEmitir.addEventListener('click', function () {
            const borradores = chks().filter(c => c.checked && c.getAttribute('data-estado') === 'borrador').map(c => c.value);
            if (!borradores.length) {
                alert('Ninguna de las facturas seleccionadas está en estado Borrador.');
                return;
            }
            if (!confirm('¿Emitir ' + borradores.length + ' borrador(es) seleccionado(s) a SIFEN?')) return;

            formEmitirLote.innerHTML = '@csrf';
            borradores.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'factura_ids[]';
                inp.value = id;
                formEmitirLote.appendChild(inp);
            });
            formEmitirLote.submit();
        });
    }
})();
</script>
@endsection
