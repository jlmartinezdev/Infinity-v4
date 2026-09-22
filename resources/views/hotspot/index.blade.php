@extends('layouts.app')

@section('title', 'Usuarios Hotspot')

@php
    $estados = \App\Models\Servicio::estadosDisponibles();
    $maxSlots = \App\Models\ServicioHotspot::MAX_POR_CLIENTE;
    $buscar = trim((string) request('buscar', ''));
    $hayFiltro = $buscar !== '';
    $resaltar = fn (?string $texto): string => \App\Support\SearchHighlight::html($texto, $buscar);
    $estadoBadge = [
        'A' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'S' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'C' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'X' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
        'P' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
    ];
    $seleccionado = $seleccionado ?? null;
    $esRecientes = $esRecientes ?? ($buscar === '');
    $puedeEditar = (bool) auth()->user()?->tienePermiso('servicios.editar');
    $seleccionadoId = $seleccionado?->getKey();
    $consumo = $consumo ?? [];
    $slotPayload = function ($sh, $cl, $nombre) use ($estados, $consumo) {
        $codigo = $sh->servicio?->estado;
        $synced = $sh->last_synced
            ? $sh->last_synced->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : 'Nunca';
        $usados = (int) ($consumo[(string) $sh->username] ?? 0);
        $cuotaGb = $sh->hotspotPerfil?->cuota_gb;
        $vista = \App\Services\RadiusHotspotSyncService::consumoVista($usados, $cuotaGb);

        return [
            'id' => $sh->getKey(),
            'clienteId' => (int) $cl->cliente_id,
            'username' => (string) $sh->username,
            'pin' => (string) $sh->password,
            'cliente' => $nombre,
            'cedula' => (string) ($cl->cedula ?? ''),
            'slot' => (int) $sh->slot_numero,
            'estado' => (string) ($codigo ?? ''),
            'estadoLabel' => $codigo ? ($estados[$codigo] ?? $codigo) : 'Sin servicio',
            'servicioId' => $sh->servicio_id,
            'router' => $sh->router?->nombre ?: 'Sin router',
            'perfil' => $sh->hotspotPerfil?->nombre ?: 'default',
            'consumo' => $vista['etiqueta'],
            'tieneCuota' => $vista['tiene_cuota'],
            'cuotaPct' => $vista['porcentaje'],
            'centroNum' => $vista['centro_num'],
            'centroUnit' => $vista['centro_unit'],
            'synced' => $synced,
            'syncUrl' => route('hotspot.sync', $sh),
            'editUrl' => route('hotspot.clientes.edit', $cl),
            'vaciarUrl' => route('hotspot.clientes.destroy', [$cl, $sh]),
            'suspenderUrl' => $sh->servicio_id ? route('servicios.suspender', $sh->servicio_id) : '',
            'activarUrl' => $sh->servicio_id ? route('servicios.activar', $sh->servicio_id) : '',
        ];
    };
@endphp

