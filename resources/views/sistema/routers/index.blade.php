@extends('layouts.app')

@section('title', 'Routers')

@section('content')
@php
    $totalRouters = $kpis['total_routers'] ?? $routers->total();
    $onlineRouters = $kpis['online_routers'] ?? $routers->where('estado', \App\Models\Router::ESTADO_CONECTADO)->count();
    $offlineRouters = $kpis['offline_routers'] ?? $routers->where('estado', \App\Models\Router::ESTADO_DESCONECTADO)->count();
    $desconocidoRouters = $kpis['desconocido_routers'] ?? max(0, $totalRouters - $onlineRouters - $offlineRouters);
    $totalPools = $kpis['total_pools'] ?? 0;
    $totalRegistrados = $kpis['total_clientes_registrados'] ?? collect($statsClientes)->sum('registrados');
    $totalActivos = $kpis['total_clientes_activos'] ?? collect($statsClientes)->sum('activos');
    $pctGlobal = $totalRegistrados > 0 ? round(($totalActivos / $totalRegistrados) * 100, 1) : 0;
    $pctDisponibilidad = $totalRouters > 0 ? round(($onlineRouters / $totalRouters) * 100) : 0;
@endphp

<div class="max-w-7xl mx-auto space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="2" y="6" width="20" height="12" rx="3" stroke-width="2"/>
                        <circle cx="6" cy="12" r="1.5" fill="currentColor"/>
                        <circle cx="10" cy="12" r="1.5" fill="currentColor"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10h3m-3 4h3"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Routers MikroTik</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ number_format($totalRouters) }} equipo{{ $totalRouters === 1 ? '' : 's' }} en gestión · {{ number_format($totalActivos) }} clientes activos en servicio
                    </p>
                </div>
            </div>
        </div>

        <!-- Top Actions -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Batch Verify Button -->
            <button type="button" id="btn-verify-all"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-sky-50 dark:bg-sky-900/30 hover:bg-sky-100 dark:hover:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-300 dark:border-sky-700 text-sm font-semibold transition-all hover:scale-[1.02] active:scale-[0.98]"
                title="Comprobar la conexión API MikroTik de todos los routers en pantalla">
                <svg id="verify-all-icon" class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span id="verify-all-label">Verificar red</span>
                <span id="verify-all-badge" class="hidden px-2 py-0.5 rounded-full text-xs font-bold bg-sky-600 text-white">0/0</span>
            </button>

            <!-- Tools Dropdown -->
            <div class="relative" data-tools-menu>
                <button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" data-tools-toggle>
                    <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                    Herramientas
                    <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="hidden absolute right-0 mt-1.5 w-56 z-30 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl py-1.5" data-tools-panel>
                    <a href="{{ route('sistema.router-scripts.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Scripts MikroTik
                    </a>
                    <a href="{{ route('sistema.router-schedulers.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Schedulers
                    </a>
                    <a href="{{ route('sistema.router-network-backups.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                        Backup de red
                    </a>
                    <a href="{{ route('sistema.router-ip-pools.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        Pools de IP
                    </a>
                    <a href="{{ route('sistema.router-modelos.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        Catálogo de modelos
                    </a>
                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                    <a href="{{ route('sistema.router-caida-avisos.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        Alertas por caída
                    </a>
                </div>
            </div>

            <!-- New Router Button -->
            <a href="{{ route('sistema.routers.create') }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-semibold hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 shadow-sm transition-all hover:scale-[1.02] active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo router
            </a>
        </div>
    </div>

    <!-- NOC Metrics Deck (Package B) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Routers Fleet -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Flota MikroTik</span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="3" stroke-width="2"/><circle cx="6" cy="12" r="1.5" fill="currentColor"/><circle cx="10" cy="12" r="1.5" fill="currentColor"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10h3m-3 4h3"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100" id="deck-total-routers">{{ $totalRouters }}</p>
                <span class="text-xs text-gray-500 dark:text-gray-400">equipos</span>
            </div>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span id="deck-online-routers">{{ $onlineRouters }}</span> en línea
                </span>
                @if($offlineRouters > 0)
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span class="inline-flex items-center gap-1 font-semibold text-rose-500">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        <span id="deck-offline-routers">{{ $offlineRouters }}</span> caídos
                    </span>
                @endif
            </div>
        </div>

        <!-- Metric 2: Active Clients -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Clientes en Servicio</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold tracking-tight text-emerald-500 leading-none">{{ number_format($totalActivos) }}</p>
                <span class="text-xs text-gray-500 dark:text-gray-400">/ {{ number_format($totalRegistrados) }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Tasa de actividad</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $pctGlobal }}%</span>
            </div>
        </div>

        <!-- Metric 3: IP Pools -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pools de IP</span>
                <span class="p-2 rounded-xl bg-violet-50 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{{ $totalPools }}</p>
                <span class="text-xs text-gray-500 dark:text-gray-400">bloques</span>
            </div>
            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                <a href="{{ route('sistema.router-ip-pools.index') }}" class="text-violet-600 dark:text-violet-400 hover:underline font-medium">Ver asignaciones &rarr;</a>
            </div>
        </div>

        <!-- Metric 4: General Fleet Health -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Disponibilidad Flota</span>
                <span class="p-2 rounded-xl bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <p class="text-2xl font-bold tracking-tight text-sky-600 dark:text-sky-400" id="deck-health-pct">{{ $pctDisponibilidad }}%</p>
                <span class="text-xs text-gray-500 dark:text-gray-400">operatividad</span>
            </div>
            <div class="mt-2.5 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                <div id="deck-health-bar" class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $pctDisponibilidad }}%"></div>
            </div>
        </div>
    </div>

    <!-- Live Batch Health Check Progress Banner (Hidden by default) -->
    <div id="verify-all-banner" class="hidden rounded-2xl border border-sky-300 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/60 p-4 transition-all">
        <div class="flex items-center justify-between gap-4 mb-2">
            <div class="flex items-center gap-2.5">
                <div class="animate-spin text-sky-600 dark:text-sky-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-sky-900 dark:text-sky-100">Verificando conectividad de la flota MikroTik...</h3>
                    <p id="verify-all-status-text" class="text-xs text-sky-700 dark:text-sky-300">Iniciando comprobaciones de API...</p>
                </div>
            </div>
            <button type="button" id="verify-all-stop" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-sky-300 dark:border-sky-700 text-sky-700 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900/50">
                Detener
            </button>
        </div>
        <div class="w-full bg-sky-200 dark:bg-sky-900/60 rounded-full h-2 overflow-hidden">
            <div id="verify-all-progress-bar" class="h-full rounded-full bg-sky-500 transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" action="{{ route('sistema.routers.index') }}" id="router-filter-form"
        class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 sm:p-4 shadow-sm">
        <div class="flex flex-col lg:flex-row gap-3">
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" name="buscar" id="router-search-input" value="{{ request('buscar') }}"
                    placeholder="Buscar por nombre, IP, modelo, nodo..."
                    class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                <button type="button" id="router-search-clear" class="{{ request('buscar') ? '' : 'hidden' }} absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Limpiar búsqueda">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="sm:w-48">
                <select name="serie" id="router-filter-serie" class="w-full py-2.5 px-3 rounded-xl border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                    <option value="todas">Todas las series</option>
                    @foreach($series as $serie)
                        <option value="{{ $serie }}" {{ request('serie') == $serie ? 'selected' : '' }}>{{ $serie }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-56">
                <select name="nodo_id" id="router-filter-nodo" class="w-full py-2.5 px-3 rounded-xl border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                    <option value="todos">Todos los nodos</option>
                    @foreach($nodos as $nodo)
                        <option value="{{ $nodo->nodo_id }}" {{ request('nodo_id') == $nodo->nodo_id ? 'selected' : '' }}>
                            {{ $nodo->descripcion ?? "Nodo #{$nodo->nodo_id}" }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-purple-600 text-white rounded-xl text-sm font-semibold hover:bg-purple-700 transition-colors">
                    Filtrar
                </button>
                @if(request()->filled('buscar') || (request('serie') && request('serie') !== 'todas') || (request('nodo_id') && request('nodo_id') !== 'todos'))
                    <a href="{{ route('sistema.routers.index') }}" class="inline-flex items-center justify-center px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Restablecer filtros">
                        Limpiar
                    </a>
                @endif
            </div>
        </div>
    </form>

    <!-- Routers Grid (Package A) -->
    @if($routers->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-12 text-center">
            <img src="{{ asset('images/routers/mikrotik-generic.svg') }}" alt="" class="mx-auto h-24 w-48 object-contain opacity-60 mb-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">No se encontraron routers con esos criterios</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Probá cambiando los términos de búsqueda o restableciendo los filtros seleccionados.</p>
            <div class="mt-4 flex items-center justify-center gap-3">
                <a href="{{ route('sistema.routers.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Restablecer filtros</a>
                <a href="{{ route('sistema.routers.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-semibold hover:bg-purple-700 transition-colors">Nuevo router</a>
            </div>
        </div>
    @else
        <div id="routers-cards-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 items-stretch">
            @foreach($routers as $r)
                @php
                    $cat = $r->modeloCatalogo();
                    $serie = $cat['serie'] ?? 'MikroTik';
                    $stats = $statsClientes[$r->router_id] ?? ['registrados' => 0, 'activos' => 0];
                    $pctActivos = $stats['registrados'] > 0
                        ? min(100, round(($stats['activos'] / $stats['registrados']) * 100))
                        : 0;
                    $estado = strtolower((string) ($r->estado ?? \App\Models\Router::ESTADO_DESCONOCIDO));
                    $estadoOk = $estado === \App\Models\Router::ESTADO_CONECTADO;
                    $estadoBad = $estado === \App\Models\Router::ESTADO_DESCONECTADO;
                    $estadoLabel = $estadoOk ? 'Conectado' : ($estadoBad ? 'Desconectado' : 'Desconocido');

                    // Color semántico para la barra de salud
                    $healthBarColor = $pctActivos >= 85
                        ? 'bg-emerald-500'
                        : ($pctActivos >= 60 ? 'bg-purple-500' : 'bg-amber-500');
                @endphp
                <article class="router-card group relative flex flex-col h-full rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:border-purple-400/60 dark:hover:border-purple-500/50 hover:shadow-lg transition-all duration-200"
                    data-router-id="{{ $r->router_id }}"
                    data-search-text="{{ strtolower($r->nombre . ' ' . $r->ip . ' ' . $r->modeloEtiqueta() . ' ' . ($r->nodo?->descripcion ?? '') . ' ' . $serie) }}">

                    <!-- Card Header -->
                    <div class="px-5 pt-5 pb-3">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                {{ $serie }}
                            </span>

                            <!-- Dynamic Status Pill -->
                            <span class="router-status-pill inline-flex items-center gap-1.5 shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors
                                {{ $estadoOk
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/30'
                                    : ($estadoBad
                                        ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-1 ring-rose-500/30'
                                        : 'bg-gray-500/10 text-gray-500 dark:text-gray-400 ring-1 ring-gray-500/30') }}"
                                data-status="{{ $estado }}">
                                <span class="status-dot h-1.5 w-1.5 rounded-full {{ $estadoOk ? 'bg-emerald-500' : ($estadoBad ? 'bg-rose-500' : 'bg-gray-400') }}"></span>
                                <span class="status-text font-mono text-[11px]">
                                    @if($estadoOk && $r->ping_latencia_ms)
                                        {{ $r->ping_latencia_ms }}ms · Conectado
                                    @else
                                        {{ $estadoLabel }}
                                    @endif
                                </span>
                            </span>
                        </div>

                        <!-- Card Identity -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 truncate leading-snug" title="{{ $r->nombre }}">
                                    {{ $r->nombre }}
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ $r->modeloEtiqueta() }}</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                    {{ $r->nodo?->descripcion ?? 'Sin nodo' }} · <span class="font-mono">#{{ $r->router_id }}</span>
                                </p>
                            </div>
                            <img src="{{ $r->imagenUrl() }}" alt="{{ $r->modeloEtiqueta() }}"
                                class="h-12 w-16 object-contain opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-200 shrink-0"
                                onerror="this.onerror=null;this.src='{{ asset('images/routers/mikrotik-generic.svg') }}';">
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="px-5 pb-4 flex-1 flex flex-col justify-between">
                        <!-- IP Row with 1-Click Copy & WebFig link -->
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-mono text-sm font-bold text-gray-900 dark:text-gray-100 truncate" title="{{ $r->ip }}">
                                        {{ $r->ip }}
                                    </span>
                                    <!-- 1-Click Copy IP -->
                                    <button type="button" class="copy-ip-btn p-1 text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 rounded-md transition-colors"
                                        data-ip="{{ $r->ip }}"
                                        title="Copiar dirección IP">
                                        <svg class="w-3.5 h-3.5 copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <svg class="w-3.5 h-3.5 check-icon hidden text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                    <!-- WebFig Link -->
                                    <a href="http://{{ $r->ip }}" target="_blank" rel="noopener noreferrer"
                                        class="p-1 text-gray-400 hover:text-sky-600 dark:hover:text-sky-400 rounded-md transition-colors"
                                        title="Abrir WebFig MikroTik en nueva pestaña">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-200 dark:bg-gray-700 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:text-gray-200" title="Pools activos">
                                        <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        {{ $r->router_ip_pools_count ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Clients -->
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3 py-2.5">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Registrados</p>
                                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100 leading-none">{{ number_format($stats['registrados']) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3 py-2.5">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Activos</p>
                                <p class="mt-1 leading-none">
                                    <span class="text-2xl font-bold tabular-nums text-emerald-500">{{ number_format($stats['activos']) }}</span>
                                    <span class="text-sm font-medium text-gray-400">/ {{ number_format($stats['registrados']) }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Health Progress Bar -->
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                                <span>Salud Clientes</span>
                                <span class="tabular-nums font-semibold text-gray-700 dark:text-gray-300">{{ $pctActivos }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full rounded-full {{ $healthBarColor }} transition-all duration-300" style="width: {{ $pctActivos }}%"></div>
                            </div>
                        </div>

                        <!-- Direct Action Bar (Replaces Accordion, Package A) -->
                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-1.5">
                                <!-- Action 1: Test Connection -->
                                <button type="button"
                                    class="router-test-btn flex-1 inline-flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 hover:bg-sky-100 dark:bg-sky-900/30 dark:hover:bg-sky-900/50 border border-sky-200 dark:border-sky-700 transition-all active:scale-95"
                                    data-url="{{ route('sistema.routers.test-connection', $r) }}"
                                    data-csrf="{{ csrf_token() }}"
                                    title="Probar conexión API con el router">
                                    <svg class="w-3.5 h-3.5 test-icon text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <svg class="w-3.5 h-3.5 test-spinner hidden animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>Test</span>
                                </button>

                                <!-- Action 2: DHCP / PPPoE Modal -->
                                <button type="button"
                                    class="router-dhcp-pppoe-btn flex-1 inline-flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-semibold text-violet-700 dark:text-violet-300 bg-violet-50 hover:bg-violet-100 dark:bg-violet-900/30 dark:hover:bg-violet-900/50 border border-violet-200 dark:border-violet-700 transition-all active:scale-95"
                                    data-url="{{ route('sistema.routers.consultar-dhcp-pppoe', $r) }}"
                                    data-nombre="{{ $r->nombre }}"
                                    title="Ver leases DHCP y sesiones PPPoE en vivo">
                                    <svg class="w-3.5 h-3.5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    <span>Sesiones</span>
                                </button>

                                <!-- Action 3: Sync PPPoE -->
                                <button type="button"
                                    class="router-sync-btn p-2 rounded-xl text-emerald-700 dark:text-emerald-300 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-700 transition-all active:scale-95 shrink-0"
                                    data-url="{{ route('sistema.routers.sync-pppoe', $r) }}"
                                    data-csrf="{{ csrf_token() }}"
                                    title="Sincronizar usuarios PPPoE de la BD a este router">
                                    <svg class="w-3.5 h-3.5 sync-icon text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>

                                <!-- Action 4: Script PPPoE -->
                                <button type="button"
                                    class="router-export-script-btn p-2 rounded-xl text-amber-700 dark:text-amber-300 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-900/50 border border-amber-200 dark:border-amber-700 transition-all active:scale-95 shrink-0"
                                    data-url="{{ route('sistema.routers.export-pppoe-script', ['router' => $r, 'formato' => 'json']) }}"
                                    data-nombre="{{ $r->nombre }}"
                                    title="Exportar script RouterOS para consola">
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                </button>

                                <!-- Action 5: More Options Menu (Floating Dropdown) -->
                                <div class="relative router-more-menu shrink-0">
                                    <button type="button"
                                        class="router-more-btn p-2 rounded-xl text-gray-700 dark:text-gray-300 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600 transition-all active:scale-95"
                                        title="Más opciones de gestión">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                    </button>
                                    <div class="router-more-panel hidden absolute right-0 bottom-full mb-1.5 w-48 z-40 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl py-1.5">
                                        <a href="{{ route('sistema.router-scripts.index', ['router_origen_id' => $r->router_id]) }}"
                                            class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Scripts del router
                                        </a>
                                        <a href="{{ route('sistema.router-schedulers.index', ['router_origen_id' => $r->router_id]) }}"
                                            class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <svg class="w-3.5 h-3.5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Schedulers
                                        </a>
                                        <a href="{{ route('sistema.router-network-backups.index', ['router_origen_id' => $r->router_id]) }}"
                                            class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                                            Backup de red
                                        </a>
                                        <a href="{{ route('sistema.router-ip-pools.index') }}"
                                            class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                            Pools de IP
                                        </a>
                                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                        <a href="{{ route('sistema.routers.edit', $r) }}"
                                            class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Editar router
                                        </a>
                                        <form action="{{ route('sistema.routers.destroy', $r) }}" method="POST"
                                            onsubmit="return confirm('¿Eliminar este router?{{ $r->router_ip_pools_count > 0 ? ' También se eliminarán sus pools de IP ('.$r->router_ip_pools_count.').' : '' }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Micro Status Line -->
                            <p class="router-action-status mt-2 min-h-[1rem] text-[11px] text-gray-500 dark:text-gray-400 truncate" aria-live="polite"></p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($routers->hasPages())
            <div class="mt-6">
                {{ $routers->links() }}
            </div>
        @endif
    @endif
</div>

<!-- Modal DHCP & PPPoE Avanzado con Tabs (Package C) -->
<div id="router-dhcp-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-3 sm:p-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" id="router-dhcp-modal-backdrop"></div>
        <div class="relative w-full max-w-5xl rounded-2xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 max-h-[92vh] flex flex-col overflow-hidden">

            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="p-2 rounded-xl bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">Sesiones en Vivo & Leases</h2>
                        <p id="router-dhcp-modal-subtitle" class="text-xs text-gray-500 dark:text-gray-400 truncate"></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Live Refresh Button -->
                    <button type="button" id="router-dhcp-refresh-btn"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        title="Actualizar datos del router en vivo">
                        <svg class="w-3.5 h-3.5 refresh-icon text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Refrescar</span>
                    </button>
                    <!-- Close Button -->
                    <button type="button" id="router-dhcp-modal-close" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Chips Summary Strip -->
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex-shrink-0">
                <div class="flex flex-wrap items-center gap-2" id="router-dhcp-modal-stats"></div>
            </div>

            <!-- Tabs Navigation & Search Bar -->
            <div class="px-5 pt-3 pb-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex-shrink-0 space-y-3">
                <!-- Tabs -->
                <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                    <button type="button" id="tab-btn-pppoe"
                        class="tab-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all bg-violet-600 text-white shadow-sm"
                        data-tab="pppoe">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>PPPoE Conectados</span>
                        <span id="tab-count-pppoe" class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20">0</span>
                    </button>
                    <button type="button" id="tab-btn-dhcp"
                        class="tab-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                        data-tab="dhcp">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>DHCP Leases</span>
                        <span id="tab-count-dhcp" class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 dark:bg-gray-700">0</span>
                    </button>
                </div>

                <!-- Search Input & Filters -->
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center justify-between">
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input type="search" id="router-dhcp-filter" placeholder="Buscar por usuario, IP, MAC, hostname, caller ID..."
                            class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500/30">
                    </div>
                    <label id="dhcp-only-bound-wrapper" class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap hidden">
                        <input type="checkbox" id="router-dhcp-solo-activos" class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500" checked>
                        Solo DHCP bound
                    </label>
                </div>
            </div>

            <!-- Tab Panels Scroll Area -->
            <div class="overflow-auto flex-1 min-h-0 p-5">
                <!-- Tab Panel 1: PPPoE Sessions -->
                <div id="tab-panel-pppoe" class="space-y-4">
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-900/90 text-left uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                                <tr>
                                    <th class="px-4 py-2.5">Usuario</th>
                                    <th class="px-4 py-2.5">Dirección IP</th>
                                    <th class="px-4 py-2.5">Uptime</th>
                                    <th class="px-4 py-2.5">Caller ID (MAC)</th>
                                    <th class="px-4 py-2.5">Servicio</th>
                                </tr>
                            </thead>
                            <tbody id="router-pppoe-modal-tbody" class="divide-y divide-gray-100 dark:divide-gray-700 font-medium"></tbody>
                        </table>
                        <div id="router-pppoe-modal-empty" class="hidden p-8 text-center">
                            <svg class="mx-auto h-8 w-8 text-gray-400 opacity-60 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No hay sesiones PPPoE activas</p>
                            <p class="text-xs text-gray-400 mt-0.5">El router no tiene clientes PPPoE conectados en este momento.</p>
                        </div>
                    </div>
                </div>

                <!-- Tab Panel 2: DHCP Leases -->
                <div id="tab-panel-dhcp" class="space-y-4 hidden">
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-xs">
                            <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900/90 text-left uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                                <tr>
                                    <th class="px-4 py-2.5">Dirección IP</th>
                                    <th class="px-4 py-2.5">MAC Address</th>
                                    <th class="px-4 py-2.5">Hostname</th>
                                    <th class="px-4 py-2.5">Estado</th>
                                    <th class="px-4 py-2.5">Server</th>
                                    <th class="px-4 py-2.5">Expira</th>
                                </tr>
                            </thead>
                            <tbody id="router-dhcp-modal-tbody" class="divide-y divide-gray-100 dark:divide-gray-700 font-medium"></tbody>
                        </table>
                        <div id="router-dhcp-modal-empty" class="hidden p-8 text-center">
                            <svg class="mx-auto h-8 w-8 text-gray-400 opacity-60 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No hay leases DHCP para mostrar</p>
                            <p class="text-xs text-gray-400 mt-0.5">Probá quitando el filtro de búsqueda o cambiando los criterios.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
                <span id="router-dhcp-modal-footer-count" class="text-xs text-gray-500 dark:text-gray-400 font-medium"></span>
                <button type="button" id="router-dhcp-modal-close-btn" class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-xs font-semibold text-gray-800 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Script PPPoE para Consola -->
<div id="router-script-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" id="router-script-modal-backdrop"></div>
        <div class="relative w-full max-w-3xl rounded-2xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Script PPPoE para Consola MikroTik</h2>
                        <p id="router-script-modal-subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"></p>
                    </div>
                </div>
                <button type="button" id="router-script-modal-close" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        Pegá este comando en la terminal de Winbox, SSH o WebFig del MikroTik:
                    </p>
                    <span class="text-[11px] font-mono text-gray-400">RouterOS Script (.rsc)</span>
                </div>
                <textarea id="router-script-textarea" readonly rows="14"
                    class="w-full font-mono text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-900 text-emerald-400 p-4 focus:outline-none focus:ring-2 focus:ring-purple-500/30 selection:bg-purple-600 selection:text-white"></textarea>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <a id="router-script-download" href="#" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descargar .rsc
                </a>
                <button type="button" id="router-script-copy" class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 text-white rounded-xl text-xs font-semibold hover:bg-purple-700 shadow-sm transition-all active:scale-95">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span>Copiar al portapapeles</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Toast Container -->
<div id="router-toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<script>
(function() {
    'use strict';
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Toast helper
    function showToast(message, type) {
        var container = document.getElementById('router-toast-container');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'pointer-events-auto flex items-center gap-2 px-4 py-2.5 rounded-xl shadow-xl text-xs font-semibold transition-all transform duration-300 opacity-0 translate-y-2 '
            + (type === 'success' ? 'bg-emerald-600 text-white' : (type === 'error' ? 'bg-rose-600 text-white' : 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'));
        toast.textContent = message;
        container.appendChild(toast);
        requestAnimationFrame(function() {
            toast.classList.remove('opacity-0', 'translate-y-2');
        });
        setTimeout(function() {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }

    // Set micro status on router card
    function setCardStatus(card, text, ok) {
        if (!card) return;
        var el = card.querySelector('.router-action-status');
        if (!el) return;
        el.textContent = text || '';
        el.className = 'router-action-status mt-2 min-h-[1rem] text-[11px] truncate ' +
            (ok === true ? 'text-emerald-600 dark:text-emerald-400 font-medium' :
            (ok === false ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-gray-500 dark:text-gray-400'));
    }

    // Update status pill on router card
    function updateStatusPill(card, isOk, latencyMs) {
        if (!card) return;
        var pill = card.querySelector('.router-status-pill');
        var dot = card.querySelector('.status-dot');
        var text = card.querySelector('.status-text');
        if (!pill || !dot || !text) return;

        if (isOk) {
            pill.className = 'router-status-pill inline-flex items-center gap-1.5 shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/30';
            dot.className = 'status-dot h-1.5 w-1.5 rounded-full bg-emerald-500';
            text.textContent = (latencyMs ? (latencyMs + 'ms · ') : '') + 'Conectado';
            pill.setAttribute('data-status', 'conectado');
        } else {
            pill.className = 'router-status-pill inline-flex items-center gap-1.5 shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-1 ring-rose-500/30';
            dot.className = 'status-dot h-1.5 w-1.5 rounded-full bg-rose-500';
            text.textContent = 'Desconectado';
            pill.setAttribute('data-status', 'desconectado');
        }
    }

    // 1-Click Copy IP button
    document.querySelectorAll('.copy-ip-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var ip = this.getAttribute('data-ip');
            if (!ip) return;
            var copyIcon = this.querySelector('.copy-icon');
            var checkIcon = this.querySelector('.check-icon');

            navigator.clipboard.writeText(ip).then(function() {
                if (copyIcon && checkIcon) {
                    copyIcon.classList.add('hidden');
                    checkIcon.classList.remove('hidden');
                    setTimeout(function() {
                        copyIcon.classList.remove('hidden');
                        checkIcon.classList.add('hidden');
                    }, 1500);
                }
                showToast('IP ' + ip + ' copiada al portapapeles', 'success');
            }).catch(function() {
                showToast('No se pudo copiar la IP', 'error');
            });
        });
    });

    // Tools Dropdown
    document.querySelectorAll('[data-tools-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var root = btn.closest('[data-tools-menu]');
            var panel = root.querySelector('[data-tools-panel]');
            document.querySelectorAll('[data-tools-panel]').forEach(function(p) {
                if (p !== panel) p.classList.add('hidden');
            });
            panel.classList.toggle('hidden');
        });
    });

    // More Options Floating Dropdowns in Cards
    document.querySelectorAll('.router-more-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var root = btn.closest('.router-more-menu');
            var panel = root.querySelector('.router-more-panel');
            document.querySelectorAll('.router-more-panel').forEach(function(p) {
                if (p !== panel) p.classList.add('hidden');
            });
            panel.classList.toggle('hidden');
        });
    });

    // Close all open dropdowns on document click
    document.addEventListener('click', function() {
        document.querySelectorAll('[data-tools-panel]').forEach(function(p) { p.classList.add('hidden'); });
        document.querySelectorAll('.router-more-panel').forEach(function(p) { p.classList.add('hidden'); });
    });

    // Live search filter on visible router cards
    var searchInput = document.getElementById('router-search-input');
    var clearBtn = document.getElementById('router-search-clear');
    var routerCards = Array.from(document.querySelectorAll('.router-card'));

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var val = (this.value || '').trim().toLowerCase();
            if (clearBtn) clearBtn.classList.toggle('hidden', val.length === 0);

            routerCards.forEach(function(card) {
                var text = card.getAttribute('data-search-text') || '';
                var matches = !val || text.indexOf(val) !== -1;
                card.classList.toggle('hidden', !matches);
            });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            }
        });
    }

    // Auto-submit dropdown filters on change
    var filterSerie = document.getElementById('router-filter-serie');
    var filterNodo = document.getElementById('router-filter-nodo');
    var filterForm = document.getElementById('router-filter-form');

    if (filterSerie && filterForm) {
        filterSerie.addEventListener('change', function() { filterForm.submit(); });
    }
    if (filterNodo && filterForm) {
        filterNodo.addEventListener('change', function() { filterForm.submit(); });
    }

    // Single Router Test Connection
    document.querySelectorAll('.router-test-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var card = this.closest('.router-card');
            var url = this.getAttribute('data-url');
            var token = this.getAttribute('data-csrf') || csrf;
            var testIcon = this.querySelector('.test-icon');
            var testSpinner = this.querySelector('.test-spinner');

            this.disabled = true;
            if (testIcon) testIcon.classList.add('hidden');
            if (testSpinner) testSpinner.classList.remove('hidden');
            setCardStatus(card, 'Probando conexión API...', null);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: '{}'
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                var d = res.data;
                var lat = d.latency_ms ? d.latency_ms + 'ms' : '';
                var isOk = !!res.ok && !!d.success;
                if (isOk) {
                    setCardStatus(card, 'Conexión OK (' + lat + ')', true);
                    updateStatusPill(card, true, d.latency_ms);
                    showToast('Router ' + (card.querySelector('h2')?.textContent.trim() || '') + ' en línea (' + lat + ')', 'success');
                } else {
                    setCardStatus(card, d.message || 'Error de conexión', false);
                    updateStatusPill(card, false, null);
                }
            })
            .catch(function() {
                setCardStatus(card, 'Error de red al conectar', false);
                updateStatusPill(card, false, null);
            })
            .finally(function() {
                btn.disabled = false;
                if (testIcon) testIcon.classList.remove('hidden');
                if (testSpinner) testSpinner.classList.add('hidden');
            });
        });
    });

    // Batch Health Check ("Verificar Red", Package B)
    var btnVerifyAll = document.getElementById('btn-verify-all');
    var verifyBanner = document.getElementById('verify-all-banner');
    var verifyStatusText = document.getElementById('verify-all-status-text');
    var verifyProgressBar = document.getElementById('verify-all-progress-bar');
    var verifyBadge = document.getElementById('verify-all-badge');
    var btnVerifyStop = document.getElementById('verify-all-stop');
    var batchCanceled = false;

    if (btnVerifyAll) {
        btnVerifyAll.addEventListener('click', function() {
            var cards = Array.from(document.querySelectorAll('.router-card')).filter(function(c) {
                return !c.classList.contains('hidden');
            });

            if (!cards.length) {
                showToast('No hay routers visibles para verificar', 'info');
                return;
            }

            btnVerifyAll.disabled = true;
            batchCanceled = false;
            verifyBanner?.classList.remove('hidden');
            verifyBadge?.classList.remove('hidden');

            var total = cards.length;
            var current = 0;
            var onlineCount = 0;
            var offlineCount = 0;

            function runNext(index) {
                if (batchCanceled || index >= total) {
                    btnVerifyAll.disabled = false;
                    setTimeout(function() {
                        verifyBanner?.classList.add('hidden');
                        verifyBadge?.classList.add('hidden');
                    }, 4000);
                    if (!batchCanceled) {
                        showToast('Verificación completada: ' + onlineCount + ' en línea, ' + offlineCount + ' con error', onlineCount === total ? 'success' : 'info');
                        // Update deck metrics
                        var deckOnline = document.getElementById('deck-online-routers');
                        var deckOffline = document.getElementById('deck-offline-routers');
                        if (deckOnline) deckOnline.textContent = onlineCount;
                        if (deckOffline) deckOffline.textContent = offlineCount;
                    }
                    return;
                }

                var card = cards[index];
                var testBtn = card.querySelector('.router-test-btn');
                var url = testBtn?.getAttribute('data-url');
                var token = testBtn?.getAttribute('data-csrf') || csrf;
                var routerName = card.querySelector('h2')?.textContent.trim() || ('Router #' + (index + 1));

                current = index + 1;
                var pct = Math.round((current / total) * 100);
                if (verifyProgressBar) verifyProgressBar.style.width = pct + '%';
                if (verifyStatusText) verifyStatusText.textContent = 'Comprobando ' + current + '/' + total + ': ' + routerName + '...';
                if (verifyBadge) verifyBadge.textContent = current + '/' + total;

                setCardStatus(card, 'Verificando...', null);

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: '{}'
                })
                .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                .then(function(res) {
                    var d = res.data;
                    var isOk = !!res.ok && !!d.success;
                    if (isOk) {
                        onlineCount++;
                        setCardStatus(card, 'Conexión OK (' + (d.latency_ms || '') + 'ms)', true);
                        updateStatusPill(card, true, d.latency_ms);
                    } else {
                        offlineCount++;
                        setCardStatus(card, d.message || 'Sin conexión', false);
                        updateStatusPill(card, false, null);
                    }
                })
                .catch(function() {
                    offlineCount++;
                    setCardStatus(card, 'Error de red', false);
                    updateStatusPill(card, false, null);
                })
                .finally(function() {
                    runNext(index + 1);
                });
            }

            runNext(0);
        });
    }

    if (btnVerifyStop) {
        btnVerifyStop.addEventListener('click', function() {
            batchCanceled = true;
            if (verifyStatusText) verifyStatusText.textContent = 'Comprobación detenida por el usuario.';
            if (btnVerifyAll) btnVerifyAll.disabled = false;
            setTimeout(function() { verifyBanner?.classList.add('hidden'); }, 1500);
        });
    }

    // Sync PPPoE Action
    document.querySelectorAll('.router-sync-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var card = this.closest('.router-card');
            var routerName = card?.querySelector('h2')?.textContent.trim() || 'este router';
            if (!confirm('¿Sincronizar usuarios PPPoE de la BD a ' + routerName + '?')) return;

            var url = this.getAttribute('data-url');
            var token = this.getAttribute('data-csrf') || csrf;
            var syncIcon = this.querySelector('.sync-icon');

            this.disabled = true;
            syncIcon?.classList.add('animate-spin');
            setCardStatus(card, 'Sincronizando usuarios PPPoE...', null);

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ remove_orphans: false })
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(res) {
                var d = res.data;
                var msg = '+' + (d.added || 0) + ' · ~' + (d.updated || 0) + ' · -' + (d.removed || 0);
                if (d.errors && d.errors.length) msg += ' · ' + d.errors.length + ' err';
                setCardStatus(card, 'Sync: ' + msg, res.ok);
                showToast('Sync finalizado en ' + routerName + ': ' + msg, res.ok ? 'success' : 'error');
                if (d.errors && d.errors.length) {
                    alert((d.message ? d.message + '\n\n' : '') + d.errors.join('\n'));
                }
            })
            .catch(function() {
                setCardStatus(card, 'Error al sincronizar', false);
                showToast('Error de red al sincronizar PPPoE', 'error');
            })
            .finally(function() {
                btn.disabled = false;
                syncIcon?.classList.remove('animate-spin');
            });
        });
    });

    // DHCP & PPPoE Modal (Package C)
    var dhcpModal = document.getElementById('router-dhcp-modal');
    var dhcpSubtitle = document.getElementById('router-dhcp-modal-subtitle');
    var dhcpStats = document.getElementById('router-dhcp-modal-stats');
    var dhcpTbody = document.getElementById('router-dhcp-modal-tbody');
    var dhcpEmpty = document.getElementById('router-dhcp-modal-empty');
    var pppoeTbody = document.getElementById('router-pppoe-modal-tbody');
    var pppoeEmpty = document.getElementById('router-pppoe-modal-empty');
    var dhcpFilter = document.getElementById('router-dhcp-filter');
    var dhcpSoloActivos = document.getElementById('router-dhcp-solo-activos');
    var dhcpOnlyBoundWrapper = document.getElementById('dhcp-only-bound-wrapper');
    var tabBtnPppoe = document.getElementById('tab-btn-pppoe');
    var tabBtnDhcp = document.getElementById('tab-btn-dhcp');
    var tabPanelPppoe = document.getElementById('tab-panel-pppoe');
    var tabPanelDhcp = document.getElementById('tab-panel-dhcp');
    var tabCountPppoe = document.getElementById('tab-count-pppoe');
    var tabCountDhcp = document.getElementById('tab-count-dhcp');
    var modalFooterCount = document.getElementById('router-dhcp-modal-footer-count');
    var refreshBtn = document.getElementById('router-dhcp-refresh-btn');

    var currentRouterUrl = '';
    var currentRouterName = '';
    var activeTab = 'pppoe';
    var dhcpLeasesCache = [];
    var pppoeSesionesCache = [];

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openDhcpModal() { dhcpModal?.classList.remove('hidden'); }
    function closeDhcpModal() { dhcpModal?.classList.add('hidden'); }

    document.getElementById('router-dhcp-modal-close')?.addEventListener('click', closeDhcpModal);
    document.getElementById('router-dhcp-modal-close-btn')?.addEventListener('click', closeDhcpModal);
    document.getElementById('router-dhcp-modal-backdrop')?.addEventListener('click', closeDhcpModal);

    // Switch Tabs
    function switchTab(target) {
        activeTab = target;
        if (target === 'pppoe') {
            tabBtnPppoe.className = 'tab-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all bg-violet-600 text-white shadow-sm';
            tabBtnDhcp.className = 'tab-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700';
            tabPanelPppoe?.classList.remove('hidden');
            tabPanelDhcp?.classList.add('hidden');
            dhcpOnlyBoundWrapper?.classList.add('hidden');
        } else {
            tabBtnDhcp.className = 'tab-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all bg-violet-600 text-white shadow-sm';
            tabBtnPppoe.className = 'tab-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700';
            tabPanelDhcp?.classList.remove('hidden');
            tabPanelPppoe?.classList.add('hidden');
            dhcpOnlyBoundWrapper?.classList.remove('hidden');
        }
        renderTables();
    }

    tabBtnPppoe?.addEventListener('click', function() { switchTab('pppoe'); });
    tabBtnDhcp?.addEventListener('click', function() { switchTab('dhcp'); });

    function renderDhcpChips(d) {
        if (!dhcpStats) return;
        var staticCount = dhcpLeasesCache.filter(function(l) { return !!l.static; }).length;
        var pppoeCount = pppoeSesionesCache.length;
        var dhcpActivosCount = dhcpLeasesCache.filter(function(l) { return !!l.active; }).length;

        var chips = [
            { label: 'PPPoE Activos', value: pppoeCount, tone: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300' },
            { label: 'DHCP Bound (Activos)', value: dhcpActivosCount, tone: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' },
            { label: 'DHCP Total', value: dhcpLeasesCache.length, tone: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' },
            { label: 'IP Estáticas', value: staticCount, tone: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }
        ];

        dhcpStats.innerHTML = chips.map(function(c) {
            return '<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ' + c.tone + '">'
                + escHtml(c.label) + ': <span class="tabular-nums font-bold">' + escHtml(c.value) + '</span></span>';
        }).join('');

        if (tabCountPppoe) tabCountPppoe.textContent = pppoeCount;
        if (tabCountDhcp) tabCountDhcp.textContent = dhcpLeasesCache.length;
    }

    function renderTables() {
        var q = (dhcpFilter?.value || '').trim().toLowerCase();

        // Render PPPoE Table
        if (pppoeTbody) {
            var pppoeRows = pppoeSesionesCache.filter(function(s) {
                if (!q) return true;
                var hay = [s.name, s.address, s.uptime, s.caller_id, s.service].join(' ').toLowerCase();
                return hay.indexOf(q) !== -1;
            });

            if (!pppoeRows.length) {
                pppoeTbody.innerHTML = '';
                pppoeEmpty?.classList.remove('hidden');
            } else {
                pppoeEmpty?.classList.add('hidden');
                pppoeTbody.innerHTML = pppoeRows.map(function(s) {
                    var ip = s.address ? s.address.trim() : '';
                    var ipCell = ip ? '<a href="http://' + escHtml(ip) + '" target="_blank" rel="noopener noreferrer" class="font-mono text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-1">' + escHtml(ip) + ' <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>' : '-';
                    return '<tr class="hover:bg-violet-50/40 dark:hover:bg-violet-900/10 transition-colors">'
                        + '<td class="px-4 py-2.5 font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>' + escHtml(s.name || '-') + '</td>'
                        + '<td class="px-4 py-2.5 whitespace-nowrap">' + ipCell + '</td>'
                        + '<td class="px-4 py-2.5 font-mono text-gray-600 dark:text-gray-400">' + escHtml(s.uptime || '-') + '</td>'
                        + '<td class="px-4 py-2.5 font-mono text-gray-700 dark:text-gray-300">' + escHtml(s.caller_id || '-') + '</td>'
                        + '<td class="px-4 py-2.5 text-gray-500 dark:text-gray-400"><span class="inline-flex rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[10px] font-semibold uppercase">' + escHtml(s.service || 'pppoe') + '</span></td>'
                        + '</tr>';
                }).join('');
            }
        }

        // Render DHCP Table
        if (dhcpTbody) {
            var soloActivos = !!(dhcpSoloActivos && dhcpSoloActivos.checked);
            var dhcpRows = dhcpLeasesCache.filter(function(l) {
                if (soloActivos && !l.active) return false;
                if (!q) return true;
                var hay = [l.address, l.mac, l.hostname, l.status, l.server, l.static ? 'static estatica' : 'dynamic'].join(' ').toLowerCase();
                return hay.indexOf(q) !== -1;
            });

            if (!dhcpRows.length) {
                dhcpTbody.innerHTML = '';
                dhcpEmpty?.classList.remove('hidden');
            } else {
                dhcpEmpty?.classList.add('hidden');
                dhcpTbody.innerHTML = dhcpRows.map(function(l) {
                    var ip = l.address ? l.address.trim() : '';
                    var ipCell = ip ? '<a href="http://' + escHtml(ip) + '" target="_blank" rel="noopener noreferrer" class="font-mono text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-1">' + escHtml(ip) + ' <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>' : '-';
                    if (l.static) {
                        ipCell += ' <span class="inline-flex rounded-full bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">static</span>';
                    }
                    var statusBadge = l.active
                        ? '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>' + escHtml(l.status || 'bound') + '</span>'
                        : '<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300"><span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>' + escHtml(l.status || 'waiting') + '</span>';

                    return '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">'
                        + '<td class="px-4 py-2.5 whitespace-nowrap">' + ipCell + '</td>'
                        + '<td class="px-4 py-2.5 font-mono text-gray-700 dark:text-gray-300 whitespace-nowrap">' + escHtml(l.mac || '-') + '</td>'
                        + '<td class="px-4 py-2.5 text-gray-900 dark:text-gray-100 font-semibold">' + escHtml(l.hostname || '-') + '</td>'
                        + '<td class="px-4 py-2.5">' + statusBadge + '</td>'
                        + '<td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">' + escHtml(l.server || '-') + '</td>'
                        + '<td class="px-4 py-2.5 font-mono text-gray-500 dark:text-gray-400 whitespace-nowrap">' + escHtml(l.expires || '-') + '</td>'
                        + '</tr>';
                }).join('');
            }
        }

        if (modalFooterCount) {
            if (activeTab === 'pppoe') {
                modalFooterCount.textContent = pppoeSesionesCache.length + ' sesiones PPPoE activas en MikroTik';
            } else {
                modalFooterCount.textContent = dhcpLeasesCache.length + ' leases DHCP en MikroTik';
            }
        }
    }

    function fetchRouterData(url, nombre) {
        if (!url) return;
        var refreshIcon = refreshBtn?.querySelector('.refresh-icon');
        if (refreshBtn) refreshBtn.disabled = true;
        if (refreshIcon) refreshIcon.classList.add('animate-spin');

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            var d = res.data || {};
            if (!res.ok) {
                showToast(d.message || 'Error al consultar datos del router', 'error');
                return;
            }
            dhcpLeasesCache = Array.isArray(d.dhcp_leases) ? d.dhcp_leases : [];
            pppoeSesionesCache = Array.isArray(d.pppoe_sesiones) ? d.pppoe_sesiones : [];

            if (dhcpSubtitle) {
                dhcpSubtitle.textContent = (d.router?.nombre || nombre) + ' · ' + (d.message || 'Consulta exitosa');
            }
            renderDhcpChips(d);
            renderTables();
            showToast('Datos actualizados en vivo (' + pppoeSesionesCache.length + ' PPPoE / ' + dhcpLeasesCache.length + ' DHCP)', 'success');
        })
        .catch(function() {
            showToast('Error de red al consultar el router', 'error');
        })
        .finally(function() {
            if (refreshBtn) refreshBtn.disabled = false;
            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
        });
    }

    // Open DHCP/PPPoE Modal on Button Click
    document.querySelectorAll('.router-dhcp-pppoe-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var url = this.getAttribute('data-url');
            var nombre = this.getAttribute('data-nombre') || 'Router';
            currentRouterUrl = url;
            currentRouterName = nombre;

            if (dhcpFilter) dhcpFilter.value = '';
            if (dhcpSoloActivos) dhcpSoloActivos.checked = true;

            openDhcpModal();
            switchTab('pppoe');
            fetchRouterData(url, nombre);
        });
    });

    // Modal Live Refresh Button
    refreshBtn?.addEventListener('click', function() {
        if (currentRouterUrl) fetchRouterData(currentRouterUrl, currentRouterName);
    });

    dhcpFilter?.addEventListener('input', renderTables);
    dhcpSoloActivos?.addEventListener('change', renderTables);

    // Script PPPoE Modal
    var scriptModal = document.getElementById('router-script-modal');
    var scriptTextarea = document.getElementById('router-script-textarea');
    var scriptSubtitle = document.getElementById('router-script-modal-subtitle');
    var scriptDownload = document.getElementById('router-script-download');
    var scriptCopyBtn = document.getElementById('router-script-copy');

    function openScriptModal() { scriptModal?.classList.remove('hidden'); }
    function closeScriptModal() { scriptModal?.classList.add('hidden'); }

    document.getElementById('router-script-modal-close')?.addEventListener('click', closeScriptModal);
    document.getElementById('router-script-modal-backdrop')?.addEventListener('click', closeScriptModal);

    document.querySelectorAll('.router-export-script-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var url = this.getAttribute('data-url');
            var nombre = this.getAttribute('data-nombre') || 'Router';
            btn.disabled = true;

            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                scriptTextarea.value = d.script || '';
                scriptSubtitle.textContent = (d.router?.nombre || nombre) + ' · ' + (d.usuarios || 0) + ' usuario(s) configurados';
                if (d.download_url) scriptDownload.href = d.download_url;
                openScriptModal();
                scriptTextarea.focus();
                scriptTextarea.select();
            })
            .catch(function() {
                showToast('No se pudo cargar el script PPPoE', 'error');
            })
            .finally(function() { btn.disabled = false; });
        });
    });

    scriptCopyBtn?.addEventListener('click', function() {
        var text = scriptTextarea.value;
        if (!text) return;
        navigator.clipboard.writeText(text).then(function() {
            showToast('Script RouterOS copiado al portapapeles', 'success');
        }).catch(function() {
            scriptTextarea.select();
            document.execCommand('copy');
            showToast('Script copiado al portapapeles', 'success');
        });
    });

})();
</script>
@endsection
