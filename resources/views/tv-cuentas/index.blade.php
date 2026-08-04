@extends('layouts.app')

@section('title', 'Cuentas TV (app)')

@section('content')
@php
    $cardBase = 'block p-4 rounded-xl shadow border transition-colors bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700';
    $cardActive = 'ring-2 ring-purple-500 border-purple-400 dark:border-purple-500';
    $badgeClasses = [
        'vencido' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
        'por_vencer' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200',
        'ok' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    ];
    $badgeLabels = [
        'vencido' => 'Vencido',
        'por_vencer' => 'Por vencer',
        'ok' => 'Al día',
    ];
@endphp

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cuentas TV (streaming)</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Nebula: 3 perfiles con nombre. Lumix: 4 pantallas por cuenta. Los badges indican vencimiento (por vencer en {{ $tvAviso['dias_antes'] ?? \App\Models\TvCuenta::diasAvisoPorVencer() }} días o menos, vencido).
                @if(!empty($tvAviso['enabled']))
                    <span class="inline-flex items-center gap-1 ml-1 text-emerald-600 dark:text-emerald-400">· Avisos WA activos a las {{ $tvAviso['hora'] }}</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if(!empty($esAdmin))
            <button type="button" onclick="document.getElementById('tv-aviso-modal').showModal()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-purple-300 dark:border-purple-600 text-purple-800 dark:text-purple-200 rounded-lg font-medium hover:bg-purple-50 dark:hover:bg-purple-900/20 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                Avisos WhatsApp
            </button>
            @endif
            <a href="{{ route('tv-cuentas.exportar-excel', request()->query()) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Exportar Excel
            </a>
            @if(auth()->user()?->tienePermiso('tv.editar'))
            <a href="{{ route('tv-cuentas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                Nueva cuenta
            </a>
            @endif
        </div>
    </div>

    @if(!empty($esAdmin))
    <dialog id="tv-aviso-modal" class="rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-0 w-[min(100%,32rem)] backdrop:bg-black/40">
        <div class="p-5 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Avisos WhatsApp — vencimiento TV</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Solo administradores. Se notifica una vez por cuenta y fecha de vencimiento a los usuarios elegidos (deben tener teléfono WhatsApp).</p>
                </div>
                <button type="button" onclick="document.getElementById('tv-aviso-modal').close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl leading-none">&times;</button>
            </div>

            <form id="tv-aviso-config-form" method="POST" action="{{ route('tv-cuentas.aviso-config') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="enabled" value="1" @checked(!empty($tvAviso['enabled']))
                        class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                    Activar avisos automáticos
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Días de anticipación</label>
                        <input type="number" name="dias_antes" min="0" max="60" required
                            value="{{ old('dias_antes', $tvAviso['dias_antes'] ?? 7) }}"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Hora de envío</label>
                        <input type="time" name="hora" required
                            value="{{ old('hora', $tvAviso['hora'] ?? '09:00') }}"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Usuarios que reciben el aviso</label>
                    <div class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($staffAviso as $u)
                            <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                                <input type="checkbox" name="usuario_ids[]" value="{{ $u->usuario_id }}"
                                    @checked(in_array((int) $u->usuario_id, $tvAviso['usuario_ids'] ?? [], true))
                                    class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                <span class="flex-1">{{ $u->name }}</span>
                                <span class="text-xs text-gray-400">{{ $u->telefono ?: 'sin teléfono' }}</span>
                            </label>
                        @empty
                            <p class="px-3 py-2 text-sm text-gray-500">No hay personal activo.</p>
                        @endforelse
                    </div>
                </div>
            </form>

            <div class="flex flex-wrap justify-between gap-2 pt-1 border-t border-gray-100 dark:border-gray-700">
                <form method="POST" action="{{ route('tv-cuentas.aviso-probar') }}"
                    onsubmit="return confirm('¿Enviar avisos de prueba por WhatsApp para TODAS las cuentas por vencer/vencidas (según días de anticipación)?\n\nSe envía un mensaje [PRUEBA] por cada cuenta a los usuarios guardados.\nNo se registran como avisos automáticos.\n\nSi cambiaste destinatarios, guardá primero.');">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 text-sm rounded-lg border border-emerald-400 dark:border-emerald-600 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium">
                        Probar envío
                    </button>
                </form>
                <div class="flex gap-2 ml-auto">
                    <button type="button" onclick="document.getElementById('tv-aviso-modal').close()"
                        class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
                    <button type="submit" form="tv-aviso-config-form"
                        class="px-4 py-2 text-sm rounded-lg bg-purple-600 text-white hover:bg-purple-700 font-medium">Guardar</button>
                </div>
            </div>
        </div>
    </dialog>
    @if($errors->any())
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('tv-aviso-modal')?.showModal());</script>
    @endif
    @endif

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <a href="{{ route('tv-cuentas.index', array_filter(['estado' => 'todos', 'q' => $busqueda])) }}"
            class="{{ $cardBase }} {{ $filtro === 'todos' ? $cardActive : 'hover:border-purple-300 dark:hover:border-purple-600' }}">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total cuentas</p>
            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', array_filter(['estado' => 'vencido', 'q' => $busqueda])) }}"
            class="{{ $cardBase }} {{ $filtro === 'vencido' ? $cardActive : 'hover:border-red-400 dark:hover:border-red-500' }}">
            <p class="text-xs font-medium text-red-700 dark:text-red-300 uppercase tracking-wide">Vencidas</p>
            <p class="mt-1 text-3xl font-bold text-red-700 dark:text-red-300">{{ $stats['vencido'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', array_filter(['estado' => 'por_vencer', 'q' => $busqueda])) }}"
            class="{{ $cardBase }} {{ $filtro === 'por_vencer' ? $cardActive : 'hover:border-orange-400 dark:hover:border-orange-500' }}">
            <p class="text-xs font-medium text-orange-700 dark:text-orange-300 uppercase tracking-wide">Por vencer</p>
            <p class="mt-1 text-3xl font-bold text-orange-700 dark:text-orange-300">{{ $stats['por_vencer'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', array_filter(['estado' => 'ok', 'q' => $busqueda])) }}"
            class="{{ $cardBase }} {{ $filtro === 'ok' ? $cardActive : 'hover:border-green-400 dark:hover:border-green-500' }}">
            <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wide">Al día</p>
            <p class="mt-1 text-3xl font-bold text-green-700 dark:text-green-400">{{ $stats['ok'] }}</p>
        </a>
        <div class="{{ $cardBase }}">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Asignaciones en uso</p>
            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['asignaciones'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">de {{ $stats['cupos_totales'] ?? 0 }} posibles</p>
        </div>
    </div>

    <form method="GET" action="{{ route('tv-cuentas.index') }}" class="mb-4">
        @if($filtro !== 'todos')
            <input type="hidden" name="estado" value="{{ $filtro }}">
        @endif
        <div class="flex flex-col sm:flex-row gap-2 sm:max-w-xl">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                </svg>
                <input type="text" name="q" value="{{ $busqueda }}"
                    placeholder="Buscar por cuenta, usuario app, perfil, cliente o cédula…"
                    class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm placeholder-gray-400 dark:placeholder-gray-500">
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors">Buscar</button>
                @if($busqueda !== '')
                    <a href="{{ route('tv-cuentas.index', $filtro !== 'todos' ? ['estado' => $filtro] : []) }}"
                        class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Limpiar</a>
                @endif
            </div>
        </div>
    </form>

    @if($filtro !== 'todos' || $busqueda !== '')
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            @if($filtro !== 'todos')
                Filtrando: <span class="font-medium">{{ $badgeLabels[$filtro] ?? $filtro }}</span>
            @endif
            @if($busqueda !== '')
                @if($filtro !== 'todos') · @endif
                Búsqueda: <span class="font-medium">"{{ $busqueda }}"</span>
            @endif
            ({{ $cuentas->total() }} cuenta{{ $cuentas->total() === 1 ? '' : 's' }})
            · <a href="{{ route('tv-cuentas.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todas</a>
        </p>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">App</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre / usuario app</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Contraseña</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vencimiento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cupos en uso</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($cuentas as $c)
                        @php $estado = $c->estadoVencimiento(); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badgeClasses[$estado] ?? '' }}">
                                    {{ $badgeLabels[$estado] ?? $estado }}
                                </span>
                                <div class="text-xs mt-1 {{ $estado === 'vencido' ? 'text-red-700 dark:text-red-300 font-medium' : ($estado === 'por_vencer' ? 'text-orange-700 dark:text-orange-300 font-medium' : 'text-gray-500 dark:text-gray-400') }}">
                                    {{ $c->etiquetaEstadoVencimiento() }}
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $c->esLumix() ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200' }}">
                                    {{ \App\Models\TvCuenta::aplicaciones()[$c->aplicacion] ?? $c->aplicacion }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $c->nombre ?: '—' }}</span>
                                <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">{{ $c->usuario_app }}</div>
                                @if($c->esNebula())
                                    <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                        P1: {{ $c->perfil_1 ?: 'Perfil 1' }} | P2: {{ $c->perfil_2 ?: 'Perfil 2' }} | P3: {{ $c->perfil_3 ?: 'Perfil 3' }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                        Precio P1: {{ $c->precio_perfil_1 !== null ? 'Gs. '.number_format((float) $c->precio_perfil_1, 0, ',', '.') : '—' }}
                                        | Precio P2: {{ $c->precio_perfil_2 !== null ? 'Gs. '.number_format((float) $c->precio_perfil_2, 0, ',', '.') : '—' }}
                                        | Precio P3: {{ $c->precio_perfil_3 !== null ? 'Gs. '.number_format((float) $c->precio_perfil_3, 0, ',', '.') : '—' }}
                                    </div>
                                @else
                                    <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                        4 pantallas · Precios:
                                        @foreach([1,2,3,4] as $pi)
                                            P{{ $pi }} {{ $c->{'precio_pantalla_'.$pi} !== null ? 'Gs. '.number_format((float) $c->{'precio_pantalla_'.$pi}, 0, ',', '.') : '—' }}@if($pi < 4) | @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $c->password }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                <div>{{ $c->fechaVencimientoReferencia()->format('d/m/Y') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Día {{ $c->diaVencimientoMensual() }} de cada mes</div>
                            </td>
                            <td class="px-4 py-3 text-sm align-top">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->asignaciones_count >= $c->maxAsignaciones() ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200' }}">
                                    {{ $c->asignaciones_count }} / {{ $c->maxAsignaciones() }}
                                </span>
                                @if($c->asignaciones->isNotEmpty())
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach($c->asignaciones as $asig)
                                            @php
                                                $cliente = $asig->servicio?->cliente;
                                                $nombreCliente = trim(($cliente?->nombre ?? '') . ' ' . ($cliente?->apellido ?? ''));
                                            @endphp
                                            <li class="text-xs leading-snug">
                                                @if($asig->perfil_numero)
                                                    <span class="text-gray-500 dark:text-gray-400 font-medium">{{ $c->esLumix() ? 'Pan' : 'P' }}{{ $asig->perfil_numero }}:</span>
                                                @endif
                                                @if($asig->servicio?->plan)
                                                    <span class="inline-flex px-1 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200 mr-0.5"
                                                        title="{{ $asig->servicio->plan->nombre }}">{{ $asig->servicio->plan->iniciales() }}</span>
                                                @endif
                                                <span class="text-gray-900 dark:text-gray-100">{{ $nombreCliente !== '' ? $nombreCliente : '—' }}</span>
                                                @if($cliente?->cedula)
                                                    <span class="text-gray-500 dark:text-gray-400">({{ $cliente->cedula }})</span>
                                                @endif
                                                @if(($asig->es_promo ?? false) || ($asig->tvbox_comodato ?? false))
                                                    <span class="inline-flex flex-wrap gap-1 ml-1 align-middle">
                                                        @if($asig->es_promo ?? false)
                                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Promo</span>
                                                        @endif
                                                        @if($asig->tvbox_comodato ?? false)
                                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">TV box</span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Sin asignar</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if(auth()->user()?->tienePermiso('tv.editar'))
                                    <div class="inline-flex items-center justify-end gap-2 flex-wrap">
                                        <form action="{{ route('tv-cuentas.renovar', $c) }}" method="POST" class="inline"
                                            onsubmit="return confirm('¿Renovar esta cuenta por 1 mes adelante?');">
                                            @csrf
                                            <button type="submit" class="text-green-600 dark:text-green-400 hover:underline text-sm font-medium" title="Adelantar vencimiento 1 mes">
                                                +1 mes
                                            </button>
                                        </form>
                                        <a href="{{ route('tv-cuentas.edit', $c) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm font-medium">Editar</a>
                                    </div>
                                @else
                                    <span class="text-gray-400 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                @if($stats['total'] === 0)
                                    No hay cuentas TV registradas.
                                    @if(auth()->user()?->tienePermiso('tv.editar'))
                                        <a href="{{ route('tv-cuentas.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear una</a>
                                    @endif
                                @elseif($busqueda !== '')
                                    Ninguna cuenta coincide con la búsqueda "{{ $busqueda }}".
                                @else
                                    Ninguna cuenta coincide con el filtro seleccionado.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cuentas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">{{ $cuentas->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