@push('styles')
<style>
    mark.search-mark {
        background-color: rgba(147, 51, 234, 0.22);
        color: inherit;
        padding: 0 0.12em;
    }
    .dark mark.search-mark {
        background-color: rgba(192, 132, 252, 0.28);
        color: inherit;
    }
    #hotspot-buscar::selection,
    .hotspot-slot::selection {
        background: rgba(147, 51, 234, 0.28);
    }
    .hotspot-slot,
    .hotspot-add,
    #insp-sync-btn,
    #insp-close,
    #insp-pin-btn,
    #insp-edit-link,
    #hotspot-buscar,
    #hotspot-buscar-btn,
    #insp-vaciar-btn,
    #insp-pausar-btn,
    #insp-reanudar-btn,
    #insp-confirm-yes,
    #insp-confirm-no {
        min-height: 44px;
    }
    #insp-pin-btn {
        min-width: 44px;
    }
    #insp-pin-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #insp-edit-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-width: 44px;
        padding: 0 0.5rem;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        white-space: nowrap;
    }
    .hotspot-slot-card .hotspot-slot {
        display: block;
        width: 100%;
        padding-bottom: 0.25rem;
    }
    .hotspot-slot-main {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .hotspot-slot-copy {
        min-width: 0;
        flex: 1;
    }
    @include('hotspot._cuota-ring-styles')
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota-track {
        stroke: rgba(126, 34, 206, 0.38);
    }
    html.dark .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota-track {
        stroke: rgba(243, 232, 255, 0.42);
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota-fill {
        stroke: #7e22ce;
    }
    html.dark .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota-fill {
        stroke: #e9d5ff;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota.is-warn .hotspot-cuota-fill {
        stroke: #fbbf24;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota.is-danger .hotspot-cuota-fill {
        stroke: #fca5a5;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota-center {
        color: inherit;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-cuota-unit {
        color: inherit;
        opacity: 0.8;
    }
    .hotspot-slot-plan {
        display: block;
        margin-top: 0.15rem;
        font-size: 0.75rem;
        line-height: 1.25;
        color: #6b7280;
    }
    html.dark .hotspot-slot-plan {
        color: #9ca3af;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-slot-plan {
        color: inherit;
        opacity: 0.88;
    }
    .hotspot-slot-meta {
        display: flex;
        align-items: center;
        min-height: 44px;
        margin-top: 0.25rem;
    }
    #insp-risk {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        margin-top: 0.75rem;
    }
    #insp-risk button,
    #insp-confirm-no,
    #insp-confirm-yes {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
    }
    #insp-vaciar-btn,
    #insp-pausar-btn,
    #insp-reanudar-btn,
    #insp-confirm-no {
        background: transparent;
        border: 1px solid transparent;
    }
    #insp-vaciar-btn {
        color: #dc2626;
    }
    #insp-vaciar-btn:hover {
        background: #fef2f2;
        border-color: #fecaca;
    }
    html.dark #insp-vaciar-btn:hover {
        background: rgba(127, 29, 29, 0.3);
        border-color: transparent;
        color: #fca5a5;
    }
    #insp-pausar-btn {
        color: #d97706;
    }
    #insp-pausar-btn:hover {
        background: #fffbeb;
        border-color: #fde68a;
    }
    html.dark #insp-pausar-btn:hover {
        background: rgba(120, 53, 15, 0.3);
        border-color: transparent;
        color: #fcd34d;
    }
    #insp-reanudar-btn {
        color: #15803d;
    }
    #insp-reanudar-btn:hover {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    html.dark #insp-reanudar-btn:hover {
        background: rgba(20, 83, 45, 0.3);
        border-color: transparent;
        color: #86efac;
    }
    #insp-confirm {
        margin-top: 0.75rem;
        padding: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #f9fafb;
    }
    html.dark #insp-confirm {
        border-color: #4b5563;
        background: #111827;
    }
    #insp-confirm-copy {
        margin: 0 0 0.75rem;
        font-size: 0.875rem;
        color: #111827;
    }
    html.dark #insp-confirm-copy {
        color: #f3f4f6;
    }
    #insp-confirm-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    #insp-confirm-no {
        border: 1px solid #e5e7eb;
        color: #111827;
        background: #fff;
    }
    html.dark #insp-confirm-no {
        border-color: #4b5563;
        color: #f3f4f6;
        background: #1f2937;
    }
    #insp-confirm-yes {
        border: 1px solid #dc2626;
        background: #dc2626;
        color: #fff;
    }
    #insp-confirm-yes.is-pause {
        border-color: #d97706;
        background: #d97706;
    }
    #insp-confirm-yes.is-resume {
        border-color: #16a34a;
        background: #16a34a;
    }
    #insp-risk button:focus-visible,
    #insp-confirm-no:focus-visible,
    #insp-confirm-yes:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px #a855f7;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) {
        background-color: rgba(88, 28, 135, 0.2);
        border-color: transparent;
        color: #7e22ce;
    }
    html.dark .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) {
        color: #c084fc;
    }
    .hotspot-slot-card:has(.hotspot-slot[aria-pressed="true"]) .hotspot-slot-user {
        color: inherit;
    }
    article.is-focused {
        border-color: #a855f7;
    }
    html.dark article.is-focused {
        border-color: #c084fc;
    }
    #hotspot-hint-narrow {
        display: block;
    }
    #insp-close {
        display: inline-flex;
    }
    @media (min-width: 1024px) {
        .hotspot-work {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 20rem;
            gap: 1.5rem;
            align-items: start;
        }
        #hotspot-inspector {
            position: sticky;
            top: 5rem;
            margin-top: 0;
        }
        #hotspot-hint-narrow {
            display: none;
        }
        #insp-close {
            display: none;
        }
    }
    @media (max-width: 1023px) {
        #hotspot-inspector:not(.is-docked) {
            display: none;
        }
        #hotspot-inspector.is-docked {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 35;
            margin: 0;
            border-radius: 0.5rem 0.5rem 0 0;
            border-bottom: 0;
            max-height: min(52vh, 28rem);
            padding-bottom: max(1rem, env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
        }
        #hotspot-inspector.is-docked #insp-detail {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1 1 auto;
        }
        #hotspot-inspector.is-docked #insp-meta {
            overflow-y: auto;
            min-height: 0;
            flex: 1 1 auto;
        }
        #hotspot-inspector.is-docked .insp-actions {
            flex-shrink: 0;
        }
        .hotspot-work.has-dock {
            padding-bottom: min(52vh, 28rem);
        }
    }
</style>
@endpush

