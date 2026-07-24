@extends('layouts.app')

@php
    $clienteNombre = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));
    $router = $servicio->pool?->router;
    $ultimaOptica = $ultimaSenalOptica ?? null;
    $ultimaAntena = $ultimaSenalAntena ?? null;
    $nocBarPct = function (?float $dbm, float $min = -95, float $max = -50): int {
        if ($dbm === null) return 0;
        $pct = (($dbm - $min) / ($max - $min)) * 100;
        return (int) max(4, min(100, round($pct)));
    };
    $nocSvgRefresh = <<<'SVG'
<svg class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
SVG;
    $nocSvgPlay = <<<'SVG'
<svg class="noc-btn-action-icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.866l11.196-6.86a1 1 0 0 0 0-1.732L9.5 4.274A1 1 0 0 0 8 5.14z"/></svg>
SVG;
    $nocSvgTraffic = <<<'SVG'
<svg class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
SVG;
    $nocSvgEdit = <<<'SVG'
<svg class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
SVG;
    $nocSvgList = <<<'SVG'
<svg class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
SVG;
@endphp

@section('title', 'Herramientas de red')

@section('content')
<div class="max-w-7xl mx-auto" id="herramientas-red-app"
     data-ping-url="{{ route('servicios.ping', $servicio->servicio_id) }}"
     data-mikrotik-url="{{ route('servicios.herramientas-red.mikrotik', $servicio->servicio_id) }}"
     data-antena-url="{{ route('servicios.herramientas-red.antena', $servicio->servicio_id) }}"
     data-antena-dhcp-url="{{ route('servicios.herramientas-red.antena-dhcp', $servicio->servicio_id) }}"
     data-olt-url="{{ route('servicios.herramientas-red.olt', $servicio->servicio_id) }}"
     data-olt-desc-url="{{ route('servicios.herramientas-red.olt-desc', $servicio->servicio_id) }}"
     data-csrf="{{ csrf_token() }}"
     data-desc-onu="{{ $servicio->usuario_pppoe ?: trim(($servicio->cliente?->nombre ?? '').'_'.($servicio->cliente?->apellido ?? '')) }}">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-500 dark:text-blue-400">Centro de operaciones · NOC</p>
            <a href="{{ route('servicios.index') }}" class="mt-1 inline-block text-sm font-medium text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">&larr; Volver a servicios</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Herramientas de red</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $clienteNombre !== '' ? $clienteNombre : 'Servicio #'.$servicio->servicio_id }}
                @if($servicio->ip)
                    · IP <span class="font-mono text-gray-800 dark:text-gray-200">{{ $servicio->ip }}</span>
                @endif
                @if($servicio->usuario_pppoe)
                    · PPPoE <span class="font-mono text-gray-800 dark:text-gray-200">{{ $servicio->usuario_pppoe }}</span>
                @endif
            </p>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                Router: {{ $router?->nombre ?? $router?->ip ?? 'sin pool/router' }}
                @if($router?->nodo)
                    · Nodo {{ $router->nodo->descripcion }}
                @endif
            </p>
        </div>
        @if($servicio->servicio_id)
            <a href="{{ route('servicios.edit', $servicio->servicio_id) }}"
               class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium">
                Editar servicio
            </a>
        @endif
    </div>

    @if ($ticketOrigen ?? null)
        @include('partials.ticket-diagnostico-app', [
            'datosDiagnostico' => $ticketOrigen->datos_diagnostico,
            'ticketOrigen' => $ticketOrigen,
            'wrapperClass' => 'mb-6',
        ])
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {{-- Ping --}}
        <section class="noc-card">
            <div class="noc-card-head">
                <span class="noc-icon noc-icon--blue">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <div>
                    <h2 class="noc-card-title">Ping CPE</h2>
                    <p class="noc-card-sub">ICMP desde el servidor</p>
                </div>
            </div>
            <div class="noc-card-body space-y-3">
                <div class="flex items-stretch gap-2">
                    <div class="noc-input-display flex flex-1 items-center font-mono text-sm">{{ $servicio->ip ?: 'Sin IP asignada' }}</div>
                    <button type="button" id="btn-ping" class="noc-btn-icon noc-btn-icon--primary" title="Ejecutar ping" aria-label="Ejecutar ping" @disabled(! $servicio->ip)>
                        {!! $nocSvgPlay !!}
                        <svg id="spin-ping" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
                <div id="out-ping" class="hidden text-sm"></div>
            </div>
        </section>

        {{-- MAC --}}
        <section class="noc-card">
            <div class="noc-card-head">
                <span class="noc-icon noc-icon--indigo">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div>
                    <h2 class="noc-card-title">MAC Address</h2>
                    <p class="noc-card-sub">Consulta MikroTik</p>
                </div>
            </div>
            <div class="noc-card-body space-y-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">PPP activo · ARP · DHCP lease</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btn-mac" class="noc-btn-icon noc-btn-icon--primary" title="Consultar MAC" aria-label="Consultar MAC" @disabled(! $router)>
                        {!! $nocSvgRefresh !!}
                        <svg id="spin-mac" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                    <button type="button" id="btn-trafico" class="noc-btn-icon noc-btn-icon--ghost" title="Ver tráfico de sesión" aria-label="Ver tráfico de sesión" @disabled(! $router)>
                        {!! $nocSvgTraffic !!}
                        <svg id="spin-trafico" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
                <div id="out-mac" class="hidden text-sm"></div>
                <div id="out-trafico" class="hidden text-sm"></div>
            </div>
        </section>

        {{-- ONU Signal --}}
        <section class="noc-card">
            <div class="noc-card-head">
                <span class="noc-icon noc-icon--amber">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="noc-card-title">ONU Signal</h2>
                        @if($ultimaOptica)
                            <span class="noc-live-badge">REGISTRO</span>
                        @endif
                    </div>
                    <p class="noc-card-sub">Señal óptica OLT</p>
                </div>
            </div>
            <div class="noc-card-body space-y-3">
                @if($ultimaOptica)
                    <div class="grid grid-cols-2 gap-3">
                        @if($ultimaOptica->tx_power_dbm !== null)
                            <div>
                                <p class="noc-metric-label">TX Power</p>
                                <p class="noc-metric noc-metric--blue">{{ $ultimaOptica->tx_power_dbm }} <span class="text-sm font-normal">dBm</span></p>
                            </div>
                        @endif
                        @if($ultimaOptica->rx_power_dbm !== null)
                            <div>
                                <p class="noc-metric-label">RX Power</p>
                                <p class="noc-metric {{ (float) $ultimaOptica->rx_power_dbm <= -27 ? 'noc-metric--warn' : 'noc-metric--amber' }}">
                                    {{ $ultimaOptica->rx_power_dbm }} <span class="text-sm font-normal">dBm</span>
                                </p>
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 dark:text-gray-400">
                        @if($ultimaOptica->pon_port !== null && $ultimaOptica->onu_index !== null)
                            <div>PON <span class="font-mono text-gray-700 dark:text-gray-200">{{ $ultimaOptica->pon_port }}:{{ $ultimaOptica->onu_index }}</span></div>
                        @endif
                        @if($ultimaOptica->onu_estado)
                            <div>Estado <span class="text-gray-700 dark:text-gray-200">{{ $ultimaOptica->onu_estado }}</span></div>
                        @endif
                    </div>
                    <p class="text-[10px] text-gray-400">Última lectura · {{ $ultimaOptica->ocurrio_at?->format('d/m/Y H:i') }}</p>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">Sin registro de señal óptica. Consultá la ONU para guardar RX/TX.</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btn-olt" class="noc-btn-icon noc-btn-icon--primary" title="Consultar señal ONU" aria-label="Consultar señal ONU" @disabled(! ($esFibra ?? false))>
                        {!! $nocSvgRefresh !!}
                        <svg id="spin-olt" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                    <button type="button" id="btn-olt-desc" class="noc-btn-icon noc-btn-icon--ghost" title="Aplicar descripción ONU" aria-label="Aplicar descripción ONU" @disabled(! ($esFibra ?? false) || (blank($servicio->usuario_pppoe) && blank($servicio->cliente?->nombre)))>
                        {!! $nocSvgEdit !!}
                        <svg id="spin-olt-desc" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
                <div id="out-olt" class="hidden text-sm"></div>
            </div>
        </section>

        {{-- CPE / Antena --}}
        <section class="noc-card">
            <div class="noc-card-head">
                <span class="noc-icon noc-icon--sky">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-2.912a10 10 0 0114.16 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="noc-card-title">CPE / Antena</h2>
                        @if($ultimaAntena)
                            <span class="noc-live-badge">REGISTRO</span>
                        @endif
                    </div>
                    <p class="noc-card-sub">Wireless Ubiquiti</p>
                </div>
            </div>
            <div class="noc-card-body space-y-3">
                @if($ultimaAntena)
                    @if($ultimaAntena->antena_signal_dbm !== null)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="noc-metric-label mb-0">Signal Strength</span>
                                <span class="font-mono font-semibold text-blue-500">{{ $ultimaAntena->antena_signal_dbm }} dBm</span>
                            </div>
                            <div class="noc-bar-track"><div class="noc-bar-fill noc-bar-fill--signal" style="width: {{ $nocBarPct((float) $ultimaAntena->antena_signal_dbm) }}%"></div></div>
                        </div>
                    @endif
                    @if(is_array($ultimaAntena->payload) && !empty($ultimaAntena->payload['noise_floor_dbm']))
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="noc-metric-label mb-0">Noise Floor</span>
                                <span class="font-mono font-semibold text-orange-500">{{ $ultimaAntena->payload['noise_floor_dbm'] }} dBm</span>
                            </div>
                            <div class="noc-bar-track"><div class="noc-bar-fill noc-bar-fill--noise" style="width: {{ $nocBarPct((float) $ultimaAntena->payload['noise_floor_dbm']) }}%"></div></div>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                        @if($ultimaAntena->antena_snr_db !== null)
                            <span>SNR <strong class="text-gray-700 dark:text-gray-200">{{ $ultimaAntena->antena_snr_db }} dB</strong></span>
                        @endif
                        @if(is_array($ultimaAntena->payload) && !empty($ultimaAntena->payload['ccq']))
                            <span>CCQ <strong class="text-gray-700 dark:text-gray-200">{{ $ultimaAntena->payload['ccq'] }}%</strong></span>
                        @endif
                    </div>
                    <p class="text-[10px] text-gray-400">Última lectura · {{ $ultimaAntena->ocurrio_at?->format('d/m/Y H:i') }}</p>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">Sin registro de señal antena. Consultá vía SSH <span class="font-mono">wstalist</span>.</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btn-antena" class="noc-btn-icon noc-btn-icon--primary" title="Consultar señal antena" aria-label="Consultar señal antena" @disabled(! ($esAntena ?? false))>
                        {!! $nocSvgRefresh !!}
                        <svg id="spin-antena" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                    <button type="button" id="btn-antena-dhcp" class="noc-btn-icon noc-btn-icon--ghost" title="Consultar DHCP leases" aria-label="Consultar DHCP leases" @disabled(! ($esAntena ?? false))>
                        {!! $nocSvgList !!}
                        <svg id="spin-antena-dhcp" class="noc-btn-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </button>
                </div>
                <div id="out-antena" class="hidden text-sm"></div>
                <div id="out-antena-dhcp" class="hidden text-sm"></div>
            </div>
        </section>
    </div>

    <section class="noc-card mt-6">
        <div class="noc-card-head border-b border-gray-200 dark:border-gray-700 px-4 py-3">
            <div>
                <h2 class="noc-card-title text-base">Registro de actividad reciente</h2>
                <p class="noc-card-sub">Señal óptica, señal antena y eventos PPPoE · últimos 30</p>
            </div>
        </div>

        @php
            $timeline = $pppoeTimeline12h ?? null;
            $timelineSegmentos = $timeline['segmentos'] ?? [];
            $timelineMarcas = $timeline['marcas'] ?? [];
        @endphp
        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Últimas 12 horas · PPPoE</p>
                    @if($timeline)
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            {{ $timeline['inicio']->format('d/m H:i') }} → {{ $timeline['fin']->format('d/m H:i') }}
                            · Estado actual:
                            @if(($timeline['estado_actual'] ?? '') === 'up')
                                <span class="font-medium text-sky-500 dark:text-sky-400">conectado</span>
                            @elseif(($timeline['estado_actual'] ?? '') === 'down')
                                <span class="font-medium text-amber-500 dark:text-amber-400">desconectado</span>
                            @else
                                <span class="font-medium text-gray-500 dark:text-gray-400">sin datos</span>
                            @endif
                        </p>
                    @endif
                </div>
                @if($timeline)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="pppoe-timeline-legend pppoe-timeline-legend--up"></span>
                            Conectado {{ \App\Support\PppoeTimeline12h::formatearDuracion($timeline['conectado_segundos']) }}
                            ({{ number_format($timeline['conectado_pct'], 1, ',', '.') }}%)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="pppoe-timeline-legend pppoe-timeline-legend--down"></span>
                            Desconectado {{ \App\Support\PppoeTimeline12h::formatearDuracion($timeline['desconectado_segundos']) }}
                            ({{ number_format($timeline['desconectado_pct'], 1, ',', '.') }}%)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="pppoe-timeline-legend pppoe-timeline-legend--unknown"></span>
                            Sin datos {{ \App\Support\PppoeTimeline12h::formatearDuracion($timeline['sin_datos_segundos'] ?? 0) }}
                            ({{ number_format($timeline['sin_datos_pct'] ?? 0, 1, ',', '.') }}%)
                        </span>
                    </div>
                @endif
            </div>

            <div class="pppoe-timeline-track">
                @foreach($timelineSegmentos as $seg)
                    @php
                        $estadoSeg = $seg['estado'] ?? 'unknown';
                        $claseSeg = match ($estadoSeg) {
                            'up' => 'pppoe-timeline-seg--up',
                            'down' => 'pppoe-timeline-seg--down',
                            default => 'pppoe-timeline-seg--unknown',
                        };
                        $etiquetaSeg = \App\Support\PppoeTimeline12h::etiquetaEstado($estadoSeg);
                    @endphp
                    <div
                        class="pppoe-timeline-seg {{ $claseSeg }}"
                        style="left: {{ $seg['left_pct'] }}%; width: {{ $seg['width_pct'] }}%;"
                        title="{{ $etiquetaSeg }} · {{ $seg['inicio']->format('H:i:s') }} – {{ $seg['fin']->format('H:i:s') }} · {{ \App\Support\PppoeTimeline12h::formatearDuracion($seg['duracion_segundos']) }}"
                    ></div>
                @endforeach
            </div>

            @if(count($timelineMarcas) > 0)
                <div class="relative mt-1.5 h-3">
                    @foreach($timelineMarcas as $marca)
                        <span
                            class="absolute text-[9px] text-gray-400/80 dark:text-gray-500"
                            style="left: {{ $marca['left_pct'] }}%; transform: translateX(-50%);"
                        >{{ $marca['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Fecha</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Tipo</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Detalle</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Fuente</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse(($conexionEventos ?? collect()) as $ev)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">
                                {{ $ev->ocurrio_at?->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                @if($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_SENAL_OPTICA)
                                    <span class="noc-log-badge noc-log-badge--olt">ONU_SIG</span>
                                @elseif($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_SENAL_ANTENA)
                                    <span class="noc-log-badge noc-log-badge--wifi">WIFI</span>
                                @elseif($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_PPPOE_UP)
                                    <span class="noc-log-badge noc-log-badge--up">PPPoE UP</span>
                                @elseif($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_PPPOE_DOWN)
                                    <span class="noc-log-badge noc-log-badge--down">PPPoE DOWN</span>
                                @else
                                    {{ $ev->etiquetaTipo() }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                @if($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_SENAL_OPTICA)
                                    @if($ev->pon_port !== null && $ev->onu_index !== null)
                                        PON {{ $ev->pon_port }}:{{ $ev->onu_index }}
                                    @endif
                                    @if($ev->rx_power_dbm !== null)
                                        · RX <span class="font-mono">{{ $ev->rx_power_dbm }} dBm</span>
                                    @endif
                                    @if($ev->onu_estado)
                                        · {{ $ev->onu_estado }}
                                    @endif
                                    @if($ev->onu_descripcion)
                                        · {{ $ev->onu_descripcion }}
                                    @endif
                                @elseif($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_SENAL_ANTENA)
                                    @if($ev->antena_signal_dbm !== null)
                                        Señal <span class="font-mono">{{ $ev->antena_signal_dbm }} dBm</span>
                                    @endif
                                    @if($ev->antena_snr_db !== null)
                                        · SNR <span class="font-mono">{{ $ev->antena_snr_db }} dB</span>
                                    @endif
                                    @if(is_array($ev->payload))
                                        @if(!empty($ev->payload['noise_floor_dbm']))
                                            · Noise <span class="font-mono">{{ $ev->payload['noise_floor_dbm'] }} dBm</span>
                                        @endif
                                        @if(!empty($ev->payload['ccq']))
                                            · CCQ <span class="font-mono">{{ $ev->payload['ccq'] }}%</span>
                                        @endif
                                        @if(!empty($ev->payload['tx_rx_rate']))
                                            · TX/RX <span class="font-mono">{{ $ev->payload['tx_rx_rate'] }}</span>
                                        @endif
                                        @if(!empty($ev->payload['capacity']))
                                            · Cap. <span class="font-mono">{{ $ev->payload['capacity'] }}</span>
                                        @endif
                                        @if(!empty($ev->payload['distance']))
                                            · Dist. <span class="font-mono">{{ $ev->payload['distance'] }}</span>
                                        @endif
                                        @if(!empty($ev->payload['ap_name']))
                                            · AP <span class="font-mono">{{ $ev->payload['ap_name'] }}</span>
                                        @endif
                                        @if(!empty($ev->payload['mac_remota']))
                                            · MAC <span class="font-mono">{{ $ev->payload['mac_remota'] }}</span>
                                        @endif
                                    @endif
                                    @if($ev->antena_radio_iface)
                                        · {{ $ev->antena_radio_iface }}
                                    @endif
                                @else
                                    {{ $ev->pppoe_estado === 'up' ? 'Online' : 'Offline' }}
                                    @if($ev->usuario_pppoe)
                                        · <span class="font-mono">{{ $ev->usuario_pppoe }}</span>
                                    @endif
                                    @if($ev->uptime)
                                        · uptime {{ $ev->uptime }}
                                    @endif
                                    @if($ev->mac_address)
                                        · {{ $ev->mac_address }}
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ev->fuente ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                Todavía no hay eventos. Se registran al consultar MAC/tráfico o ONU en OLT.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
        Antena Ubiquiti: SSH a la IP del servicio con <span class="font-mono">wstalist</span> (RSSI, noise, CCQ, TX/RX, distancia, MAC remota)
        o <span class="font-mono">cat /tmp/dhcpd.leases</span> (dispositivos conectados al CPE vía DHCP).
        OLT: estrategia principal <span class="font-mono">show address-table gpon 0/{pon}</span> (tabla por PON).
        El download MikroTik se lee de <span class="font-mono">&lt;pppoe-USUARIO&gt;</span>.
    </p>
</div>
@endsection

@push('scripts')
<style>
    .noc-card {
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }
    .dark .noc-card {
        border-color: #334155;
        background: #1e293b;
        box-shadow: none;
    }
    .noc-card-head {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem 1rem 0;
    }
    .noc-card-body {
        padding: 1rem;
    }
    .noc-card-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .dark .noc-card-title { color: #f1f5f9; }
    .noc-card-sub {
        margin-top: 0.125rem;
        font-size: 0.6875rem;
        color: #64748b;
    }
    .dark .noc-card-sub { color: #94a3b8; }
    .noc-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.5rem;
        flex-shrink: 0;
    }
    .noc-icon--blue { background: #dbeafe; color: #2563eb; }
    .noc-icon--indigo { background: #e0e7ff; color: #4f46e5; }
    .noc-icon--amber { background: #ffedd5; color: #ea580c; }
    .noc-icon--sky { background: #e0f2fe; color: #0284c7; }
    .noc-icon--teal { background: #ccfbf1; color: #0d9488; }
    .dark .noc-icon--blue { background: rgb(30 58 138 / 0.45); color: #93c5fd; }
    .dark .noc-icon--indigo { background: rgb(49 46 129 / 0.45); color: #a5b4fc; }
    .dark .noc-icon--amber { background: rgb(124 45 18 / 0.45); color: #fdba74; }
    .dark .noc-icon--sky { background: rgb(12 74 110 / 0.45); color: #7dd3fc; }
    .dark .noc-icon--teal { background: rgb(19 78 74 / 0.45); color: #5eead4; }
    .noc-input-display {
        padding: 0.625rem 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
    }
    .dark .noc-input-display {
        border-color: #475569;
        background: #0f172a;
        color: #e2e8f0;
    }
    .noc-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 0.5rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #fff;
        background: #2563eb;
        border: 1px solid #1d4ed8;
        transition: background 0.15s;
    }
    .noc-btn-primary:hover:not(:disabled) { background: #1d4ed8; }
    .noc-btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }
    .noc-btn-ghost {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 0.5rem;
        padding: 0.45rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #475569;
        border: 1px solid #cbd5e1;
        background: transparent;
    }
    .dark .noc-btn-ghost {
        color: #cbd5e1;
        border-color: #475569;
    }
    .noc-btn-ghost:hover:not(:disabled) { background: rgb(148 163 184 / 0.12); }
    .noc-btn-ghost:disabled { opacity: 0.45; cursor: not-allowed; }
    .noc-btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.625rem;
        flex-shrink: 0;
        transition: background 0.15s, box-shadow 0.15s, opacity 0.15s, transform 0.1s;
    }
    .noc-btn-icon--primary {
        color: #fff;
        background: #3b82f6;
        border: none;
        box-shadow: 0 1px 2px rgb(59 130 246 / 0.35), inset 0 1px 0 rgb(255 255 255 / 0.12);
    }
    .noc-btn-icon--primary:hover:not(:disabled) {
        background: #2563eb;
        box-shadow: 0 2px 6px rgb(37 99 235 / 0.4);
    }
    .noc-btn-icon--primary:active:not(:disabled) { transform: scale(0.96); }
    .noc-btn-icon--ghost {
        color: #64748b;
        border: none;
        background: rgb(148 163 184 / 0.14);
    }
    .dark .noc-btn-icon--ghost {
        color: #cbd5e1;
        background: rgb(51 65 85 / 0.55);
    }
    .noc-btn-icon--ghost:hover:not(:disabled) {
        color: #334155;
        background: rgb(148 163 184 / 0.22);
    }
    .dark .noc-btn-icon--ghost:hover:not(:disabled) {
        color: #f1f5f9;
        background: rgb(71 85 105 / 0.75);
    }
    .noc-btn-icon:disabled { opacity: 0.4; cursor: not-allowed; }
    .noc-btn-action-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
    }
    .noc-btn-spinner {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
    }
    .noc-btn-icon .noc-btn-action-icon.hidden,
    .noc-btn-icon .noc-btn-spinner.hidden { display: none; }
    .noc-metric-label {
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.125rem;
    }
    .dark .noc-metric-label { color: #94a3b8; }
    .noc-metric {
        font-size: 1.375rem;
        font-weight: 700;
        line-height: 1.1;
    }
    .noc-metric--blue { color: #2563eb; }
    .noc-metric--amber { color: #ea580c; }
    .noc-metric--warn { color: #dc2626; }
    .dark .noc-metric--blue { color: #60a5fa; }
    .dark .noc-metric--amber { color: #fb923c; }
    .dark .noc-metric--warn { color: #f87171; }
    .noc-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 9999px;
        padding: 0.125rem 0.5rem;
        font-size: 0.5625rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        color: #15803d;
        background: #dcfce7;
        border: 1px solid #86efac;
    }
    .dark .noc-live-badge {
        color: #86efac;
        background: rgb(20 83 45 / 0.35);
        border-color: #166534;
    }
    .noc-bar-track {
        height: 0.375rem;
        border-radius: 9999px;
        background: #334155;
        overflow: hidden;
    }
    .noc-bar-fill {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.35s ease;
    }
    .noc-bar-fill--signal { background: linear-gradient(90deg, #2563eb, #38bdf8); }
    .noc-bar-fill--noise { background: linear-gradient(90deg, #ea580c, #fb923c); }
    .noc-log-badge {
        display: inline-flex;
        border-radius: 0.25rem;
        padding: 0.125rem 0.375rem;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }
    .noc-log-badge--olt { background: #ffedd5; color: #9a3412; }
    .noc-log-badge--wifi { background: #e0f2fe; color: #075985; }
    .noc-log-badge--up { background: #dcfce7; color: #166534; }
    .noc-log-badge--down { background: #fee2e2; color: #991b1b; }
    .dark .noc-log-badge--olt { background: rgb(124 45 18 / 0.45); color: #fdba74; }
    .dark .noc-log-badge--wifi { background: rgb(12 74 110 / 0.45); color: #7dd3fc; }
    .dark .noc-log-badge--up { background: rgb(20 83 45 / 0.45); color: #86efac; }
    .dark .noc-log-badge--down { background: rgb(127 29 29 / 0.45); color: #fca5a5; }
    .pppoe-timeline-track {
        position: relative;
        height: 0.625rem;
        width: 100%;
        overflow: hidden;
        border-radius: 9999px;
        background: rgb(226 232 240 / 0.65);
    }
    .dark .pppoe-timeline-track {
        background: rgb(51 65 85 / 0.55);
    }
    .pppoe-timeline-seg {
        position: absolute;
        top: 0;
        height: 100%;
        min-width: 1px;
    }
    .pppoe-timeline-seg--up {
        background: linear-gradient(180deg, #38bdf8 0%, #0ea5e9 100%);
    }
    .pppoe-timeline-seg--down {
        background: linear-gradient(180deg, #fdba74 0%, #f97316 100%);
    }
    .pppoe-timeline-seg--unknown {
        background: rgb(148 163 184 / 0.55);
    }
    .dark .pppoe-timeline-seg--unknown {
        background: rgb(100 116 139 / 0.65);
    }
    .pppoe-timeline-legend {
        display: inline-block;
        height: 0.5rem;
        width: 0.5rem;
        border-radius: 9999px;
        flex-shrink: 0;
    }
    .pppoe-timeline-legend--up {
        background: #0ea5e9;
    }
    .pppoe-timeline-legend--down {
        background: #f97316;
    }
    .pppoe-timeline-legend--unknown {
        background: #94a3b8;
    }
    .ubnt-signal-panel {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }
    .dark .ubnt-signal-panel {
        background: linear-gradient(180deg, rgb(31 41 55) 0%, rgb(17 24 39) 100%);
    }
    .ubnt-chain-bar {
        height: 10px;
        border-radius: 9999px;
        background: #eef2f7;
        overflow: hidden;
    }
    .dark .ubnt-chain-bar {
        background: #374151;
    }
    .ubnt-chain-fill {
        height: 100%;
        border-radius: 9999px;
        background: linear-gradient(90deg, #0ea5e9 0%, #22d3ee 55%, #67e8f9 100%);
        transition: width 0.35s ease;
    }
    .ubnt-chain-badge {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 0.25rem;
        background: #111827;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>
<script>
(function () {
    var root = document.getElementById('herramientas-red-app');
    if (!root) return;

    var pingUrl = root.getAttribute('data-ping-url');
    var mikrotikUrl = root.getAttribute('data-mikrotik-url');
    var antenaUrl = root.getAttribute('data-antena-url');
    var antenaDhcpUrl = root.getAttribute('data-antena-dhcp-url');
    var oltUrl = root.getAttribute('data-olt-url');
    var oltDescUrl = root.getAttribute('data-olt-desc-url');
    var csrf = root.getAttribute('data-csrf');
    var mikrotikCache = null;

    function setLoading(btnId, spinId, on) {
        var btn = document.getElementById(btnId);
        var spin = document.getElementById(spinId);
        if (btn) btn.disabled = !!on;
        if (spin) spin.classList.toggle('hidden', !on);
        if (btn) {
            btn.querySelectorAll('.noc-btn-action-icon').forEach(function (icon) {
                icon.classList.toggle('hidden', !!on);
            });
        }
        if (btn && on && spin) spin.classList.add('animate-spin');
        if (btn && !on && spin) spin.classList.remove('animate-spin');
    }

    function showBox(id, html) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.innerHTML = html;
    }

    function errHtml(msg) {
        return '<p class="text-red-600 dark:text-red-400">' + escapeHtml(msg || 'Error') + '</p>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: '{}',
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data || {} };
            });
        });
    }

    function fetchMikrotik(force) {
        if (!force && mikrotikCache) {
            return Promise.resolve({ ok: true, data: mikrotikCache });
        }
        return postJson(mikrotikUrl).then(function (res) {
            if (res.ok && res.data.success) {
                mikrotikCache = res.data;
            }
            return res;
        });
    }

    document.getElementById('btn-ping')?.addEventListener('click', function () {
        setLoading('btn-ping', 'spin-ping', true);
        showBox('out-ping', '<p class="text-gray-500 dark:text-gray-400">Consultando…</p>');
        postJson(pingUrl).then(function (res) {
            var d = res.data;
            if (!res.ok && !d.output) {
                showBox('out-ping', errHtml(d.message));
                return;
            }
            var badge = d.alive
                ? '<span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300">Responde</span>'
                : '<span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300">Sin respuesta</span>';
            var html = '<div class="flex flex-wrap items-center gap-2">' + badge +
                '<span class="text-gray-600 dark:text-gray-300">' + escapeHtml(d.message || '') + '</span></div>';
            if (d.output) {
                html += '<pre class="mt-2 max-h-48 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-2 text-xs font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-200 whitespace-pre-wrap">' +
                    escapeHtml(d.output) + '</pre>';
            }
            showBox('out-ping', html);
        }).catch(function () {
            showBox('out-ping', errHtml('Error de conexión al ejecutar ping.'));
        }).finally(function () {
            setLoading('btn-ping', 'spin-ping', false);
        });
    });

    document.getElementById('btn-mac')?.addEventListener('click', function () {
        setLoading('btn-mac', 'spin-mac', true);
        showBox('out-mac', '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>');
        fetchMikrotik(true).then(function (res) {
            var d = res.data;
            if (!res.ok || d.success === false) {
                showBox('out-mac', errHtml(d.message));
                return;
            }
            if (!d.mac) {
                showBox('out-mac', '<p class="text-amber-600 dark:text-amber-400">' + escapeHtml(d.message || 'MAC no encontrada.') + '</p>');
                return;
            }
            var html = '<p class="font-mono text-lg font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.mac) + '</p>' +
                '<p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ' + escapeHtml(d.mac_fuente || '—') + '</p>';
            if (d.online) {
                html += '<p class="text-xs text-green-600 dark:text-green-400">Sesión PPPoE activa' + (d.uptime ? ' · uptime ' + escapeHtml(d.uptime) : '') + '</p>';
            }
            if (d.mac_sistema && d.mac_sistema.toUpperCase() !== d.mac.toUpperCase()) {
                html += '<p class="text-xs text-gray-400">MAC en sistema: ' + escapeHtml(d.mac_sistema) + '</p>';
            }
            showBox('out-mac', html);
        }).catch(function () {
            showBox('out-mac', errHtml('Error de conexión con MikroTik.'));
        }).finally(function () {
            setLoading('btn-mac', 'spin-mac', false);
        });
    });

    document.getElementById('btn-trafico')?.addEventListener('click', function () {
        setLoading('btn-trafico', 'spin-trafico', true);
        showBox('out-trafico', '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>');
        fetchMikrotik(true).then(function (res) {
            var d = res.data;
            if (!res.ok || d.success === false) {
                showBox('out-trafico', errHtml(d.message));
                return;
            }
            if (d.download_humano == null && d.upload_humano == null) {
                showBox('out-trafico', '<p class="text-amber-600 dark:text-amber-400">Sin contadores de tráfico (¿sesión caida o sin queue?).</p>');
                return;
            }
            var html = '<div class="space-y-1">' +
                '<p><span class="text-gray-500 dark:text-gray-400">Download:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.download_humano || '—') + '</span></p>' +
                '<p><span class="text-gray-500 dark:text-gray-400">Upload:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.upload_humano || '—') + '</span></p>' +
                '<p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ' + escapeHtml(d.trafico_fuente || '—') + '</p>' +
                (d.online ? '<p class="text-xs text-green-600 dark:text-green-400">Sesión activa' + (d.uptime ? ' · ' + escapeHtml(d.uptime) : '') + '</p>' : '') +
                '</div>';
            showBox('out-trafico', html);
        }).catch(function () {
            showBox('out-trafico', errHtml('Error de conexión con MikroTik.'));
        }).finally(function () {
            setLoading('btn-trafico', 'spin-trafico', false);
        });
    });

    function antenaRawHtml(payload, label) {
        if (!payload.raw && !payload.comando) return '';
        var summary = label || 'Salida wstalist';
        var parts = '<details class="mt-3 rounded-lg border border-dashed border-sky-300 bg-sky-50/60 p-2 dark:border-sky-700 dark:bg-sky-950/30">' +
            '<summary class="cursor-pointer text-xs font-semibold text-sky-800 dark:text-sky-300">' + escapeHtml(summary) + '</summary>';
        if (payload.comando) {
            parts += '<p class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">Comando: <span class="font-mono">' +
                escapeHtml(payload.comando) + '</span> @ ' + escapeHtml(payload.host || '') + '</p>';
        }
        if (payload.raw) {
            parts += '<pre class="mt-1 max-h-56 overflow-auto rounded border border-gray-200 bg-white p-2 text-[11px] font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 whitespace-pre-wrap">' +
                escapeHtml(payload.raw) + '</pre>';
        }
        parts += '</details>';
        return parts;
    }

    function antenaDbmBarPercent(dbm) {
        var min = -95;
        var max = -50;
        var pct = ((Number(dbm) - min) / (max - min)) * 100;
        if (pct < 4) return 4;
        if (pct > 100) return 100;
        return Math.round(pct);
    }

    function antenaChainLabel(chains) {
        if (!Array.isArray(chains) || chains.length === 0) return '';
        return chains.map(function (c) {
            return String(Math.round(c.signal_dbm));
        }).join(' / ');
    }

    function antenaSignalGaugeHtml(d) {
        if (d.signal_dbm == null && d.noise_floor_dbm == null) return '';

        var chains = Array.isArray(d.signal_chains) ? d.signal_chains : [];
        var chainText = antenaChainLabel(chains);
        var delta = d.chain_delta != null ? Number(d.chain_delta) : null;
        var signalText = d.signal_dbm != null ? String(Math.round(Number(d.signal_dbm))) : '—';
        var noiseText = d.noise_floor_dbm != null ? String(Math.round(Number(d.noise_floor_dbm))) : '—';

        var html = '<div class="ubnt-signal-panel rounded-xl border border-gray-200 dark:border-gray-600 p-4 mb-3">' +
            '<div class="flex items-start justify-between gap-4">' +
            '<div class="min-w-0">' +
            '<div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Señal</div>' +
            '<div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">' +
            '<span class="text-3xl font-light text-gray-900 dark:text-gray-100">' + escapeHtml(signalText) + '</span>';

        if (chainText) {
            html += '<span class="text-sm text-gray-600 dark:text-gray-300">(' + escapeHtml(chainText) + ')</span>';
        }
        if (delta != null && chains.length >= 2) {
            html += '<span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Δ ' + escapeHtml(String(delta)) + '</span>';
        }
        html += '<span class="text-sm text-gray-500 dark:text-gray-400">dBm</span>' +
            '</div></div>' +
            '<div class="text-right shrink-0">' +
            '<div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ruido base</div>' +
            '<div class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">' + escapeHtml(noiseText) +
            ' <span class="text-sm font-normal text-gray-500">dBm</span></div>' +
            '</div></div>';

        if (chains.length > 0) {
            html += '<div class="mt-4 space-y-2.5">';
            chains.forEach(function (chain) {
                var pct = antenaDbmBarPercent(chain.signal_dbm);
                html += '<div class="flex items-center gap-2">' +
                    '<span class="ubnt-chain-badge">' + escapeHtml(String(chain.chain)) + '</span>' +
                    '<div class="ubnt-chain-bar flex-1">' +
                    '<div class="ubnt-chain-fill" style="width:' + pct + '%"></div>' +
                    '</div>' +
                    '<span class="w-10 text-right text-xs font-mono text-gray-600 dark:text-gray-300">' +
                    escapeHtml(String(Math.round(chain.signal_dbm))) + '</span>' +
                    '</div>';
            });
            html += '</div>';
        } else if (d.signal_dbm != null) {
            var mainPct = antenaDbmBarPercent(d.signal_dbm);
            html += '<div class="mt-4"><div class="ubnt-chain-bar"><div class="ubnt-chain-fill" style="width:' + mainPct + '%"></div></div></div>';
        }

        html += '</div>';
        return html;
    }

    function antenaDetalleHtml(d) {
        return '<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm">' +
            '<p><span class="text-gray-500">SNR:</span> <span class="font-mono">' +
            (d.snr_db != null ? escapeHtml(String(d.snr_db)) + ' dB' : '—') + '</span></p>' +
            '<p><span class="text-gray-500">CCQ / score:</span> <span class="font-mono">' +
            (d.ccq != null ? escapeHtml(String(d.ccq)) + '%' : '—') + '</span></p>' +
            '<p><span class="text-gray-500">TX/RX rate:</span> <span class="font-mono">' + escapeHtml(d.tx_rx_rate || '—') + '</span></p>' +
            '<p><span class="text-gray-500">Capacity:</span> <span class="font-mono">' + escapeHtml(d.capacity || '—') + '</span></p>' +
            '<p><span class="text-gray-500">Distancia:</span> <span class="font-mono">' + escapeHtml(d.distance || '—') + '</span></p>' +
            '<p><span class="text-gray-500">MAC remota:</span> <span class="font-mono">' + escapeHtml(d.mac_remota || '—') + '</span></p>' +
            (d.ap_name ? '<p class="sm:col-span-2"><span class="text-gray-500">AP / enlace:</span> <span class="font-semibold">' + escapeHtml(d.ap_name) + '</span></p>' : '') +
            '</div>';
    }

    function antenaDhcpLeasesHtml(d) {
        var leases = Array.isArray(d.leases) ? d.leases : [];
        if (leases.length === 0) {
            return '<p class="text-amber-600 dark:text-amber-400">' + escapeHtml(d.message || 'Sin leases DHCP.') + '</p>';
        }

        var rows = leases.map(function (lease) {
            return '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">' +
                '<td class="px-2 py-2 font-mono text-gray-900 dark:text-gray-100">' + escapeHtml(lease.ip || '—') + '</td>' +
                '<td class="px-2 py-2 font-mono text-gray-700 dark:text-gray-300">' + escapeHtml(lease.mac || '—') + '</td>' +
                '<td class="px-2 py-2 text-gray-700 dark:text-gray-300">' + escapeHtml(lease.hostname || '—') + '</td>' +
                '<td class="px-2 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">' + escapeHtml(lease.expires_human || '—') + '</td>' +
                '</tr>';
        }).join('');

        return '<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">' +
            '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">' +
            '<thead class="bg-gray-50 dark:bg-gray-900/40">' +
            '<tr>' +
            '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">IP</th>' +
            '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">MAC</th>' +
            '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Hostname</th>' +
            '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Vence</th>' +
            '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">' +
            rows +
            '</tbody></table></div>' +
            '<p class="mt-2 text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>';
    }

    document.getElementById('btn-antena')?.addEventListener('click', function () {
        if (!antenaUrl) return;
        setLoading('btn-antena', 'spin-antena', true);
        showBox('out-antena', '<p class="text-gray-500 dark:text-gray-400">Conectando por SSH y ejecutando wstalist…</p>');
        postJson(antenaUrl).then(function (res) {
            var d = res.data || {};
            if (!res.ok || d.success === false) {
                showBox('out-antena', errHtml(d.message) + antenaRawHtml(d));
                return;
            }
            var html = antenaSignalGaugeHtml(d) +
                antenaDetalleHtml(d) +
                '<p class="mt-2 text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>' +
                antenaRawHtml(d);
            showBox('out-antena', html);
        }).catch(function () {
            showBox('out-antena', errHtml('Error al consultar la antena por SSH.'));
        }).finally(function () {
            setLoading('btn-antena', 'spin-antena', false);
        });
    });

    document.getElementById('btn-antena-dhcp')?.addEventListener('click', function () {
        if (!antenaDhcpUrl) return;
        setLoading('btn-antena-dhcp', 'spin-antena-dhcp', true);
        showBox('out-antena-dhcp', '<p class="text-gray-500 dark:text-gray-400">Conectando por SSH y leyendo dhcpd.leases…</p>');
        postJson(antenaDhcpUrl).then(function (res) {
            var d = res.data || {};
            if (!res.ok || d.success === false) {
                showBox('out-antena-dhcp', errHtml(d.message) + antenaRawHtml(d, 'Salida dhcpd.leases'));
                return;
            }
            var html = antenaDhcpLeasesHtml(d) + antenaRawHtml(d, 'Salida dhcpd.leases');
            showBox('out-antena-dhcp', html);
        }).catch(function () {
            showBox('out-antena-dhcp', errHtml('Error al consultar DHCP leases por SSH.'));
        }).finally(function () {
            setLoading('btn-antena-dhcp', 'spin-antena-dhcp', false);
        });
    });

    function oltCmdResultHtml(payload) {
        var parts = '<details open class="mt-3 rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-2 dark:border-amber-700 dark:bg-amber-950/30">' +
            '<summary class="cursor-pointer text-xs font-semibold text-amber-800 dark:text-amber-300">Comando y resultado OLT</summary>';
        if (payload.comando) {
            parts += '<p class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">Comando: <span class="font-mono">' +
                escapeHtml(payload.comando) + '</span></p>';
        }
        if (payload.olt || payload.olts_probadas) {
            parts += '<p class="text-[11px] text-gray-600 dark:text-gray-400">OLT: <span class="font-mono">' +
                escapeHtml(payload.olt || (payload.olts_probadas || []).join(', ') || '—') + '</span></p>';
        }
        if (payload.raw_match || payload.raw) {
            parts += '<p class="mt-2 text-[11px] font-medium text-gray-500">Resultado</p>' +
                '<pre class="mt-1 max-h-56 overflow-auto rounded border border-gray-200 bg-white p-2 text-[11px] font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 whitespace-pre-wrap">' +
                escapeHtml(payload.raw_match || payload.raw) + '</pre>';
        }
        parts += '</details>';
        return parts;
    }

    document.getElementById('btn-olt')?.addEventListener('click', function () {
        if (!oltUrl) return;
        setLoading('btn-olt', 'spin-olt', true);
        showBox('out-olt', '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik + OLT (puede tardar)…</p>');
        postJson(oltUrl).then(function (res) {
            var d = res.data;
            if (!res.ok || d.success === false) {
                showBox('out-olt', errHtml(d.message) + oltCmdResultHtml(d));
                return;
            }
            var html = '<div class="space-y-1">' +
                '<p class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.mac || '') + '</p>' +
                '<p class="text-xs text-gray-500">MAC fuente: ' + escapeHtml(d.mac_fuente || '—') +
                (d.olt ? ' · OLT ' + escapeHtml(d.olt) : '') + '</p>' +
                '<p><span class="text-gray-500">PON/ONU:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' +
                (d.pon_port != null && d.onu_index != null ? escapeHtml(String(d.pon_port) + ':' + String(d.onu_index)) : '—') +
                '</span></p>' +
                '<p><span class="text-gray-500">Estado:</span> ' + escapeHtml(d.estado || '—') + '</p>' +
                '<p><span class="text-gray-500">Descripción:</span> ' + escapeHtml(d.descripcion || '—') + '</p>' +
                '<p><span class="text-gray-500">RX:</span> <span class="font-mono font-semibold">' +
                (d.rx_power_dbm != null ? escapeHtml(String(d.rx_power_dbm)) + ' dBm' : '—') +
                '</span></p>' +
                '<p class="text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>' +
                '</div>' + oltCmdResultHtml(d);
            showBox('out-olt', html);
        }).catch(function () {
            showBox('out-olt', errHtml('Error al consultar OLT.'));
        }).finally(function () {
            setLoading('btn-olt', 'spin-olt', false);
        });
    });

    document.getElementById('btn-olt-desc')?.addEventListener('click', function () {
        if (!oltDescUrl) return;
        var label = root.getAttribute('data-desc-onu') || 'usuario PPPoE';
        if (!confirm('¿Escribir en la OLT la descripción de la ONU como «' + label + '»?')) return;
        setLoading('btn-olt-desc', 'spin-olt-desc', true);
        showBox('out-olt', '<p class="text-gray-500 dark:text-gray-400">Localizando ONU y aplicando descripción…</p>');
        postJson(oltDescUrl).then(function (res) {
            var d = res.data || {};
            if (!res.ok || d.success === false) {
                showBox('out-olt', errHtml(d.message) + oltCmdResultHtml(d));
                return;
            }
            var html = '<div class="space-y-1">' +
                '<p class="text-green-700 dark:text-green-400 font-medium">' + escapeHtml(d.message || 'OK') + '</p>' +
                '<p><span class="text-gray-500">PON/ONU:</span> <span class="font-semibold">' +
                (d.pon_port != null && d.onu_index != null ? escapeHtml(String(d.pon_port) + ':' + String(d.onu_index)) : '—') +
                '</span></p>' +
                '<p><span class="text-gray-500">Desc escrita:</span> <span class="font-mono font-semibold">' + escapeHtml(d.descripcion || '') + '</span></p>' +
                '<p><span class="text-gray-500">Desc leída:</span> <span class="font-mono">' + escapeHtml(d.descripcion_leida || '—') + '</span></p>' +
                '<p class="text-xs text-gray-500">OLT ' + escapeHtml(d.olt || '—') + ' · MAC ' + escapeHtml(d.mac || '—') + '</p>' +
                '</div>' + oltCmdResultHtml(d);
            showBox('out-olt', html);
        }).catch(function () {
            showBox('out-olt', errHtml('Error al aplicar descripción en OLT.'));
        }).finally(function () {
            setLoading('btn-olt-desc', 'spin-olt-desc', false);
        });
    });
})();
</script>
@endpush
