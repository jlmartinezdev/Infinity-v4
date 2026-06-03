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
                Nebula: 3 perfiles con nombre. Lumix: 4 pantallas por cuenta. Los badges indican vencimiento (por vencer en {{ \App\Models\TvCuenta::DIAS_AVISO_POR_VENCER }} días o menos, vencido).
            </p>
        </div>
        @if(auth()->user()?->tienePermiso('tv.editar'))
            <a href="{{ route('tv-cuentas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 shrink-0">
                Nueva cuenta
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <a href="{{ route('tv-cuentas.index', ['estado' => 'todos']) }}"
            class="{{ $cardBase }} {{ $filtro === 'todos' ? $cardActive : 'hover:border-purple-300 dark:hover:border-purple-600' }}">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total cuentas</p>
            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', ['estado' => 'vencido']) }}"
            class="{{ $cardBase }} {{ $filtro === 'vencido' ? $cardActive : 'hover:border-red-400 dark:hover:border-red-500' }}">
            <p class="text-xs font-medium text-red-700 dark:text-red-300 uppercase tracking-wide">Vencidas</p>
            <p class="mt-1 text-3xl font-bold text-red-700 dark:text-red-300">{{ $stats['vencido'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', ['estado' => 'por_vencer']) }}"
            class="{{ $cardBase }} {{ $filtro === 'por_vencer' ? $cardActive : 'hover:border-orange-400 dark:hover:border-orange-500' }}">
            <p class="text-xs font-medium text-orange-700 dark:text-orange-300 uppercase tracking-wide">Por vencer</p>
            <p class="mt-1 text-3xl font-bold text-orange-700 dark:text-orange-300">{{ $stats['por_vencer'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', ['estado' => 'ok']) }}"
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

    @if($filtro !== 'todos')
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Filtrando: <span class="font-medium">{{ $badgeLabels[$filtro] ?? $filtro }}</span>
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
