@extends('layouts.app')

@section('title', 'Cuentas TV (app)')

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
    $filtroApp = $filtroApp ?? 'todos';
    $filtroCupos = $filtroCupos ?? 'todos';
    $orden = $orden ?? 'urgencia';
    $estadosPago = [
        'todos' => 'Todos los pagos',
        'vencido' => 'Vencido',
        'por_vencer' => 'Por vencer',
        'ok' => 'Al día',
    ];
    $queryListado = array_filter([
        'estado' => ($filtro ?? 'todos') !== 'todos' ? $filtro : null,
        'q' => ($busqueda ?? '') !== '' ? $busqueda : null,
        'app' => $filtroApp !== 'todos' ? $filtroApp : null,
        'cupos' => $filtroCupos !== 'todos' ? $filtroCupos : null,
        'orden' => $orden !== 'urgencia' ? $orden : null,
    ]);
    $ordenLabels = [
        'urgencia' => 'Urgencia (vencidas primero)',
        'vencimiento_asc' => 'Vencimiento — más próximo',
        'vencimiento_desc' => 'Vencimiento — más lejano',
        'nombre_asc' => 'Nombre A → Z',
        'nombre_desc' => 'Nombre Z → A',
        'usuario_asc' => 'Usuario app A → Z',
        'usuario_desc' => 'Usuario app Z → A',
        'app' => 'App (Nebula / Lumix)',
        'cupos_desc' => 'Cupos — más en uso',
        'cupos_asc' => 'Cupos — más libres',
        'estado_pago' => 'Estado de pago',
    ];
    $cuposLabels = [
        'todos' => 'Todos los cupos',
        'libres' => 'Con cupos libres',
        'llenas' => 'Completas (sin cupo)',
        'vacias' => 'Sin asignar',
    ];
    $hayFiltrosActivos = ($filtro ?? 'todos') !== 'todos'
        || ($busqueda ?? '') !== ''
        || $filtroApp !== 'todos'
        || $filtroCupos !== 'todos'
        || $orden !== 'urgencia';
    $cantidadFiltrosPanel = (int) (
        (($filtro ?? 'todos') !== 'todos')
        + ($filtroApp !== 'todos')
        + ($filtroCupos !== 'todos')
        + ($orden !== 'urgencia')
    );
    $resaltar = fn (?string $texto): string => \App\Support\SearchHighlight::html($texto, $busqueda ?? '');
@endphp

