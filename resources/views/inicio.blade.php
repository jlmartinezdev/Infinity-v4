@extends('layouts.app')

@section('title', 'Inicio')

@php
    $rolNombre = $user->rol->descripcion ?? 'Usuario';
    $kpis = $kpis ?? [];
    $accionesRapidas = $accionesRapidas ?? [];
    $links = $links ?? [];

    $grupos = collect($links)->groupBy(fn ($l) => $l['grupo'] ?: 'Accesos');
    $gruposSecundarios = ['Configuración', 'Más', 'Referenciales'];
    $gruposPrincipales = $grupos->filter(fn ($_, $nombre) => ! in_array($nombre, $gruposSecundarios, true));
    $gruposOtros = $grupos->filter(fn ($_, $nombre) => in_array($nombre, $gruposSecundarios, true));

    $toneCard = [
        'rose' => 'hover:border-rose-400/60 dark:hover:border-rose-500/40',
        'violet' => 'hover:border-violet-400/60 dark:hover:border-violet-500/40',
        'blue' => 'hover:border-blue-400/60 dark:hover:border-blue-500/40',
        'amber' => 'hover:border-amber-400/60 dark:hover:border-amber-500/40',
        'emerald' => 'hover:border-emerald-400/60 dark:hover:border-emerald-500/40',
        'teal' => 'hover:border-teal-400/60 dark:hover:border-teal-500/40',
    ];
    $toneIcon = [
        'rose' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
        'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        'blue' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        'teal' => 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
        'gray' => 'bg-gray-500/10 text-gray-600 dark:text-gray-300',
    ];
    $grupoTone = [
        'Clientes' => 'blue',
        'Servicios' => 'emerald',
        'Facturación' => 'amber',
        'Tickets' => 'rose',
        'Tareas' => 'violet',
        'WhatsApp' => 'emerald',
        'Inventario' => 'teal',
        'TV streaming' => 'violet',
        'Accesos' => 'blue',
    ];

    $iconPaths = [
        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'wifi' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
        'currency' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'ticket' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
        'clipboard-list' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'chat' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        'cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'tv' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'server' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
        'user-group' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'bolt' => 'M13 10V3L4 14h7v7l9-11h-7z',
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z',
    ];
@endphp

@section('content')
<div class="max-w-[1200px] mx-auto min-w-0 space-y-6">
    {{-- Cabecera --}}
    <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-5 sm:p-6 shadow-sm overflow-hidden relative">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 via-transparent to-emerald-500/5 pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Panel operativo</p>
                <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white truncate">Hola, {{ $user->name ?? 'Usuario' }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Accesos rápidos según tu rol y permisos.</p>
            </div>
            <span class="inline-flex self-start items-center gap-2 rounded-full border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ $rolNombre }}
            </span>
        </div>
    </div>

    {{-- KPIs --}}
    @if(count($kpis) > 0)
    @php
        $kpiGrid = match (min(4, count($kpis))) {
            1 => 'grid-cols-1',
            2 => 'grid-cols-2',
            3 => 'grid-cols-2 lg:grid-cols-3',
            default => 'grid-cols-2 lg:grid-cols-4',
        };
    @endphp
    <div class="grid {{ $kpiGrid }} gap-3 sm:gap-4">
        @foreach($kpis as $kpi)
            @php $tone = $kpi['tone'] ?? 'blue'; @endphp
            <a href="{{ $kpi['href'] ?? '#' }}"
               class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm transition-all {{ $toneCard[$tone] ?? $toneCard['blue'] }}">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $kpi['value'] }}</p>
                <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $kpi['hint'] }}</p>
            </a>
        @endforeach
    </div>
    @endif

    {{-- Acciones rápidas --}}
    @if(count($accionesRapidas) > 0)
    <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-5 sm:p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Acciones rápidas</h2>
        @php
            $accionesGrid = match (min(4, count($accionesRapidas))) {
                1 => 'grid-cols-1',
                2 => 'grid-cols-2',
                3 => 'grid-cols-2 lg:grid-cols-3',
                default => 'grid-cols-2 lg:grid-cols-4',
            };
        @endphp
        <div class="grid {{ $accionesGrid }} gap-3">
            @foreach($accionesRapidas as $accion)
                @php
                    $tone = $accion['tone'] ?? 'blue';
                    $icon = $accion['icon'] ?? 'document';
                    $path = $iconPaths[$icon] ?? $iconPaths['document'];
                @endphp
                <a href="{{ $accion['href'] }}"
                   class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 dark:border-white/15 px-4 py-5 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-all group">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $toneIcon[$tone] ?? $toneIcon['gray'] }} group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $path }}"/></svg>
                    </span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 text-center">{{ $accion['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Módulos --}}
    @if(empty($links))
        <div class="rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-6 text-amber-900 dark:text-amber-100 text-sm">
            No tenés acceso a ningún módulo todavía. Pedile a un administrador que te asigne permisos o rol.
        </div>
    @else
        @foreach($gruposPrincipales as $nombreGrupo => $itemsGrupo)
            @php $tone = $grupoTone[$nombreGrupo] ?? 'blue'; @endphp
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white px-1">{{ $nombreGrupo }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($itemsGrupo as $link)
                        @php
                            $icon = $link['icon'] ?? 'document';
                            $path = $iconPaths[$icon] ?? $iconPaths['document'];
                        @endphp
                        <a href="{{ url($link['path']) }}"
                           class="group flex items-start gap-3 rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 shadow-sm transition-all {{ $toneCard[$tone] ?? $toneCard['blue'] }}">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $toneIcon[$tone] ?? $toneIcon['gray'] }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $path }}"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $link['label'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $link['path'] }}</p>
                            </div>
                            <svg class="w-4 h-4 mt-1 text-gray-300 dark:text-gray-600 group-hover:text-blue-400 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if($gruposOtros->isNotEmpty())
            <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Otros accesos</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($gruposOtros as $itemsGrupo)
                        @foreach($itemsGrupo as $link)
                            <a href="{{ url($link['path']) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-white/10 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
