@extends('layouts.app')

@section('title', 'Sesiones hotspot')

@php
    $estados = \App\Models\Servicio::estadosDisponibles();
    $estadoBadge = [
        'A' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'S' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'C' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'X' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
        'P' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
    ];
    $origenBadge = [
        'locker' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'trial' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'sin-locker' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
        'sin-usuario' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
    ];
    $origenLabel = [
        'locker' => 'En línea',
        'trial' => 'Trial',
        'sin-locker' => 'Sin locker',
        'sin-sesion' => 'Sin sesión',
        'sin-usuario' => 'Sin usuario',
    ];
    $linkUser = 'underline underline-offset-2 decoration-gray-400 dark:decoration-gray-500 text-gray-900 dark:text-gray-100 hover:text-gray-700 dark:hover:text-white';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-11 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $btnSecondary = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-11 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $fieldClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-2.5 min-h-11 focus:border-purple-400 focus:ring-2 focus:ring-purple-400/20 focus:outline-none';
    $card = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800';
    $tabOn = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium bg-purple-50 text-purple-800 border border-purple-200 dark:bg-purple-950/40 dark:text-purple-200 dark:border-purple-800';
    $tabOff = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $puedePerfiles = auth()->user()?->tienePermiso('servicios-hotspot-perfiles.ver') || auth()->user()?->tienePermiso('servicios.ver');
    $puedeCrear = (bool) auth()->user()?->tienePermiso('servicios.editar');
    $btnAccion = 'inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $btnPeligro = 'inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-300 dark:border-gray-600 text-red-800 dark:text-red-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $macsBloqueadas = $macsBloqueadas ?? [];
    $nVivas = count($sesiones);
    $nLocker = 0;
    $nPortal = 0;
    $nSinLocker = 0;
    foreach ($sesiones as $filaConteo) {
        $origenConteo = (string) ($filaConteo['origen'] ?? '');
        if ($origenConteo === 'locker') {
            $nLocker++;
        } elseif (in_array($origenConteo, ['sin-sesion', 'sin-usuario', 'trial'], true)) {
            $nPortal++;
        } else {
            $nSinLocker++;
        }
    }
@endphp

@push('styles')
<style>
    @include('hotspot._cuota-ring-styles')
    .hotspot-dash-tech > summary {
        cursor: pointer;
        list-style: none;
    }
    .hotspot-dash-tech > summary::-webkit-details-marker {
        display: none;
    }
    #hotspot-dash-page ::selection {
        background: #e9d5ff;
        color: #111827;
    }
    html.dark #hotspot-dash-page ::selection {
        background: #6b21a8;
        color: #f3f4f6;
    }
    #hotspot-dash-page {
        scrollbar-color: #d1d5db transparent;
    }
    html.dark #hotspot-dash-page {
        scrollbar-color: #4b5563 transparent;
    }
    #hotspot-dash-pulse-bar {
        transition: width 1s linear;
    }
</style>
@endpush

