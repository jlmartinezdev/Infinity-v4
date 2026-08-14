@extends('layouts.app')

@section('title', 'Routers')

@section('content')
@php
    $totalRouters = $routers->total();
    $totalRegistrados = collect($statsClientes)->sum('registrados');
    $totalActivos = collect($statsClientes)->sum('activos');
@endphp
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Routers</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ number_format($totalRouters) }} equipo{{ $totalRouters === 1 ? '' : 's' }}
                @if($totalRegistrados > 0)
                    · {{ number_format($totalActivos) }}/{{ number_format($totalRegistrados) }} clientes activos en esta página
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative" data-tools-menu>
                <button type="button" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700" data-tools-toggle>
                    Herramientas
                    <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="hidden absolute right-0 mt-1 w-52 z-30 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1" data-tools-panel>
                    <a href="{{ route('sistema.router-scripts.index') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Scripts MikroTik</a>
                    <a href="{{ route('sistema.router-schedulers.index') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Schedulers</a>
                    <a href="{{ route('sistema.router-network-backups.index') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Backup de red</a>
                    <a href="{{ route('sistema.router-modelos.index') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Catálogo modelos</a>
                </div>
            </div>
            <a href="{{ route('sistema.routers.create') }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Nuevo router
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('sistema.routers.index') }}"
        class="mb-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 sm:p-4">
        <div class="flex flex-col lg:flex-row gap-3">
            <div class="flex-1">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    placeholder="Buscar por nombre, IP, modelo..."
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="sm:w-40">
                <select name="serie" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="todas">Todas las series</option>
                    @foreach($series as $serie)
                        <option value="{{ $serie }}" {{ request('serie') == $serie ? 'selected' : '' }}>{{ $serie }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-48">
                <select name="nodo_id" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="todos">Todos los nodos</option>
                    @foreach($nodos as $nodo)
                        <option value="{{ $nodo->nodo_id }}" {{ request('nodo_id') == $nodo->nodo_id ? 'selected' : '' }}>
                            {{ $nodo->descripcion ?? "Nodo #{$nodo->nodo_id}" }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">
                Filtrar
            </button>
        </div>
    </form>

    @if($routers->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-12 text-center">
            <img src="{{ asset('images/routers/mikrotik-generic.svg') }}" alt="" class="mx-auto h-24 w-48 object-contain opacity-60 mb-4">
            <p class="text-gray-500 dark:text-gray-400">No hay routers con esos filtros.</p>
            <a href="{{ route('sistema.routers.create') }}" class="mt-3 inline-block text-purple-600 dark:text-purple-400 hover:underline font-medium">Crear el primero</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
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
                @endphp
                <article class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:border-purple-400/60 dark:hover:border-purple-500/50 hover:shadow-md transition-all duration-200">
                    <div class="relative px-5 pt-5 pb-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $serie }}</span>
                                    <span class="inline-flex items-center gap-1.5 shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium
                                        {{ $estadoOk
                                            ? 'bg-emerald-900/40 text-emerald-400 ring-1 ring-emerald-700/50'
                                            : ($estadoBad
                                                ? 'bg-red-900/40 text-red-400 ring-1 ring-red-700/50'
                                                : 'bg-gray-700/50 text-gray-400 ring-1 ring-gray-600/50') }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $estadoOk ? 'bg-emerald-400' : ($estadoBad ? 'bg-red-400' : 'bg-gray-400') }}"></span>
                                        {{ $estadoLabel }}
                                    </span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate leading-tight" title="{{ $r->nombre }}">{{ $r->nombre }}</h2>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $r->modeloEtiqueta() }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $r->nodo?->descripcion ?? 'Sin nodo' }} · #{{ $r->router_id }}</p>
                                    </div>
                                    <img src="{{ $r->imagenUrl() }}" alt="{{ $r->modeloEtiqueta() }}"
                                        class="h-14 w-20 object-contain opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-200 shrink-0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 pb-4 flex-1 flex flex-col">
                        <div class="mt-3">
                            <p class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $r->ip }}</p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3a3 3 0 110 6 3 3 0 010-6z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 14c1.5-1 3-1.5 4.5-1.5S11.5 13 13 14s3 1.5 4.5 1.5S20.5 15 22 14"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 18c1.5-1 3-1.5 4.5-1.5S11.5 17 13 18s3 1.5 4.5 1.5S20.5 19 22 18"/>
                                    </svg>
                                    Pools Activos
                                </span>
                                <span class="inline-flex min-w-[1.75rem] items-center justify-center rounded-md bg-gray-200/80 dark:bg-slate-700 px-2 py-0.5 text-sm font-medium tabular-nums text-gray-700 dark:text-gray-200">
                                    {{ $r->router_ip_pools_count ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3 py-2.5">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Registrados</p>
                                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100 leading-none">{{ number_format($stats['registrados']) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3 py-2.5">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Activos</p>
                                <p class="mt-1 leading-none">
                                    <span class="text-2xl font-bold tabular-nums text-emerald-500">{{ number_format($stats['activos']) }}</span>
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">/ {{ number_format($stats['registrados']) }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                                <span>Sistema Salud</span>
                                <span class="tabular-nums font-medium">{{ $pctActivos }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full rounded-full bg-violet-300 dark:bg-violet-400/80 transition-all duration-300" style="width: {{ $pctActivos }}%"></div>
                            </div>
                        </div>

                        <div class="mt-auto pt-4" data-router-accordion>
                            <button type="button"
                                class="w-full flex items-center justify-between gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40 px-3 py-2.5 text-left hover:border-purple-300 dark:hover:border-purple-600 transition-colors"
                                data-accordion-toggle
                                aria-expanded="false">
                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                    <svg class="w-4 h-4 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M11.3 1.046a1 1 0 01.7 1.32l-1.4 3.884h3.65a1 1 0 01.78 1.625l-6.5 8.5a1 1 0 01-1.76-.9l1.4-3.884H4.62a1 1 0 01-.78-1.625l6.5-8.5a1 1 0 01.96-.42z"/>
                                    </svg>
                                    Acciones rápidas
                                </span>
                                <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" data-accordion-chevron fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div class="hidden mt-3" data-accordion-panel>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button"
                                        class="router-test-btn flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-sky-50 dark:hover:bg-sky-900/30 hover:border-sky-300 dark:hover:border-sky-700 transition-colors"
                                        data-url="{{ route('sistema.routers.test-connection', $r) }}"
                                        data-csrf="{{ csrf_token() }}">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">Probar conexión</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Test API del router</span>
                                        </span>
                                    </button>

                                    <button type="button"
                                        class="router-dhcp-pppoe-btn flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-violet-50 dark:hover:bg-violet-900/30 hover:border-violet-300 dark:hover:border-violet-700 transition-colors"
                                        data-url="{{ route('sistema.routers.consultar-dhcp-pppoe', $r) }}"
                                        data-nombre="{{ $r->nombre }}">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/50 text-violet-600 dark:text-violet-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">DHCP / PPPoE</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Leases y sesiones activas</span>
                                        </span>
                                    </button>

                                    <button type="button"
                                        class="router-sync-btn flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-emerald-50 dark:hover:bg-emerald-900/30 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors"
                                        data-url="{{ route('sistema.routers.sync-pppoe', $r) }}"
                                        data-csrf="{{ csrf_token() }}">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">Sync PPPoE</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Sincronizar usuarios</span>
                                        </span>
                                    </button>

                                    <button type="button"
                                        class="router-export-script-btn flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-amber-50 dark:hover:bg-amber-900/30 hover:border-amber-300 dark:hover:border-amber-700 transition-colors"
                                        data-url="{{ route('sistema.routers.export-pppoe-script', ['router' => $r, 'formato' => 'json']) }}"
                                        data-nombre="{{ $r->nombre }}">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">Script PPPoE</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Generar / copiar</span>
                                        </span>
                                    </button>

                                    <a href="{{ route('sistema.router-scripts.index', ['router_origen_id' => $r->router_id]) }}"
                                        class="flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">Scripts</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Gestor de scripts</span>
                                        </span>
                                    </a>

                                    <a href="{{ route('sistema.router-schedulers.index', ['router_origen_id' => $r->router_id]) }}"
                                        class="flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-cyan-50 dark:hover:bg-cyan-900/30 hover:border-cyan-300 dark:hover:border-cyan-700 transition-colors">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900/50 text-cyan-600 dark:text-cyan-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">Schedulers</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Tareas programadas</span>
                                        </span>
                                    </a>

                                    <a href="{{ route('sistema.router-network-backups.index', ['router_origen_id' => $r->router_id]) }}"
                                        class="flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-2.5 text-left hover:bg-orange-50 dark:hover:bg-orange-900/30 hover:border-orange-300 dark:hover:border-orange-700 transition-colors">
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/50 text-orange-600 dark:text-orange-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">Red</span>
                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400 leading-snug">Backup IP y rutas</span>
                                        </span>
                                    </a>
                                </div>

                                <div class="mt-2 flex items-center gap-2">
                                    <a href="{{ route('sistema.routers.edit', $r) }}"
                                        class="flex-1 text-center rounded-lg px-3 py-2 text-xs font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:hover:bg-purple-900/50">
                                        Editar
                                    </a>
                                    <form action="{{ route('sistema.routers.destroy', $r) }}" method="POST" class="flex-1"
                                        onsubmit="return confirm('¿Eliminar este router?{{ $r->router_ip_pools_count > 0 ? ' También se eliminarán sus pools de IP ('.$r->router_ip_pools_count.').' : '' }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>

                                <p class="router-action-status mt-2 min-h-[1rem] text-[11px] text-gray-500 dark:text-gray-400" aria-live="polite"></p>
                            </div>
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

<div id="router-script-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" id="router-script-modal-backdrop"></div>
        <div class="relative w-full max-w-3xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Script PPPoE para consola</h2>
                    <p id="router-script-modal-subtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                </div>
                <button type="button" id="router-script-modal-close" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">&times;</button>
            </div>
            <div class="p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Copiá el contenido y pegalo en la terminal de Winbox, SSH o WebFig del MikroTik.
                </p>
                <textarea id="router-script-textarea" readonly rows="16"
                    class="w-full font-mono text-xs sm:text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-3 focus:outline-none focus:ring-2 focus:ring-purple-500/20"></textarea>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <a id="router-script-download" href="#" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:underline">Descargar .rsc</a>
                <button type="button" id="router-script-copy" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">Copiar al portapapeles</button>
            </div>
        </div>
    </div>
</div>

<div id="router-dhcp-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" id="router-dhcp-modal-backdrop"></div>
        <div class="relative w-full max-w-5xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700 max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">DHCP leases / PPPoE</h2>
                    <p id="router-dhcp-modal-subtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate"></p>
                </div>
                <button type="button" id="router-dhcp-modal-close" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">&times;</button>
            </div>
            <div class="p-4 flex-shrink-0 space-y-3 border-b border-gray-100 dark:border-gray-700">
                <div class="flex flex-wrap gap-2" id="router-dhcp-modal-stats"></div>
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                    <input type="search" id="router-dhcp-filter" placeholder="Filtrar por IP, MAC, hostname…"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500/30">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                        <input type="checkbox" id="router-dhcp-solo-activos" class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500" checked>
                        Solo activos (bound)
                    </label>
                </div>
            </div>
            <div class="overflow-auto flex-1 min-h-0">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900/90 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">IP</th>
                            <th class="px-4 py-2.5 font-semibold">MAC</th>
                            <th class="px-4 py-2.5 font-semibold">Hostname</th>
                            <th class="px-4 py-2.5 font-semibold">Estado</th>
                            <th class="px-4 py-2.5 font-semibold">Server</th>
                            <th class="px-4 py-2.5 font-semibold">Expira</th>
                        </tr>
                    </thead>
                    <tbody id="router-dhcp-modal-tbody" class="divide-y divide-gray-100 dark:divide-gray-700"></tbody>
                </table>
                <p id="router-dhcp-modal-empty" class="hidden px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No hay leases para mostrar.</p>
            </div>
            <div class="flex justify-end p-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                <button type="button" id="router-dhcp-modal-close-btn" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm font-medium text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function setStatus(btn, text, ok) {
        var card = btn.closest('article');
        var el = card ? card.querySelector('.router-action-status') : null;
        if (!el) return;
        el.textContent = text || '';
        el.className = 'router-action-status mt-2 min-h-[1rem] text-[11px] ' + (ok === true ? 'text-emerald-600 dark:text-emerald-400' : (ok === false ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'));
    }

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

    document.addEventListener('click', function() {
        document.querySelectorAll('[data-tools-panel]').forEach(function(p) {
            p.classList.add('hidden');
        });
    });

    document.querySelectorAll('[data-accordion-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var root = btn.closest('[data-router-accordion]');
            var panel = root.querySelector('[data-accordion-panel]');
            var chevron = root.querySelector('[data-accordion-chevron]');
            var willOpen = panel.classList.contains('hidden');

            // Un accordion abierto a la vez
            document.querySelectorAll('[data-router-accordion]').forEach(function(other) {
                if (other === root) return;
                other.querySelector('[data-accordion-panel]')?.classList.add('hidden');
                other.querySelector('[data-accordion-toggle]')?.setAttribute('aria-expanded', 'false');
                other.querySelector('[data-accordion-chevron]')?.classList.remove('rotate-180');
            });

            panel.classList.toggle('hidden', !willOpen);
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            chevron?.classList.toggle('rotate-180', willOpen);
        });
    });

    document.querySelectorAll('.router-test-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var token = this.getAttribute('data-csrf') || csrf;
            this.disabled = true;
            setStatus(this, 'Probando conexión...', null);
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: '{}' })
                .then(function(r) { return r.json(); })
                .then(function(d) { setStatus(btn, d.success ? 'Conexión exitosa' : (d.message || 'Error al conectar'), !!d.success); })
                .catch(function() { setStatus(btn, 'Error de red', false); })
                .finally(function() { btn.disabled = false; });
        });
    });

    var dhcpModal = document.getElementById('router-dhcp-modal');
    var dhcpSubtitle = document.getElementById('router-dhcp-modal-subtitle');
    var dhcpStats = document.getElementById('router-dhcp-modal-stats');
    var dhcpTbody = document.getElementById('router-dhcp-modal-tbody');
    var dhcpEmpty = document.getElementById('router-dhcp-modal-empty');
    var dhcpFilter = document.getElementById('router-dhcp-filter');
    var dhcpSoloActivos = document.getElementById('router-dhcp-solo-activos');
    var dhcpLeasesCache = [];

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function closeDhcpModal() { dhcpModal?.classList.add('hidden'); }
    function openDhcpModal() { dhcpModal?.classList.remove('hidden'); }

    function renderDhcpStats(d) {
        if (!dhcpStats) return;
        var staticCount = (Array.isArray(d.dhcp_leases) ? d.dhcp_leases : dhcpLeasesCache)
            .filter(function(l) { return !!l.static; }).length;
        var chips = [
            { label: 'DHCP activos', value: d.dhcp_activos || 0, tone: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' },
            { label: 'DHCP total', value: d.dhcp_total || 0, tone: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300' },
            { label: 'IP estáticas', value: staticCount, tone: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' },
            { label: 'PPPoE activos', value: d.pppoe_activos || 0, tone: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' }
        ];
        dhcpStats.innerHTML = chips.map(function(c) {
            return '<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ' + c.tone + '">'
                + escHtml(c.label) + ': <span class="tabular-nums">' + escHtml(c.value) + '</span></span>';
        }).join('');
    }

    function ipHref(address) {
        var ip = String(address || '').trim();
        if (!ip || ip === '-') return '';
        // Enlace HTTP a la IP (abre UI del equipo / CPE en nueva pestaña)
        return 'http://' + ip;
    }

    function renderDhcpTable() {
        if (!dhcpTbody) return;
        var q = (dhcpFilter?.value || '').trim().toLowerCase();
        var solo = !!(dhcpSoloActivos && dhcpSoloActivos.checked);
        var rows = dhcpLeasesCache.filter(function(l) {
            if (solo && !l.active) return false;
            if (!q) return true;
            var hay = [l.address, l.mac, l.hostname, l.status, l.server, l.static ? 'static estatica estática' : 'dynamic'].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });

        if (!rows.length) {
            dhcpTbody.innerHTML = '';
            dhcpEmpty?.classList.remove('hidden');
            return;
        }
        dhcpEmpty?.classList.add('hidden');
        dhcpTbody.innerHTML = rows.map(function(l) {
            var badge = l.active
                ? '<span class="inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">' + escHtml(l.status || 'bound') + '</span>'
                : '<span class="inline-flex rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300">' + escHtml(l.status || '-') + '</span>';
            var href = ipHref(l.address);
            var ipCell;
            if (href) {
                ipCell = '<a href="' + escHtml(href) + '" target="_blank" rel="noopener noreferrer" '
                    + 'class="font-mono text-xs text-sky-600 dark:text-sky-400 hover:underline" title="Abrir ' + escHtml(l.address) + ' en nueva pestaña">'
                    + escHtml(l.address) + '</a>';
            } else {
                ipCell = '<span class="font-mono text-xs text-gray-900 dark:text-gray-100">' + escHtml(l.address || '-') + '</span>';
            }
            if (l.static) {
                ipCell += ' <span class="inline-flex rounded-full bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300" title="Lease estático (make-static)">static</span>';
            }
            var rowClass = l.static
                ? 'hover:bg-amber-50/60 dark:hover:bg-amber-900/10 bg-amber-50/40 dark:bg-amber-900/10'
                : 'hover:bg-gray-50 dark:hover:bg-gray-700/40';
            return '<tr class="' + rowClass + '">'
                + '<td class="px-4 py-2 whitespace-nowrap">' + ipCell + '</td>'
                + '<td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300 whitespace-nowrap">' + escHtml(l.mac || '-') + '</td>'
                + '<td class="px-4 py-2 text-gray-800 dark:text-gray-200">' + escHtml(l.hostname || '-') + '</td>'
                + '<td class="px-4 py-2">' + badge + '</td>'
                + '<td class="px-4 py-2 text-gray-600 dark:text-gray-400">' + escHtml(l.server || '-') + '</td>'
                + '<td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">' + escHtml(l.expires || '-') + '</td>'
                + '</tr>';
        }).join('');
    }

    document.getElementById('router-dhcp-modal-close')?.addEventListener('click', closeDhcpModal);
    document.getElementById('router-dhcp-modal-close-btn')?.addEventListener('click', closeDhcpModal);
    document.getElementById('router-dhcp-modal-backdrop')?.addEventListener('click', closeDhcpModal);
    dhcpFilter?.addEventListener('input', renderDhcpTable);
    dhcpSoloActivos?.addEventListener('change', renderDhcpTable);

    document.querySelectorAll('.router-dhcp-pppoe-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var nombre = this.getAttribute('data-nombre') || 'Router';
            this.disabled = true;
            setStatus(this, 'Consultando DHCP / PPPoE...', null);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                .then(function(res) {
                    var d = res.data || {};
                    var msg = d.message || (res.ok ? 'Consulta OK' : 'Error al consultar');
                    setStatus(btn, msg, !!res.ok);
                    if (!res.ok) return;
                    dhcpLeasesCache = Array.isArray(d.dhcp_leases) ? d.dhcp_leases : [];
                    if (dhcpSubtitle) {
                        dhcpSubtitle.textContent = (d.router?.nombre || nombre) + ' · ' + (d.message || '');
                    }
                    if (dhcpFilter) dhcpFilter.value = '';
                    if (dhcpSoloActivos) dhcpSoloActivos.checked = true;
                    renderDhcpStats(d);
                    renderDhcpTable();
                    openDhcpModal();
                })
                .catch(function() { setStatus(btn, 'Error de red', false); })
                .finally(function() { btn.disabled = false; });
        });
    });

    document.querySelectorAll('.router-sync-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('¿Sincronizar usuarios PPPoE de la BD a este router?')) return;
            var url = this.getAttribute('data-url');
            var token = this.getAttribute('data-csrf') || csrf;
            this.disabled = true;
            setStatus(this, 'Sincronizando PPPoE...', null);
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ remove_orphans: false }) })
                .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                .then(function(res) {
                    var d = res.data;
                    var msg = '+' + (d.added || 0) + ' · ~' + (d.updated || 0) + ' · -' + (d.removed || 0);
                    if (d.errors && d.errors.length) msg += ' · ' + d.errors.length + ' error(es)';
                    setStatus(btn, msg, res.ok);
                    if (d.errors && d.errors.length) alert((d.message ? d.message + '\n\n' : '') + d.errors.join('\n'));
                })
                .catch(function() { setStatus(btn, 'Error de red', false); })
                .finally(function() { btn.disabled = false; });
        });
    });

    var scriptModal = document.getElementById('router-script-modal');
    var scriptTextarea = document.getElementById('router-script-textarea');
    var scriptSubtitle = document.getElementById('router-script-modal-subtitle');
    var scriptDownload = document.getElementById('router-script-download');
    var scriptCopyBtn = document.getElementById('router-script-copy');

    function closeScriptModal() { scriptModal?.classList.add('hidden'); }
    function openScriptModal() { scriptModal?.classList.remove('hidden'); }

    document.getElementById('router-script-modal-close')?.addEventListener('click', closeScriptModal);
    document.getElementById('router-script-modal-backdrop')?.addEventListener('click', closeScriptModal);

    document.querySelectorAll('.router-export-script-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var nombre = this.getAttribute('data-nombre') || 'Router';
            btn.disabled = true;
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    scriptTextarea.value = d.script || '';
                    scriptSubtitle.textContent = (d.router?.nombre || nombre) + ' · ' + (d.usuarios || 0) + ' usuario(s)';
                    if (d.download_url) scriptDownload.href = d.download_url;
                    openScriptModal();
                    scriptTextarea.focus();
                    scriptTextarea.select();
                })
                .catch(function() { alert('No se pudo cargar el script.'); })
                .finally(function() { btn.disabled = false; });
        });
    });

    scriptCopyBtn?.addEventListener('click', function() {
        var text = scriptTextarea.value;
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Script copiado al portapapeles.');
            }).catch(function() {
                scriptTextarea.select();
                document.execCommand('copy');
                alert('Script copiado al portapapeles.');
            });
        } else {
            scriptTextarea.select();
            document.execCommand('copy');
            alert('Script copiado al portapapeles.');
        }
    });
})();
</script>
@endsection