@section('content')
<div class="max-w-[1600px] mx-auto">
    <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Usuarios Hotspot</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Hasta {{ $maxSlots }} usuarios por cliente. Usuario = documento (−2 y −3 en el 2.º y 3.er). Clave = PIN de {{ \App\Models\ServicioHotspot::PIN_DIGITOS }} dígitos.</p>
        </div>
        <nav class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm shrink-0" aria-label="Otras vistas hotspot">
            <a href="{{ route('hotspot.radius') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 underline-offset-2 hover:underline">Usuarios RADIUS</a>
            <a href="{{ route('hotspot.mapa') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 underline-offset-2 hover:underline">Mapa de puntos</a>
            <a href="{{ route('hotspot.dashboard') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 underline-offset-2 hover:underline">Sesiones hotspot</a>
            @if(auth()->user()?->tienePermiso('servicios-hotspot-perfiles.ver') || auth()->user()?->tienePermiso('servicios.ver'))
                <a href="{{ route('hotspot.perfiles.index') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 underline-offset-2 hover:underline">Perfiles</a>
            @endif
        </nav>
    </div>

    <form id="hotspot-find-form" method="GET" action="{{ route('hotspot.index') }}" role="search" class="sticky top-16 z-30 -mx-4 px-4 py-3 mb-6 bg-gray-50 dark:bg-gray-900 sm:mx-0 sm:px-0">
        <label for="hotspot-buscar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar usuario o cliente</label>
        <div class="flex flex-wrap gap-2">
            <div id="hotspot-combobox" class="relative flex-1 min-w-[16rem]">
                <input type="text" name="buscar" id="hotspot-buscar" value="{{ $buscar }}"
                    placeholder="Nombre, cédula o usuario…"
                    autocomplete="off"
                    role="combobox"
                    aria-expanded="false"
                    aria-autocomplete="list"
                    aria-controls="hotspot-sugerencias"
                    aria-haspopup="listbox"
                    aria-describedby="hotspot-find-help hotspot-hint-narrow"
                    class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-400/20 focus:outline-none">
                <div id="hotspot-sugerencias" role="listbox" hidden
                    class="absolute z-20 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg max-h-60 overflow-y-auto"></div>
                <p id="hotspot-buscar-status" class="sr-only" aria-live="polite"></p>
            </div>
            <button type="submit" id="hotspot-buscar-btn" class="inline-flex items-center px-4 py-2.5 min-h-11 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-400/20">Buscar</button>
            @if($hayFiltro)
                <a href="{{ route('hotspot.index') }}" class="inline-flex items-center px-4 py-2.5 min-h-11 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-400/20">Limpiar</a>
            @endif
        </div>
        <p id="hotspot-find-help" class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Enter o Buscar filtra el tablero. Flechas eligen un locker.</p>
        <p id="hotspot-hint-narrow" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Elegí un slot. Tocá el ocupado para confirmar y sincronizar.</p>
    </form>

    <div class="hotspot-work{{ $seleccionado ? ' has-dock' : '' }}">
        <div class="space-y-3 min-w-0">
            @if($esRecientes && $clientes->isNotEmpty())
                
            @endif
            @forelse($clientes as $cl)
                @php
                    $porSlot = $cl->servicioHotspots->keyBy(fn ($h) => (int) $h->slot_numero);
                    $nombre = trim($cl->nombre.' '.$cl->apellido);
                @endphp
                @php $articleFocused = $seleccionadoId && $cl->servicioHotspots->contains(fn ($h) => (int) $h->getKey() === (int) $seleccionadoId); @endphp
                <article class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4{{ $articleFocused ? ' is-focused' : '' }}" data-cliente-id="{{ $cl->cliente_id }}">
                    <div class="flex flex-wrap items-baseline gap-2 mb-3 min-w-0">
                        @if($cl->servicio_hotspots_count >= $maxSlots)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">{{ $cl->servicio_hotspots_count }} / {{ $maxSlots }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200">{{ $cl->servicio_hotspots_count }} / {{ $maxSlots }}</span>
                        @endif
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 truncate">{!! $resaltar($nombre) !!}</h2>
                        @if($cl->cedula)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{!! $resaltar($cl->cedula) !!}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        @for($i = 1; $i <= $maxSlots; $i++)
                            @php $sh = $porSlot->get($i); @endphp
                            @if($sh)
                                @php
                                    $codigo = $sh->servicio?->estado;
                                    $estadoLabel = $codigo ? ($estados[$codigo] ?? $codigo) : 'Sin servicio';
                                    $payload = $slotPayload($sh, $cl, $nombre);
                                    $activo = (int) $seleccionadoId === (int) $sh->getKey();
                                @endphp
                                <div class="hotspot-slot-card relative rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40">
                                    <button type="button"
                                        class="hotspot-slot w-full text-left rounded-lg px-3 py-2.5 min-h-11 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20"
                                        data-slot="{{ json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                                        aria-pressed="{{ $activo ? 'true' : 'false' }}"
                                        id="hotspot-slot-{{ $sh->getKey() }}">
                                        <span class="hotspot-slot-main">
                                            <span class="hotspot-slot-copy">
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">Slot {{ $i }}</span>
                                                <span class="hotspot-slot-user mt-0.5 block text-sm font-medium font-mono break-all text-gray-900 dark:text-gray-100">{!! $resaltar($sh->username) !!}</span>
                                                <span class="hotspot-slot-plan">{{ $payload['perfil'] }}@unless($payload['tieneCuota']) · {{ $payload['consumo'] }}@endunless</span>
                                                @if($payload['tieneCuota'])
                                                    <span class="sr-only">{{ $payload['consumo'] }}</span>
                                                @endif
                                            </span>
                                            @if($payload['tieneCuota'])
                                                @include('hotspot._cuota-ring', [
                                                    'pct' => $payload['cuotaPct'],
                                                    'num' => $payload['centroNum'],
                                                    'unit' => $payload['centroUnit'],
                                                    'size' => 'slot',
                                                ])
                                            @endif
                                        </span>
                                        <span class="hotspot-slot-meta">
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $estadoBadge[$codigo] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $estadoLabel }}</span>
                                        </span>
                                    </button>
                                </div>
                            @else
                                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 px-3 py-2.5 min-h-11 flex items-center justify-between gap-2">
                                    <span class="text-xs text-gray-400">Slot {{ $i }}</span>
                                    <a href="{{ route('hotspot.clientes.edit', ['cliente' => $cl, 'slot' => $i]) }}#form-hotspot"
                                        class="hotspot-add inline-flex items-center justify-center w-11 h-11 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20"
                                        title="Crear usuario en el slot {{ $i }}"
                                        aria-label="Crear usuario en el slot {{ $i }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                                    </a>
                                </div>
                            @endif
                        @endfor
                    </div>
                    <p class="hotspot-pick-hint mt-2 text-sm text-gray-600 dark:text-gray-300" hidden></p>
                </article>
            @empty
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                    @if($esRecientes)
                        <p>No hay recientes todavía.</p>
                        <p class="mt-2 text-sm">Buscá un cliente para abrir su locker y sincronizar.</p>
                    @else
                        <p>Ningún locker coincide con “{{ $buscar }}”.</p>
                        <p class="mt-2 text-sm">Probá con el documento o el username, o <a href="{{ route('hotspot.index') }}" class="underline underline-offset-2">limpiá el filtro</a>.</p>
                    @endif
                </div>
            @endforelse

            @if($clientes->hasPages())
                <div class="pt-3">
                    {{ $clientes->links() }}
                </div>
            @endif
        </div>

        <aside id="hotspot-inspector" class="mt-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4{{ $seleccionado ? ' is-docked' : '' }}">
            <div id="insp-empty" @if($seleccionado) hidden @endif>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Elegí un slot</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Buscá el cliente y tocá el slot para confirmar estado y sincronizar. El PIN no se muestra en el listado.</p>
            </div>
            <div id="insp-detail" @if(! $seleccionado) hidden @endif>
                @php
                    $inspCliente = $seleccionado?->cliente;
                    $inspNombre = $inspCliente ? trim($inspCliente->nombre.' '.$inspCliente->apellido) : '';
                    $inspCodigo = $seleccionado?->servicio?->estado;
                    $inspEstado = $inspCodigo ? ($estados[$inspCodigo] ?? $inspCodigo) : 'Sin servicio';
                    $inspSynced = $seleccionado?->last_synced
                        ? $seleccionado->last_synced->timezone(config('app.timezone'))->format('d/m/Y H:i')
                        : 'Nunca';
                    $inspClienteLine = trim($inspNombre.($inspCliente?->cedula ? ' · '.$inspCliente->cedula : '').($seleccionado?->slot_numero ? ' · Slot '.$seleccionado->slot_numero : ''));
                    $inspVista = $seleccionado
                        ? \App\Services\RadiusHotspotSyncService::consumoVista(
                            (int) ($consumo[(string) $seleccionado->username] ?? 0),
                            $seleccionado->hotspotPerfil?->cuota_gb
                        )
                        : null;
                @endphp
                <div class="flex items-start justify-between gap-2">
                    <h2 id="insp-username" class="text-base font-semibold font-mono text-gray-900 dark:text-gray-100 break-all min-w-0">{{ $seleccionado?->username }}</h2>
                    <div class="flex items-center shrink-0">
                        <a id="insp-edit-link" href="{{ $seleccionado && $inspCliente ? route('hotspot.clientes.edit', $inspCliente) : '#' }}" class="rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20" title="Editar slots del cliente" aria-label="Editar slots del cliente">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            <span>Editar</span>
                        </a>
                        <button type="button" id="insp-close" class="shrink-0 items-center justify-center px-3 min-h-11 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20">Cerrar</button>
                    </div>
                </div>
                <p id="insp-cliente" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $inspClienteLine }}</p>
                <div id="insp-result" hidden></div>
                <dl id="insp-meta" class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Servicio</dt>
                        <dd class="text-gray-900 dark:text-gray-100">
                            <span id="insp-servicio">{{ $seleccionado?->servicio_id ? '#'.$seleccionado->servicio_id : '—' }}</span>
                            · <span id="insp-estado">{{ $inspEstado }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Router</dt>
                        <dd id="insp-router" class="text-gray-900 dark:text-gray-100">{{ $seleccionado?->router?->nombre ?: 'Sin router' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Perfil</dt>
                        <dd id="insp-perfil" class="text-gray-900 dark:text-gray-100">{{ $seleccionado?->hotspotPerfil?->nombre ?: 'default' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cuota</dt>
                        <dd class="flex items-center gap-3 text-gray-900 dark:text-gray-100">
                            <span id="insp-cuota-ring" @if(! ($inspVista['tiene_cuota'] ?? false)) hidden @endif>
                                @include('hotspot._cuota-ring', [
                                    'pct' => $inspVista['porcentaje'] ?? 0,
                                    'num' => $inspVista['centro_num'] ?? '0',
                                    'unit' => $inspVista['centro_unit'] ?? 'MB',
                                    'size' => 'insp',
                                ])
                            </span>
                            <span id="insp-consumo">{{ $inspVista['etiqueta'] ?? '—' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Última sync</dt>
                        <dd id="insp-synced" class="text-gray-900 dark:text-gray-100">{{ $seleccionado ? $inspSynced : '—' }}</dd>
                    </div>
                </dl>
                <div class="insp-actions mt-3">
                    <div class="flex items-center gap-1 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">PIN</span>
                        <span id="insp-pin" class="font-mono text-gray-900 dark:text-gray-100">{{ \App\Models\ServicioHotspot::pinOculto($seleccionado?->password) }}</span>
                        <button type="button" id="insp-pin-btn" class="inline-flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20" title="Mostrar PIN" aria-label="Mostrar PIN" aria-pressed="false">
                            <svg id="insp-pin-icon-show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg id="insp-pin-icon-hide" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" hidden><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <form id="insp-sync-form" action="{{ $seleccionado ? route('hotspot.sync', $seleccionado) : '' }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="from_index" value="1">
                        <input type="hidden" name="buscar" id="insp-buscar" value="{{ $buscar }}">
                        <input type="hidden" name="page" value="{{ request('page') }}">
                        <p id="insp-sync-copy" class="mb-2 text-sm text-gray-600 dark:text-gray-300" aria-live="polite"></p>
                        <button type="submit" id="insp-sync-btn"
                            class="w-full px-4 py-2.5 min-h-11 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-800 disabled:opacity-60">
                            Sincronizar
                        </button>
                    </form>
                    @if($puedeEditar)
                        <div id="insp-risk" @if(! $seleccionado) hidden @endif>
                            <button type="button" id="insp-vaciar-btn">Vaciar slot</button>
                            <button type="button" id="insp-pausar-btn" @if($inspCodigo !== 'A' || ! $seleccionado?->servicio_id) hidden @endif>Pausar Wi‑Fi</button>
                            <button type="button" id="insp-reanudar-btn" @if($inspCodigo !== 'S' || ! $seleccionado?->servicio_id) hidden @endif>Reanudar Wi‑Fi</button>
                        </div>
                        <div id="insp-confirm" hidden role="region" aria-live="polite">
                            <p id="insp-confirm-copy"></p>
                            <div id="insp-confirm-actions">
                                <button type="button" id="insp-confirm-no">Seguir con el slot</button>
                                <button type="button" id="insp-confirm-yes">Quitar del locker</button>
                            </div>
                        </div>
                        <form id="insp-vaciar-form" action="{{ $seleccionado && $inspCliente ? route('hotspot.clientes.destroy', [$inspCliente, $seleccionado]) : '' }}" method="POST" hidden>
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="from_index" value="1">
                            <input type="hidden" name="buscar" id="insp-vaciar-buscar" value="{{ $buscar }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                        </form>
                        <form id="insp-pausar-form" action="{{ $seleccionado?->servicio_id ? route('servicios.suspender', $seleccionado->servicio_id) : '' }}" method="POST" hidden>
                            @csrf
                        </form>
                        <form id="insp-reanudar-form" action="{{ $seleccionado?->servicio_id ? route('servicios.activar', $seleccionado->servicio_id) : '' }}" method="POST" hidden>
                            @csrf
                        </form>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var buscar = document.getElementById('hotspot-buscar');
    var listbox = document.getElementById('hotspot-sugerencias');
    var status = document.getElementById('hotspot-buscar-status');
    var combobox = document.getElementById('hotspot-combobox');
    var findForm = document.getElementById('hotspot-find-form');
    var urlBuscar = @json(route('hotspot.buscar'));
    var urlIndex = @json(route('hotspot.index'));
    var urlTocar = @json(route('hotspot.tocar'));
    var radiusEnabled = @json((bool) config('radius.enabled'));
    var alsoMikrotik = @json((bool) config('radius.also_sync_mikrotik'));
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var debounceTimer = null;
    var abortCtrl = null;
    var activeIndex = -1;
    var items = [];
    var currentPin = @json($seleccionado?->password ?: '');
    var pinVisible = false;
    var pinTimer = null;
    var PIN_HIDE_MS = 8000;

    function pinMask(pin) {
        var n = pin && pin.length ? pin.length : {{ \App\Models\ServicioHotspot::PIN_DIGITOS }};
        return '•'.repeat(n);
    }

    var emptyEl = document.getElementById('insp-empty');
    var detailEl = document.getElementById('insp-detail');
    var pinEl = document.getElementById('insp-pin');
    var pinBtn = document.getElementById('insp-pin-btn');
    var closeBtn = document.getElementById('insp-close');
    var syncForm = document.getElementById('insp-sync-form');
    var syncBtn = document.getElementById('insp-sync-btn');
    var syncCopy = document.getElementById('insp-sync-copy');
    var inspector = document.getElementById('hotspot-inspector');
    var workEl = document.querySelector('.hotspot-work');
    var resultEl = document.getElementById('insp-result');
    var currentEstado = @json($seleccionado?->servicio?->estado ?: '');
    var currentEstadoLabel = @json($inspEstado ?? 'Sin servicio');
    var currentDestino = '';
    var currentPayload = null;
    var confirmKind = null;
    var puedeEditar = @json($puedeEditar);
    var riskEl = document.getElementById('insp-risk');
    var confirmEl = document.getElementById('insp-confirm');
    var confirmCopy = document.getElementById('insp-confirm-copy');
    var confirmYes = document.getElementById('insp-confirm-yes');
    var confirmNo = document.getElementById('insp-confirm-no');
    var vaciarBtn = document.getElementById('insp-vaciar-btn');
    var pausarBtn = document.getElementById('insp-pausar-btn');
    var reanudarBtn = document.getElementById('insp-reanudar-btn');
    var vaciarForm = document.getElementById('insp-vaciar-form');
    var pausarForm = document.getElementById('insp-pausar-form');
    var reanudarForm = document.getElementById('insp-reanudar-form');
    var vaciarBuscar = document.getElementById('insp-vaciar-buscar');

    function setStatus(text) {
        if (status) status.textContent = text || '';
    }

    function clienteEtiqueta(c) {
        var etiqueta = (c && c.etiqueta) ? String(c.etiqueta).trim() : '';
        if (!etiqueta) {
            etiqueta = (String((c && c.nombre) || '') + ' ' + String((c && c.apellido) || '')).trim();
        }
        if (!etiqueta || etiqueta.toLowerCase() === 'null') {
            etiqueta = String((c && c.cedula) || '') || ('#' + ((c && c.cliente_id) || ''));
        }
        return etiqueta;
    }

    function filtrarTablero() {
        var q = buscar ? buscar.value.trim() : '';
        closeList();
        window.location = q === '' ? urlIndex : (urlIndex + '?buscar=' + encodeURIComponent(q));
    }

    function hideConfirm() {
        confirmKind = null;
        if (confirmEl) confirmEl.hidden = true;
        if (riskEl && currentPayload) riskEl.hidden = false;
    }

    function showConfirm(kind) {
        if (!currentPayload || !confirmEl || !confirmCopy || !confirmYes) return;
        var user = currentPayload.username || 'este usuario';
        confirmKind = kind;
        confirmYes.classList.remove('is-pause', 'is-resume');
        if (kind === 'vaciar') {
            confirmCopy.textContent = 'Esto quita a ' + user + ' del locker. El slot queda libre. No se puede deshacer desde acá.';
            confirmYes.textContent = 'Quitar del locker';
        } else if (kind === 'pausar') {
            confirmCopy.textContent = 'Esto corta el Wi‑Fi de ' + user + '. El usuario sigue en el locker.';
            confirmYes.textContent = 'Cortar Wi‑Fi';
            confirmYes.classList.add('is-pause');
        } else {
            confirmCopy.textContent = 'Esto reactiva el Wi‑Fi de ' + user + '.';
            confirmYes.textContent = 'Reanudar Wi‑Fi';
            confirmYes.classList.add('is-resume');
        }
        if (riskEl) riskEl.hidden = true;
        confirmEl.hidden = false;
        confirmYes.focus();
    }

    function bindRisk(payload) {
        currentPayload = payload || null;
        hideConfirm();
        if (!puedeEditar || !riskEl) return;
        if (!payload) {
            riskEl.hidden = true;
            return;
        }
        riskEl.hidden = false;
        if (vaciarForm) vaciarForm.setAttribute('action', payload.vaciarUrl || '');
        if (pausarForm) pausarForm.setAttribute('action', payload.suspenderUrl || '');
        if (reanudarForm) reanudarForm.setAttribute('action', payload.activarUrl || '');
        if (vaciarBuscar && buscar) vaciarBuscar.value = buscar.value.trim();
        if (vaciarBtn) vaciarBtn.hidden = !(payload.vaciarUrl);
        if (pausarBtn) pausarBtn.hidden = !(payload.suspenderUrl && payload.estado === 'A');
        if (reanudarBtn) reanudarBtn.hidden = !(payload.activarUrl && payload.estado === 'S');
    }

    function tocarCliente(id) {
        id = parseInt(id, 10);
        if (!id || !urlTocar) return;
        fetch(urlTocar, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ cliente_id: id })
        }).catch(function () {});
    }

    function syncDestino(payload) {
        var user = payload.username || 'este usuario';
        var router = payload.router && payload.router !== 'Sin router' ? payload.router : '';
        var partes = [];
        if (radiusEnabled) partes.push('RADIUS');
        if ((!radiusEnabled || alsoMikrotik) && router) partes.push(router);
        if (!partes.length) {
            return { ok: false, text: 'No hay RADIUS ni router para ' + user + '.', phrase: '' };
        }
        return { ok: true, text: 'Vas a sincronizar ' + user + ' en ' + partes.join(' y ') + '.', phrase: 'en ' + partes.join(' y ') };
    }

    function updateSyncAction(payload) {
        currentEstado = payload.estado || '';
        currentEstadoLabel = payload.estadoLabel || 'Sin servicio';
        var dest = syncDestino(payload);
        currentDestino = dest.phrase;
        var activo = currentEstado === 'A';
        var cortable = currentEstado === 'S' || currentEstado === 'C';
        var can = dest.ok && (activo || cortable);
        if (syncCopy) {
            if (!dest.ok) {
                syncCopy.textContent = dest.text;
            } else if (!activo && !cortable) {
                syncCopy.textContent = 'El servicio está ' + currentEstadoLabel + '. Reactivalo antes de sincronizar.';
            } else if (cortable) {
                syncCopy.textContent = dest.text + ' El servicio está ' + currentEstadoLabel + '.';
            } else {
                syncCopy.textContent = dest.text;
            }
        }
        if (syncBtn) {
            syncBtn.disabled = !can;
            if (can) syncBtn.removeAttribute('title');
            else syncBtn.setAttribute('title', syncCopy ? syncCopy.textContent : '');
        }
    }

    function setDocked(on) {
        if (inspector) inspector.classList.toggle('is-docked', !!on);
        if (workEl) workEl.classList.toggle('has-dock', !!on);
    }

    function clearPickHints() {
        document.querySelectorAll('.hotspot-pick-hint').forEach(function(el) {
            el.hidden = true;
            el.textContent = '';
        });
    }

    function setPickHint(article, text) {
        clearPickHints();
        if (!article || !text) return;
        var hint = article.querySelector('.hotspot-pick-hint');
        if (!hint) return;
        hint.textContent = text;
        hint.hidden = false;
    }

    function focusArticle(article) {
        document.querySelectorAll('article.is-focused').forEach(function(el) {
            el.classList.remove('is-focused');
        });
        if (article) article.classList.add('is-focused');
    }

    function placeFlash() {
        if (!resultEl || !detailEl || detailEl.hidden) return;
        var flash = document.querySelector('[data-app-flash]');
        if (!flash) return;
        flash.classList.remove('mb-6');
        flash.classList.add('mt-3');
        resultEl.hidden = false;
        resultEl.appendChild(flash);
    }

    function clearInspector(opts) {
        opts = opts || {};
        var pressed = document.querySelector('.hotspot-slot[aria-pressed="true"]');
        emptyEl.hidden = false;
        detailEl.hidden = true;
        setDocked(false);
        document.querySelectorAll('.hotspot-slot').forEach(function(el) {
            el.setAttribute('aria-pressed', 'false');
        });
        currentPin = '';
        setPinVisible(false);
        bindRisk(null);
        if (resultEl) {
            resultEl.hidden = true;
            resultEl.innerHTML = '';
        }
        var url = new URL(window.location.href);
        url.searchParams.delete('hotspot');
        history.replaceState({}, '', url);
        if (opts.focusSlot && pressed) pressed.focus();
    }

    function closeList() {
        listbox.hidden = true;
        buscar.setAttribute('aria-expanded', 'false');
        buscar.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function optionId(i) {
        return 'hotspot-opt-' + i;
    }

    function renderList() {
        listbox.innerHTML = '';
        if (!items.length) {
            var empty = document.createElement('div');
            empty.className = 'px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400';
            empty.textContent = 'Ningún locker coincide.';
            listbox.appendChild(empty);
            listbox.hidden = false;
            buscar.setAttribute('aria-expanded', 'true');
            setStatus('Ningún locker coincide. Enter filtra el tablero.');
            return;
        }
        items.forEach(function(c, i) {
            var opt = document.createElement('div');
            opt.setAttribute('role', 'option');
            opt.id = optionId(i);
            opt.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
            opt.className = 'w-full text-left px-4 py-2.5 min-h-11 text-sm text-gray-900 dark:text-gray-100 cursor-pointer' + (i === activeIndex ? ' bg-purple-900/20' : '');
            var label = clienteEtiqueta(c);
            if (c.cedula) label += ' · ' + c.cedula;
            if (c.slots != null) label += ' · ' + c.slots + '/' + (c.max_slots || 3);
            opt.textContent = label;
            opt.addEventListener('mousedown', function(e) {
                e.preventDefault();
                choose(i);
            });
            listbox.appendChild(opt);
        });
        listbox.hidden = false;
        buscar.setAttribute('aria-expanded', 'true');
        setStatus(items.length + ' resultados. Flechas para elegir, Enter filtra el tablero.');
    }

    function highlight() {
        var opts = listbox.querySelectorAll('[role="option"]');
        opts.forEach(function(opt, i) {
            var on = i === activeIndex;
            opt.setAttribute('aria-selected', on ? 'true' : 'false');
            opt.classList.toggle('bg-purple-900/20', on);
        });
        if (activeIndex >= 0) {
            buscar.setAttribute('aria-activedescendant', optionId(activeIndex));
        } else {
            buscar.removeAttribute('aria-activedescendant');
        }
    }

    function choose(i) {
        var c = items[i];
        if (!c) return;
        closeList();
        var article = document.querySelector('[data-cliente-id="' + c.cliente_id + '"]');
        var nombre = clienteEtiqueta(c);
        if (article) {
            focusArticle(article);
            article.scrollIntoView({ block: 'nearest' });
            var slots = article.querySelectorAll('.hotspot-slot');
            if (slots.length === 1) {
                selectSlot(slots[0], true);
                return;
            }
            clearInspector();
            focusArticle(article);
            tocarCliente(c.cliente_id);
            if (slots.length) {
                setPickHint(article, 'Elegí el slot de ' + nombre + '.');
                setStatus('Elegí el slot de ' + nombre + '.');
            } else {
                setPickHint(article, nombre + ' no tiene usuarios. Usá + para crear.');
                setStatus(nombre + ' no tiene usuarios. Usá + para crear.');
            }
            return;
        }
        var q = c.cedula || nombre;
        window.location = urlIndex + '?buscar=' + encodeURIComponent(q);
    }

    function buscarClientes() {
        var q = buscar.value.trim();
        if (q.length < 2) {
            closeList();
            setStatus('');
            return;
        }
        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();
        buscar.setAttribute('aria-busy', 'true');
        setStatus('Buscando…');
        fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: abortCtrl.signal
        }).then(function(r) {
            if (!r.ok) throw new Error('http');
            return r.json();
        }).then(function(data) {
            items = Array.isArray(data) ? data : [];
            activeIndex = -1;
            renderList();
            highlight();
        }).catch(function(err) {
            if (err && err.name === 'AbortError') return;
            items = [];
            listbox.innerHTML = '';
            var errEl = document.createElement('div');
            errEl.className = 'px-4 py-2.5 text-sm text-red-600 dark:text-red-400';
            errEl.textContent = 'No se pudo buscar. Reintentá.';
            listbox.appendChild(errEl);
            listbox.hidden = false;
            buscar.setAttribute('aria-expanded', 'true');
            setStatus('No se pudo buscar. Reintentá.');
        }).finally(function() {
            buscar.removeAttribute('aria-busy');
        });
    }

    function setPinVisible(on) {
        if (pinTimer) {
            clearTimeout(pinTimer);
            pinTimer = null;
        }
        pinVisible = on;
        if (!pinEl || !pinBtn) return;
        pinEl.textContent = on && currentPin ? currentPin : pinMask(currentPin);
        pinBtn.setAttribute('aria-label', on ? 'Ocultar PIN' : 'Mostrar PIN');
        pinBtn.setAttribute('title', on ? 'Ocultar PIN' : 'Mostrar PIN');
        pinBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        var showIcon = document.getElementById('insp-pin-icon-show');
        var hideIcon = document.getElementById('insp-pin-icon-hide');
        if (showIcon) showIcon.hidden = !!on;
        if (hideIcon) hideIcon.hidden = !on;
        if (on && currentPin) {
            pinTimer = setTimeout(function() {
                setPinVisible(false);
            }, PIN_HIDE_MS);
        }
    }

    function setCuotaRing(payload) {
        var wrap = document.getElementById('insp-cuota-ring');
        if (!wrap) return;
        var show = !!payload.tieneCuota;
        wrap.hidden = !show;
        if (!show) return;
        var ring = wrap.querySelector('.hotspot-cuota');
        var fill = wrap.querySelector('.hotspot-cuota-fill');
        var num = wrap.querySelector('.hotspot-cuota-num');
        var unit = wrap.querySelector('.hotspot-cuota-unit');
        var pct = Math.max(0, Math.min(100, Number(payload.cuotaPct) || 0));
        if (fill) fill.setAttribute('stroke-dasharray', pct + ' ' + (100 - pct));
        if (ring) {
            ring.classList.toggle('is-ok', pct < 70);
            ring.classList.toggle('is-warn', pct >= 70 && pct < 90);
            ring.classList.toggle('is-danger', pct >= 90);
        }
        if (num) num.textContent = payload.centroNum || '0';
        if (unit) unit.textContent = payload.centroUnit || 'MB';
    }

    function fillInspector(payload) {
        currentPin = payload.pin || '';
        emptyEl.hidden = true;
        detailEl.hidden = false;
        clearPickHints();
        document.getElementById('insp-username').textContent = payload.username || '';
        var clienteLine = payload.cliente || '';
        if (payload.cedula) clienteLine += (clienteLine ? ' · ' : '') + payload.cedula;
        if (payload.slot) clienteLine += (clienteLine ? ' · ' : '') + 'Slot ' + payload.slot;
        document.getElementById('insp-cliente').textContent = clienteLine;
        document.getElementById('insp-servicio').textContent = payload.servicioId ? '#' + payload.servicioId : '—';
        document.getElementById('insp-estado').textContent = payload.estadoLabel || '';
        document.getElementById('insp-router').textContent = payload.router || 'Sin router';
        document.getElementById('insp-perfil').textContent = payload.perfil || 'default';
        document.getElementById('insp-consumo').textContent = payload.consumo || '—';
        setCuotaRing(payload);
        document.getElementById('insp-synced').textContent = payload.synced || 'Nunca';
        document.getElementById('insp-edit-link').setAttribute('href', payload.editUrl || '#');
        syncForm.setAttribute('action', payload.syncUrl || '');
        setPinVisible(false);
        setDocked(true);
        updateSyncAction(payload);
        bindRisk(payload);
        tocarCliente(payload.clienteId);
        placeFlash();
        var url = new URL(window.location.href);
        url.searchParams.set('hotspot', payload.id);
        history.replaceState({}, '', url);
        setStatus('Slot ' + (payload.slot || '') + ' listo para sincronizar.');
    }

    function selectSlot(btn, scroll) {
        if (!scroll && btn.getAttribute('aria-pressed') === 'true') {
            clearInspector();
            return;
        }
        document.querySelectorAll('.hotspot-slot').forEach(function(el) {
            el.setAttribute('aria-pressed', el === btn ? 'true' : 'false');
        });
        var article = btn.closest('article');
        focusArticle(article);
        var payload = {};
        try { payload = JSON.parse(btn.getAttribute('data-slot') || '{}'); } catch (e) { return; }
        fillInspector(payload);
        if (scroll) btn.focus();
    }

    document.querySelectorAll('.hotspot-slot').forEach(function(btn) {
        btn.addEventListener('click', function() { selectSlot(btn, false); });
    });

    if (pinBtn) {
        pinBtn.addEventListener('click', function() { setPinVisible(!pinVisible); });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            clearInspector({ focusSlot: true });
        });
    }

    if (syncForm && syncBtn) {
        syncForm.addEventListener('submit', function(e) {
            if (!syncForm.getAttribute('action') || syncBtn.disabled) {
                e.preventDefault();
                return;
            }
            if (currentEstado === 'S' || currentEstado === 'C') {
                var msg = 'El servicio está ' + currentEstadoLabel + '. ¿Sincronizar igual' + (currentDestino ? ' ' + currentDestino : '') + '?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                    return;
                }
            }
            syncBtn.disabled = true;
            syncBtn.textContent = 'Sincronizando…';
        });
    }

    if (buscar) {
        buscar.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(buscarClientes, 250);
        });
        buscar.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!listbox.hidden) {
                    e.preventDefault();
                    closeList();
                }
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!listbox.hidden && activeIndex >= 0 && items.length) {
                    choose(activeIndex);
                    return;
                }
                filtrarTablero();
                return;
            }
            if (listbox.hidden) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(items.length - 1, activeIndex + 1);
                highlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = activeIndex < 0 ? items.length - 1 : Math.max(0, activeIndex - 1);
                highlight();
            }
        });
        document.addEventListener('click', function(e) {
            if (combobox && !combobox.contains(e.target)) closeList();
        });
    }

    if (findForm) {
        findForm.addEventListener('submit', function(e) {
            e.preventDefault();
            filtrarTablero();
        });
    }

    if (vaciarBtn) vaciarBtn.addEventListener('click', function() { showConfirm('vaciar'); });
    if (pausarBtn) pausarBtn.addEventListener('click', function() { showConfirm('pausar'); });
    if (reanudarBtn) reanudarBtn.addEventListener('click', function() { showConfirm('reanudar'); });
    if (confirmNo) confirmNo.addEventListener('click', hideConfirm);
    if (confirmYes) {
        confirmYes.addEventListener('click', function() {
            var form = confirmKind === 'vaciar' ? vaciarForm : (confirmKind === 'pausar' ? pausarForm : reanudarForm);
            if (!form || !form.getAttribute('action')) return;
            confirmYes.disabled = true;
            form.submit();
        });
    }

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) return;
            if (!buscar) return;
            e.preventDefault();
            buscar.focus();
            buscar.select();
            return;
        }
        if (e.key !== 'Escape') return;
        if (listbox && !listbox.hidden) return;
        if (confirmEl && !confirmEl.hidden) {
            e.preventDefault();
            hideConfirm();
            if (vaciarBtn) vaciarBtn.focus();
            return;
        }
        if (detailEl && !detailEl.hidden) {
            e.preventDefault();
            clearInspector({ focusSlot: true });
        }
    });

    placeFlash();
    if (detailEl && !detailEl.hidden) {
        var pressedInit = document.querySelector('.hotspot-slot[aria-pressed="true"]');
        var initPayload = {};
        if (pressedInit) {
            try { initPayload = JSON.parse(pressedInit.getAttribute('data-slot') || '{}'); } catch (err) { initPayload = {}; }
        }
        if (initPayload.username) {
            updateSyncAction(initPayload);
            bindRisk(initPayload);
        } else {
            updateSyncAction({
                username: document.getElementById('insp-username').textContent,
                router: document.getElementById('insp-router').textContent,
                estado: currentEstado,
                estadoLabel: currentEstadoLabel
            });
        }
    }
})();
</script>
@endpush