@section('content')
<div id="hotspot-dash-page" class="max-w-7xl mx-auto space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Sesiones hotspot</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Quién está en el Wi‑Fi de un router y qué cuentas no tienen sesión.</p>
        </div>
    </div>

    <form id="hotspot-dash-router-form" method="GET" action="{{ route('hotspot.dashboard') }}" class="{{ $card }} p-4 space-y-4">
        <div>
            <label for="hotspot-dash-router-q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Router</label>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Filtrá por nombre, IP o nodo y elegí cuál consultar.</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[16rem]">
                <input type="search" id="hotspot-dash-router-q" autocomplete="off"
                    placeholder="Filtrar por nombre, IP o nodo"
                    aria-controls="hotspot-dash-router"
                    class="{{ $fieldClass }}">
            </div>
            <div class="flex-1 min-w-[16rem]">
                <label for="hotspot-dash-router" class="sr-only">Elegir router</label>
                <select name="router_id" id="hotspot-dash-router"
                    data-loaded="{{ $selectedRouter?->router_id }}"
                    class="{{ $fieldClass }}">
                    @unless($selectedRouter)
                        <option value="" selected disabled>Elegí un router</option>
                    @endunless
                    @foreach($routersPorNodo as $nodoNombre => $grupo)
                        <optgroup label="{{ $nodoNombre }}">
                            @foreach($grupo as $r)
                                <option value="{{ $r->router_id }}"
                                    data-nombre="{{ $r->nombre }}"
                                    data-ip="{{ $r->ip }}"
                                    data-nodo="{{ $nodoNombre }}"
                                    @selected($selectedRouter && (int) $selectedRouter->router_id === (int) $r->router_id)>
                                    {{ $r->nombre }} ({{ $r->ip }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" id="hotspot-dash-ver" class="{{ $btnSecondary }} w-full">Ver</button>
            </div>
        </div>
        <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <nav class="flex flex-wrap gap-2" aria-label="Vistas hotspot">
                <a href="{{ route('hotspot.dashboard', $selectedRouter ? ['router_id' => $selectedRouter->router_id] : []) }}" class="{{ $tabOn }}" aria-current="page">Sesiones</a>
                <a href="{{ route('hotspot.index') }}" class="{{ $tabOff }}">Usuarios hotspot</a>
                @if($puedePerfiles)
                    <a href="{{ route('hotspot.perfiles.index') }}" class="{{ $tabOff }}">Perfiles</a>
                @endif
            </nav>
            @if($selectedRouter)
                <label class="relative block w-full lg:w-64">
                    <span class="sr-only">Buscar en las tablas</span>
                    <input type="search" id="hotspot-dash-find" autocomplete="off"
                        placeholder="MAC, IP, usuario o cliente"
                        class="{{ $fieldClass }}">
                </label>
            @endif
        </div>
    </form>

    @if($selectedRouter)
        <div id="hotspot-dash-session-host"
            data-copy="Tu sesión se cerró. Entrá de nuevo para seguir las sesiones."
            data-entrar="{{ route('login') }}"></div>

        <div id="hotspot-dash-kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $kpis = [
                    ['Sesiones vivas', $routerError ? '—' : (string) $nVivas, 'En este router ahora'],
                    ['Con locker', $routerError ? '—' : (string) $nLocker, 'Cuenta de Infinity'],
                    ['En el portal', $routerError ? '—' : (string) $nPortal, 'Todavía no usó una cuenta'],
                    ['Sin sesión', (string) $offline->count(), 'Tienen cuenta y no están en línea'],
                ];
            @endphp
            @foreach($kpis as $kpi)
                <div class="{{ $card }} px-4 py-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $kpi[0] }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $kpi[1] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpi[2] }}</p>
                </div>
            @endforeach
        </div>

        <div id="hotspot-dash-watch" class="relative space-y-4">
            <div id="hotspot-dash-skel" class="{{ $card }} px-4 py-6 space-y-3" style="display: none;" aria-hidden="true">
                <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-8 w-2/3 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
            </div>

            <section id="hotspot-dash-vivos" class="relative {{ $card }} overflow-hidden" aria-labelledby="hotspot-dash-vivos-title">
                @if($routerError)
                    <div id="hotspot-dash-stale" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 px-4" style="display:flex;background:rgba(17,24,39,0.8)" role="alert">
                        <p class="max-w-md text-center text-sm font-medium text-white">No pude leer las sesiones de {{ $selectedRouter->nombre }}.</p>
                        <details class="hotspot-dash-tech max-w-md text-center">
                            <summary class="text-sm text-gray-200 underline underline-offset-2">Detalle técnico</summary>
                            <p class="mt-2 text-sm text-gray-200 break-words">{{ $routerError }}</p>
                        </details>
                        <a href="{{ route('hotspot.dashboard', ['router_id' => $selectedRouter->router_id]) }}" class="{{ $btnPrimary }}" data-dash-refresh>Reintentar</a>
                    </div>
                @endif
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="hotspot-dash-vivos-title" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2" aria-live="polite">
                            @unless($routerError)
                                <span class="inline-block h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span>
                            @endunless
                            Sesiones vivas
                            @unless($routerError)
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">({{ count($sesiones) }})</span>
                            @endunless
                        </h2>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $selectedRouter->nombre }} · {{ $selectedRouter->ip }}</p>
                        @if($consultadoEn)
                            <p id="hotspot-dash-pulse" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" aria-live="polite"
                                data-consultado="{{ $consultadoEn->toIso8601String() }}">ahora · se actualiza en 30 s</p>
                            <span class="mt-2 block h-1 w-24 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden" aria-hidden="true">
                                <span id="hotspot-dash-pulse-bar" class="block h-full bg-purple-600" style="width:100%"></span>
                            </span>
                        @endif
                    </div>
                    <a href="{{ route('hotspot.dashboard', ['router_id' => $selectedRouter->router_id]) }}" class="{{ $btnSecondary }}" data-dash-refresh>Actualizar</a>
                </div>

                @if($routerError)
                    <div class="px-4 py-16" aria-hidden="true"></div>
                @elseif(count($sesiones) === 0)
                    <div class="px-4 py-8 text-center" role="status">
                        <p class="text-sm text-gray-700 dark:text-gray-200">Nadie conectado en {{ $selectedRouter->nombre }}.</p>
                    </div>
                @else
                    <div id="hotspot-dash-vivos-scroll" class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Perfil / cuota</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Tiempo en línea</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($sesiones as $fila)
                                    <tr class="hotspot-dash-fila {{ $fila['origen'] === 'locker' ? '' : 'bg-amber-50/60 dark:bg-amber-950/20' }}"
                                        data-buscar="{{ mb_strtolower($fila['username'].' '.$fila['ip'].' '.$fila['mac'].' '.$fila['cliente'].' '.$fila['perfil']) }}">
                                        <td class="px-4 py-3">
                                            @if($fila['username'] !== '—' && $fila['origen'] === 'locker')
                                                <a href="{{ route('hotspot.index', ['buscar' => $fila['username']]) }}" class="font-mono font-medium {{ $linkUser }}">{{ $fila['username'] }}</a>
                                            @else
                                                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ $fila['username'] }}</span>
                                            @endif
                                            <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $fila['ip'] }}</span>
                                            <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $fila['mac'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200 {{ $fila['origen'] === 'sin-sesion' ? '' : 'max-w-[14rem] truncate' }}" title="{{ $fila['cliente'] }}">
                                            {{ $fila['cliente'] !== '' ? $fila['cliente'] : '—' }}
                                            @if($fila['origen'] === 'locker' && $fila['estado'] !== '')
                                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $estadoBadge[$fila['estado']] ?? $estadoBadge['X'] }}">{{ $estados[$fila['estado']] ?? $fila['estado'] }}</span>
                                            @elseif(! in_array($fila['origen'], ['locker', 'sin-sesion'], true))
                                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $origenBadge[$fila['origen']] ?? $estadoBadge['X'] }}">{{ $origenLabel[$fila['origen']] ?? $fila['origen'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @include('hotspot._cuota-cell', [
                                                'perfil' => $fila['perfil'],
                                                'vista' => $fila['cuota'] ?? null,
                                                'centroPct' => empty($fila['trial_tiempo']),
                                            ])
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-600 font-mono text-xs text-gray-800 dark:text-gray-100">{{ $fila['uptime'] }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                $macOk = preg_match('/^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/', (string) $fila['mac']) === 1;
                                            @endphp
                                            <div class="flex flex-wrap gap-1">
                                                @if($macOk)
                                                    <button type="button" class="{{ $btnAccion }}" data-copiar="{{ $fila['mac'] }}" title="Copiar MAC" aria-label="Copiar MAC {{ $fila['mac'] }}">@include('hotspot._icono', ['nombre' => 'copiar'])</button>
                                                @endif
                                                @if($puedeCrear && $macOk)
                                                    <form method="POST" action="{{ route('hotspot.dashboard.desconectar', $selectedRouter) }}" class="inline" onsubmit="return confirm(@json('¿Cerrar la sesión de '.$fila['mac'].'? Si entró con la cuenta del celular, no vuelve a entrar solo.'))">
                                                        @csrf
                                                        <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                        <button type="submit" class="{{ $btnPeligro }}" title="Desconectar" aria-label="Desconectar {{ $fila['mac'] }}">@include('hotspot._icono', ['nombre' => 'desconectar'])</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('hotspot.dashboard.bloquear', $selectedRouter) }}" class="inline" onsubmit="return confirm(@json('¿Bloquear '.$fila['mac'].'? No va a poder entrar al hotspot hasta que la desbloquees.'))">
                                                        @csrf
                                                        <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                        <button type="submit" class="{{ $btnPeligro }}" title="Bloquear MAC" aria-label="Bloquear {{ $fila['mac'] }}">@include('hotspot._icono', ['nombre' => 'bloquear'])</button>
                                                    </form>
                                                @endif
                                                @if($fila['origen'] === 'locker' && $fila['cliente_id'] && $puedeCrear)
                                                    <a href="{{ route('hotspot.clientes.edit', $fila['cliente_id']) }}" class="{{ $btnAccion }}" title="Editar" aria-label="Editar {{ $fila['username'] }}">@include('hotspot._icono', ['nombre' => 'editar'])</a>
                                                @elseif($fila['origen'] !== 'locker')
                                                    <a href="{{ route('hotspot.index') }}" class="{{ $btnAccion }}" title="Vincular" aria-label="Vincular cuenta">@include('hotspot._icono', ['nombre' => 'vincular'])</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(! $routerError && count($macsBloqueadas) > 0)
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">MACs bloqueadas</p>
                        <ul class="mt-2 flex flex-col gap-2">
                            @foreach($macsBloqueadas as $macBloqueada)
                                <li class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm text-gray-800 dark:text-gray-100">{{ $macBloqueada }}</span>
                                    @if($puedeCrear)
                                        <form method="POST" action="{{ route('hotspot.dashboard.desbloquear', $selectedRouter) }}" class="inline" onsubmit="return confirm(@json('¿Desbloquear '.$macBloqueada.'?'))">
                                            @csrf
                                            <input type="hidden" name="mac" value="{{ $macBloqueada }}">
                                            <button type="submit" class="{{ $btnAccion }}" title="Desbloquear" aria-label="Desbloquear {{ $macBloqueada }}">@include('hotspot._icono', ['nombre' => 'desbloquear'])</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section id="hotspot-dash-offline" class="{{ $card }} overflow-hidden" aria-labelledby="hotspot-dash-offline-title">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="hotspot-dash-offline-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">Con cuenta, sin sesión ({{ $offline->count() }})</h2>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Cuentas de este router que ahora no están en línea.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 p-0.5 text-sm" role="group" aria-label="Filtrar cuentas">
                            <button type="button" data-cuota-filtro="todos" class="px-2.5 py-1 rounded-md font-medium text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700" aria-pressed="true">Todos</button>
                            <button type="button" data-cuota-filtro="cuota" class="px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300" aria-pressed="false">Con cuota</button>
                            <button type="button" data-cuota-filtro="libre" class="px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300" aria-pressed="false">Sin cuota</button>
                        </div>
                        @if($puedeCrear)
                            <a href="{{ route('hotspot.create') }}" class="{{ $btnPrimary }}">Nuevo usuario</a>
                        @endif
                    </div>
                </div>
                @if($offline->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">
                        @if($mappedCount === 0)
                            Este router no tiene cuentas.
                        @else
                            Todas las cuentas de este router están en Sesiones vivas.
                        @endif
                    </p>
                @else
                    <div id="hotspot-dash-offline-scroll" class="overflow-x-auto max-h-[20rem] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Perfil / cuota</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Estado</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($offline as $sh)
                                    @php
                                        $codigo = $sh->servicio?->estado;
                                        $nombre = trim(($sh->cliente?->nombre ?? $sh->servicio?->cliente?->nombre ?? '').' '.($sh->cliente?->apellido ?? $sh->servicio?->cliente?->apellido ?? ''));
                                        $bytes = (int) ($consumo[$sh->username] ?? 0);
                                        $cuotaVista = \App\Services\RadiusHotspotSyncService::consumoVista($bytes, $sh->hotspotPerfil?->cuota_gb);
                                        $perfilNombre = $sh->hotspotPerfil?->nombreDistintoDeCuota() ?? '';
                                    @endphp
                                    <tr class="hotspot-dash-fila"
                                        data-cuota="{{ ! empty($cuotaVista['tiene_cuota']) ? 'cuota' : 'libre' }}"
                                        data-buscar="{{ mb_strtolower($sh->username.' '.$nombre.' '.$perfilNombre) }}">
                                        <td class="px-4 py-3 font-mono">
                                            <a href="{{ route('hotspot.index', ['buscar' => $sh->username]) }}" class="font-medium {{ $linkUser }}">{{ $sh->username }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200 max-w-[14rem] truncate" title="{{ $nombre }}">{{ $nombre !== '' ? $nombre : '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @include('hotspot._cuota-cell', [
                                                'perfil' => $perfilNombre,
                                                'vista' => $cuotaVista,
                                                'centroPct' => true,
                                            ])
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($codigo)
                                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $estadoBadge[$codigo] ?? $estadoBadge['X'] }}">{{ $estados[$codigo] ?? $codigo }}</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">Sin servicio</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @if($sh->cliente_id && $puedeCrear)
                                                    <a href="{{ route('hotspot.clientes.edit', $sh->cliente_id) }}" class="{{ $btnAccion }}" title="Editar" aria-label="Editar {{ $sh->username }}">@include('hotspot._icono', ['nombre' => 'editar'])</a>
                                                @endif
                                                <form method="POST" action="{{ route('hotspot.sync', $sh) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="from_dashboard" value="1">
                                                    <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                    <button type="submit" class="{{ $btnAccion }}" title="Sincronizar" aria-label="Sincronizar {{ $sh->username }}">@include('hotspot._icono', ['nombre' => 'sincronizar'])</button>
                                                </form>
                                                @if($puedeCrear && $sh->cliente_id)
                                                    <form method="POST" action="{{ route('hotspot.clientes.destroy', [$sh->cliente_id, $sh]) }}" class="inline" onsubmit="return confirm(@json('¿Quitar '.$sh->username.' del locker?'))">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="from_dashboard" value="1">
                                                        <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                        <button type="submit" class="{{ $btnPeligro }}" title="Quitar del locker" aria-label="Quitar {{ $sh->username }} del locker">@include('hotspot._icono', ['nombre' => 'quitar'])</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p id="hotspot-dash-offline-vacio" class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300" style="display:none" role="status">Ninguna cuenta coincide con el filtro.</p>
                @endif
            </section>
        </div>
    @else
        <div class="{{ $card }} px-4 py-10 text-center">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Elegí un router.</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Vas a ver quién está conectado al hotspot y las cuentas de ese router que no tienen sesión.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('hotspot-dash-router-form');
    var select = document.getElementById('hotspot-dash-router');
    var filter = document.getElementById('hotspot-dash-router-q');
    var find = document.getElementById('hotspot-dash-find');
    var watch = document.getElementById('hotspot-dash-watch');
    var skel = document.getElementById('hotspot-dash-skel');
    var vivos = document.getElementById('hotspot-dash-vivos');
    var pulse = document.getElementById('hotspot-dash-pulse');
    var sessionHost = document.getElementById('hotspot-dash-session-host');
    var loaded = select ? String(select.getAttribute('data-loaded') || '') : '';
    var leaving = false;
    var dead = false;
    var cuotaFiltro = 'todos';
    var REFRESH_S = 30;
    var remain = REFRESH_S;
    var pingUrl = @json(route('sesion.ping', [], false));
    var btnPrimaryClass = @json($btnPrimary);
    var filtroOn = 'px-2.5 py-1 rounded-md font-medium text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700';
    var filtroOff = 'px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300';

    function isDirty() {
        return !!(select && loaded !== '' && String(select.value || '') !== '' && String(select.value) !== loaded);
    }

    function isPaused() {
        if (leaving || dead || document.hidden || !loaded) return true;
        if (isDirty()) return true;
        if (select && document.activeElement === select) return true;
        if (filter && document.activeElement === filter) return true;
        if (find && document.activeElement === find) return true;
        return false;
    }

    function showSkel() {
        leaving = true;
        if (skel) skel.style.display = 'block';
        if (vivos) vivos.style.display = 'none';
        var offline = document.getElementById('hotspot-dash-offline');
        if (offline) offline.style.display = 'none';
        var stale = document.getElementById('hotspot-dash-stale');
        if (stale) stale.style.display = 'none';
    }

    function submitRouter() {
        if (!form || !select || !select.value) return;
        showSkel();
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    function ageText() {
        if (!pulse) return 'ahora';
        var iso = pulse.getAttribute('data-consultado');
        if (!iso) return 'ahora';
        var then = Date.parse(iso);
        if (!then) return 'ahora';
        var sec = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (sec < 5) return 'ahora';
        if (sec < 60) return 'hace ' + sec + ' s';
        if (sec < 3600) return 'hace ' + Math.floor(sec / 60) + ' min';
        return 'hace ' + Math.floor(sec / 3600) + ' h';
    }

    function paintPulse() {
        var bar = document.getElementById('hotspot-dash-pulse-bar');
        if (bar) {
            bar.style.width = (dead || isPaused()) ? '0%' : (Math.max(0, Math.round((remain / REFRESH_S) * 100)) + '%');
        }
        if (!pulse) return;
        if (dead) {
            pulse.textContent = '';
            return;
        }
        if (isPaused()) {
            pulse.textContent = 'Actualización en pausa';
            return;
        }
        pulse.textContent = ageText() + ' · se actualiza en ' + remain + ' s';
    }

    function marcarCuota() {
        document.querySelectorAll('[data-cuota-filtro]').forEach(function (btn) {
            var on = btn.getAttribute('data-cuota-filtro') === cuotaFiltro;
            btn.className = on ? filtroOn : filtroOff;
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function filtrarFilas() {
        var term = find ? String(find.value || '').trim().toLowerCase() : '';
        document.querySelectorAll('#hotspot-dash-vivos .hotspot-dash-fila').forEach(function (fila) {
            var hay = String(fila.getAttribute('data-buscar') || '');
            fila.hidden = term !== '' && hay.indexOf(term) === -1;
        });
        var visibles = 0;
        var total = 0;
        document.querySelectorAll('#hotspot-dash-offline .hotspot-dash-fila').forEach(function (fila) {
            total += 1;
            var hay = String(fila.getAttribute('data-buscar') || '');
            var cuota = String(fila.getAttribute('data-cuota') || '');
            var pasaTexto = term === '' || hay.indexOf(term) !== -1;
            var pasaCuota = cuotaFiltro === 'todos' || cuota === cuotaFiltro;
            var ver = pasaTexto && pasaCuota;
            fila.hidden = !ver;
            if (ver) visibles += 1;
        });
        var vacio = document.getElementById('hotspot-dash-offline-vacio');
        if (vacio) vacio.style.display = (total > 0 && visibles === 0) ? 'block' : 'none';
        marcarCuota();
    }

    function showSessionDead() {
        dead = true;
        leaving = false;
        if (sessionHost && !sessionHost.dataset.shown) {
            sessionHost.dataset.shown = '1';
            var box = document.createElement('div');
            box.className = 'mb-4 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3';
            box.setAttribute('role', 'alert');
            var p = document.createElement('p');
            p.className = 'text-sm font-medium text-red-800 dark:text-red-200';
            p.textContent = sessionHost.getAttribute('data-copy') || '';
            var a = document.createElement('a');
            a.href = sessionHost.getAttribute('data-entrar') || '';
            a.className = btnPrimaryClass + ' mt-3';
            a.textContent = 'Entrar';
            box.appendChild(p);
            box.appendChild(a);
            sessionHost.appendChild(box);
        }
        paintPulse();
    }

    function scrollWrap(id) {
        return document.getElementById(id);
    }

    function applyWatch(doc) {
        var newKpis = doc.getElementById('hotspot-dash-kpis');
        var newVivos = doc.getElementById('hotspot-dash-vivos');
        var newOff = doc.getElementById('hotspot-dash-offline');
        if (!newVivos || !newOff) return false;
        var yV = scrollWrap('hotspot-dash-vivos-scroll');
        var yO = scrollWrap('hotspot-dash-offline-scroll');
        var topV = yV ? yV.scrollTop : 0;
        var topO = yO ? yO.scrollTop : 0;
        var curK = document.getElementById('hotspot-dash-kpis');
        var curV = document.getElementById('hotspot-dash-vivos');
        var curO = document.getElementById('hotspot-dash-offline');
        if (curK && newKpis) curK.replaceWith(newKpis);
        if (curV) curV.replaceWith(newVivos);
        if (curO) curO.replaceWith(newOff);
        vivos = document.getElementById('hotspot-dash-vivos');
        pulse = document.getElementById('hotspot-dash-pulse');
        var nv = scrollWrap('hotspot-dash-vivos-scroll');
        var no = scrollWrap('hotspot-dash-offline-scroll');
        if (nv) nv.scrollTop = topV;
        if (no) no.scrollTop = topO;
        remain = REFRESH_S;
        filtrarFilas();
        paintPulse();
        return true;
    }

    function tryRefresh() {
        if (leaving || dead || isPaused()) return;
        fetch(pingUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        }).then(function (r) {
            var json = (r.headers.get('content-type') || '').indexOf('json') !== -1;
            if (!r.ok || r.redirected || !json) {
                showSessionDead();
                return null;
            }
            return fetch(window.location.pathname + window.location.search, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
        }).then(function (r) {
            if (!r) return null;
            if (!r.ok || r.redirected) {
                showSessionDead();
                return null;
            }
            return r.text();
        }).then(function (html) {
            if (!html) return;
            var doc = new DOMParser().parseFromString(html, 'text/html');
            if (!applyWatch(doc)) {
                showSessionDead();
            }
        }).catch(function () {
            showSessionDead();
        });
    }

    function filterRouters() {
        if (!select) return;
        var term = filter ? String(filter.value || '').trim().toLowerCase() : '';
        var groups = select.querySelectorAll('optgroup');
        groups.forEach(function (g) {
            var any = false;
            g.querySelectorAll('option').forEach(function (opt) {
                var hay = [
                    opt.getAttribute('data-nombre') || '',
                    opt.getAttribute('data-ip') || '',
                    opt.getAttribute('data-nodo') || '',
                    opt.textContent || ''
                ].join(' ').toLowerCase();
                var keep = opt.value === loaded || opt.selected;
                var show = term === '' || hay.indexOf(term) !== -1 || keep;
                opt.hidden = !show;
                if (show) any = true;
            });
            g.hidden = !any;
        });
    }

    if (filter) {
        filter.addEventListener('input', function () {
            filterRouters();
            paintPulse();
        });
        filter.addEventListener('focus', paintPulse);
        filter.addEventListener('blur', paintPulse);
        filter.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (isDirty()) submitRouter();
            else if (select) select.focus();
        });
    }
    if (find) {
        find.addEventListener('input', filtrarFilas);
        find.addEventListener('focus', paintPulse);
        find.addEventListener('blur', paintPulse);
    }
    if (select) {
        select.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            if (!isDirty()) return;
            e.preventDefault();
            submitRouter();
        });
        select.addEventListener('change', paintPulse);
        select.addEventListener('focus', paintPulse);
        select.addEventListener('blur', paintPulse);
    }
    if (form) form.addEventListener('submit', showSkel);
    if (watch) {
        watch.addEventListener('click', function (e) {
            var copiar = e.target.closest('[data-copiar]');
            if (copiar) {
                var mac = copiar.getAttribute('data-copiar') || '';
                var icono = copiar.innerHTML;
                var titulo = copiar.getAttribute('aria-label') || 'Copiar MAC';
                var listo = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                var avisar = function (ok) {
                    copiar.innerHTML = ok ? listo : icono;
                    copiar.setAttribute('aria-label', ok ? 'MAC copiada' : 'No se pudo copiar');
                    setTimeout(function () {
                        copiar.innerHTML = icono;
                        copiar.setAttribute('aria-label', titulo);
                    }, 1200);
                };
                var fallback = function () {
                    var input = document.createElement('textarea');
                    input.value = mac;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.left = '-9999px';
                    document.body.appendChild(input);
                    input.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
                    document.body.removeChild(input);
                    avisar(ok);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(mac).then(function () { avisar(true); }).catch(fallback);
                } else {
                    fallback();
                }
                return;
            }
            var cuota = e.target.closest('[data-cuota-filtro]');
            if (cuota) {
                cuotaFiltro = cuota.getAttribute('data-cuota-filtro') || 'todos';
                filtrarFilas();
                return;
            }
            var a = e.target.closest('[data-dash-refresh]');
            if (!a) return;
            showSkel();
        });
    }

    paintPulse();
    setInterval(paintPulse, 1000);
    setInterval(function () {
        if (isPaused()) {
            paintPulse();
            return;
        }
        remain -= 1;
        if (remain <= 0) {
            remain = REFRESH_S;
            tryRefresh();
            return;
        }
        paintPulse();
    }, 1000);
})();
</script>
@endpush