<div class="max-w-[1600px] mx-auto">
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
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Solo administradores. Un aviso por día mientras la cuenta esté en la ventana de anticipación. Hace falta plantilla Meta (fuera de las 24 h no llega texto libre). Ver <code class="text-xs">docs/whatsapp-plantilla-tv-vencimiento.md</code>.</p>
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
        <a href="{{ route('tv-cuentas.index', array_merge($queryListado, ['estado' => 'todos'])) }}"
            class="{{ $cardBase }} {{ $filtro === 'todos' ? $cardActive : 'hover:border-purple-300 dark:hover:border-purple-600' }}">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total cuentas</p>
            <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', array_merge($queryListado, ['estado' => 'vencido'])) }}"
            class="{{ $cardBase }} {{ $filtro === 'vencido' ? $cardActive : 'hover:border-red-400 dark:hover:border-red-500' }}">
            <p class="text-xs font-medium text-red-700 dark:text-red-300 uppercase tracking-wide">Vencidas</p>
            <p class="mt-1 text-3xl font-bold text-red-700 dark:text-red-300">{{ $stats['vencido'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', array_merge($queryListado, ['estado' => 'por_vencer'])) }}"
            class="{{ $cardBase }} {{ $filtro === 'por_vencer' ? $cardActive : 'hover:border-orange-400 dark:hover:border-orange-500' }}">
            <p class="text-xs font-medium text-orange-700 dark:text-orange-300 uppercase tracking-wide">Por vencer</p>
            <p class="mt-1 text-3xl font-bold text-orange-700 dark:text-orange-300">{{ $stats['por_vencer'] }}</p>
        </a>
        <a href="{{ route('tv-cuentas.index', array_merge($queryListado, ['estado' => 'ok'])) }}"
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
        <div class="flex items-stretch gap-2">
            <div class="flex items-center flex-1 min-w-0 min-h-[2.75rem] sm:min-h-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-500/20">
                <span class="pl-3 flex items-center shrink-0 text-gray-400 dark:text-gray-500 pointer-events-none" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                    </svg>
                </span>
                <input type="search" name="q" id="tv-busqueda" value="{{ $busqueda }}"
                    placeholder="Usuario, cliente o cédula"
                    aria-label="Buscar cuentas TV"
                    autocomplete="off"
                    enterkeyhint="search"
                    class="flex-1 min-w-0 border-0 bg-transparent pl-2 pr-3 py-2.5 sm:py-2 text-base sm:text-sm leading-normal focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 [appearance:textfield] [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden">
            </div>
            <div class="relative shrink-0" id="tv-filtros-wrap">
                <button
                    type="button"
                    id="tv-filtros-btn"
                    class="relative inline-flex items-center gap-2 h-full px-4 py-2.5 rounded-lg border font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/20 {{ $cantidadFiltrosPanel ? 'border-purple-600 bg-purple-600 text-white hover:bg-purple-700' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                    aria-expanded="false"
                    aria-controls="tv-filtros-menu"
                    title="Filtros"
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
                    id="tv-filtros-menu"
                    class="hidden absolute right-0 mt-2 w-80 max-w-sm py-3 px-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl z-30"
                >
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Filtros</p>
                        @if($cantidadFiltrosPanel || ($busqueda ?? '') !== '')
                            <a href="{{ route('tv-cuentas.index') }}" class="text-xs text-purple-600 dark:text-purple-400 hover:underline">Limpiar</a>
                        @endif
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado de pago</label>
                            <select name="estado" aria-label="Estado de pago"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                @foreach($estadosPago as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected(($filtro ?? 'todos') === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Aplicación</label>
                            <select name="app" aria-label="Aplicación"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                <option value="todos" @selected($filtroApp === 'todos')>Todas las apps</option>
                                <option value="nebula" @selected($filtroApp === 'nebula')>Solo Nebula</option>
                                <option value="lumix" @selected($filtroApp === 'lumix')>Solo Lumix</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cupos</label>
                            <select name="cupos" aria-label="Cupos"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                @foreach($cuposLabels as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($filtroCupos === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Orden</label>
                            <select name="orden" aria-label="Orden"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                @foreach($ordenLabels as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($orden === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors">Aplicar</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if($hayFiltrosActivos)
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            @if($filtro !== 'todos')
                Estado de pago: <span class="font-medium">{{ $badgeLabels[$filtro] ?? $filtro }}</span>
            @endif
            @if($busqueda !== '')
                @if($filtro !== 'todos') · @endif
                Búsqueda: <span class="font-medium">"{{ $busqueda }}"</span>
            @endif
            @if($filtroApp !== 'todos')
                · App: <span class="font-medium">{{ \App\Models\TvCuenta::aplicaciones()[$filtroApp] ?? $filtroApp }}</span>
            @endif
            @if($filtroCupos !== 'todos')
                · Cupos: <span class="font-medium">{{ $cuposLabels[$filtroCupos] ?? $filtroCupos }}</span>
            @endif
            @if($orden !== 'urgencia')
                · Orden: <span class="font-medium">{{ $ordenLabels[$orden] ?? $orden }}</span>
            @endif
            ({{ $cuentas->total() }} cuenta{{ $cuentas->total() === 1 ? '' : 's' }})
            · <a href="{{ route('tv-cuentas.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todas</a>
        </p>
    @endif

    <div class="space-y-4">
        @forelse($cuentas as $c)
            @php
                $estado = $c->estadoVencimiento();
                $maxSlots = $c->maxAsignaciones();
                $asigPorPerfil = $c->asignaciones->keyBy(fn ($a) => (int) $a->perfil_numero);
                $nombresPerfil = $c->esNebula()
                    ? [1 => $c->perfil_1 ?: 'Perfil 1', 2 => $c->perfil_2 ?: 'Perfil 2', 3 => $c->perfil_3 ?: 'Perfil 3']
                    : [1 => 'Pantalla 1', 2 => 'Pantalla 2', 3 => 'Pantalla 3', 4 => 'Pantalla 4'];
            @endphp
            <article class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-4 sm:p-5">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badgeClasses[$estado] ?? '' }}">
                                {{ $badgeLabels[$estado] ?? $estado }}
                            </span>
                            @if($estado !== 'ok')
                                <span class="text-xs font-medium {{ $estado === 'vencido' ? 'text-red-700 dark:text-red-300' : 'text-orange-700 dark:text-orange-300' }}">
                                    {{ $c->etiquetaEstadoVencimiento() }}
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $c->esLumix() ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200' }}">
                                {{ \App\Models\TvCuenta::aplicaciones()[$c->aplicacion] ?? $c->aplicacion }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->asignaciones_count >= $maxSlots ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200' }}">
                                {{ $c->asignaciones_count }} / {{ $maxSlots }}
                            </span>
                        </div>
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Cuenta</p>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $c->nombre ?: 'Sin nombre' }}">{!! $resaltar($c->nombre ?: 'Sin nombre') !!}</h2>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Usuario app</p>
                        <div class="mt-0.5 flex items-center gap-1.5 min-w-0">
                            <p class="font-mono text-sm text-gray-800 dark:text-gray-200 break-all">{!! $resaltar($c->usuario_app) !!}</p>
                            @if(filled($c->usuario_app))
                                <button type="button"
                                    class="shrink-0 p-1 rounded-md text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                    data-tv-copiar="{{ $c->usuario_app }}"
                                    title="Copiar usuario"
                                    aria-label="Copiar usuario">
                                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Contraseña</p>
                        <div class="mt-0.5 flex items-center gap-1.5 min-w-0">
                            <p class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">{{ $c->password }}</p>
                            @if(filled($c->password))
                                <button type="button"
                                    class="shrink-0 p-1 rounded-md text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                    data-tv-copiar="{{ $c->password }}"
                                    title="Copiar contraseña"
                                    aria-label="Copiar contraseña">
                                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">Vencimiento</p>
                        <p class="mt-0.5 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $c->fechaVencimientoReferencia()->format('d/m/Y') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Día {{ $c->diaVencimientoMensual() }} de cada mes</p>
                    </div>

                    <div class="flex-shrink-0 flex items-center gap-3">
                        @if(auth()->user()?->tienePermiso('tv.editar'))
                            <form action="{{ route('tv-cuentas.renovar', $c) }}" method="POST" class="inline"
                                onsubmit="return confirm('¿Renovar esta cuenta por 1 mes adelante?');">
                                @csrf
                                <button type="submit" class="text-green-600 dark:text-green-400 hover:underline text-sm font-medium whitespace-nowrap" title="Adelantar vencimiento 1 mes">
                                    +1 mes
                                </button>
                            </form>
                            <a href="{{ route('tv-cuentas.edit', $c) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm font-medium">Editar</a>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 {{ $maxSlots >= 4 ? 'xl:grid-cols-4' : 'lg:grid-cols-3' }} gap-3">
                    @for($i = 1; $i <= $maxSlots; $i++)
                        @php
                            $asig = $asigPorPerfil->get($i);
                            $precioCampo = $c->esNebula() ? 'precio_perfil_'.$i : 'precio_pantalla_'.$i;
                            $precio = $c->{$precioCampo};
                            $cliente = $asig?->servicio?->cliente;
                            $nombreCliente = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
                            $slotLabel = $c->esLumix() ? 'Pantalla '.$i : 'P'.$i;
                        @endphp
                        @if($asig)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3 min-h-[7.5rem]">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ $slotLabel }}
                                            <span class="normal-case font-medium text-gray-700 dark:text-gray-200">{!! $resaltar($nombresPerfil[$i] ?? '') !!}</span>
                                        </p>
                                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug break-words">
                                            {!! $nombreCliente !== '' ? $resaltar($nombreCliente) : '—' !!}
                                        </p>
                                        @if($cliente?->cedula)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{!! $resaltar($cliente->cedula) !!}</p>
                                        @endif
                                    </div>
                                    @if($asig->servicio?->plan)
                                        <span class="shrink-0 inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200"
                                            title="{{ $asig->servicio->plan->nombre }}">{{ $asig->servicio->plan->iniciales() }}</span>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    @if($precio !== null)
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">Gs. {{ number_format((float) $precio, 0, ',', '.') }}</span>
                                    @endif
                                    @if($asig->es_promo ?? false)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Promo</span>
                                    @endif
                                    @if($asig->tvbox_comodato ?? false)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">TV box</span>
                                    @endif
                                    <button type="button"
                                        data-tv-historial-pago="{{ route('tv-cuentas.asignaciones.historial-pago', [$c, $asig]) }}"
                                        data-tv-historial-titulo="Pagos — {{ $nombreCliente !== '' ? $nombreCliente : 'Cliente' }}"
                                        class="ml-auto text-purple-600 dark:text-purple-400 hover:underline text-xs font-medium">
                                        Pagos
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-transparent p-3 min-h-[7.5rem] flex flex-col justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                        {{ $slotLabel }}
                                        <span class="normal-case font-medium">{!! $resaltar($nombresPerfil[$i] ?? '') !!}</span>
                                    </p>
                                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Libre</p>
                                </div>
                                @if($precio !== null)
                                    <p class="text-[11px] text-gray-400">Gs. {{ number_format((float) $precio, 0, ',', '.') }}</p>
                                @endif
                            </div>
                        @endif
                    @endfor
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-10 text-center text-gray-500 dark:text-gray-400">
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
            </div>
        @endforelse
    </div>
    @if($cuentas->hasPages())
        <div class="mt-4 px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">{{ $cuentas->withQueryString()->links() }}</div>
    @endif
</div>

@include('tv-cuentas._historial-pago-modal')

@push('scripts')
<script>
(function () {
    const iconCopy = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
    const iconOk = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';

    function fallbackCopy(texto) {
        const ta = document.createElement('textarea');
        ta.value = texto || '';
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    function marcarCopiado(btn) {
        const prev = btn.innerHTML;
        btn.innerHTML = iconOk;
        btn.classList.add('text-emerald-500');
        setTimeout(function () {
            btn.innerHTML = prev;
            btn.classList.remove('text-emerald-500');
        }, 1400);
    }

    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-tv-copiar]');
        if (!btn) return;
        const texto = btn.getAttribute('data-tv-copiar') || '';
        if (!texto) return;
        const done = function () { marcarCopiado(btn); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(done).catch(function () {
                fallbackCopy(texto);
                done();
            });
            return;
        }
        fallbackCopy(texto);
        done();
    });

    const wrap = document.getElementById('tv-filtros-wrap');
    const btnFiltros = document.getElementById('tv-filtros-btn');
    const menuFiltros = document.getElementById('tv-filtros-menu');
    if (wrap && btnFiltros && menuFiltros) {
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
            if (!wrap.contains(e.target)) setFiltrosOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setFiltrosOpen(false);
        });
    }
})();
</script>
@endpush
@endsection
