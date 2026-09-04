@extends('layouts.app')

@section('title', 'Tickets')

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
    $busqueda = $busqueda ?? '';
    $clienteFiltro = $clienteFiltro ?? null;
    $cantidadFiltrosPanel = (int) (
        request()->filled('estado')
        + request()->filled('ticket_asunto_id')
        + request()->filled('asignado_id')
        + request()->boolean('ocultar_resuelto_cerrado')
    );
    $resaltar = fn (?string $texto): string => \App\Support\SearchHighlight::html($texto, $busqueda);
    $mapsUrlCliente = fn (?\App\Models\Cliente $cliente): ?string => \App\Helpers\MapsUrlHelper::toGoogleMapsUrl($cliente?->url_ubicacion);
    $etiquetaAntiguedad = function (?\Illuminate\Support\Carbon $fecha): string {
        if (! $fecha) {
            return '';
        }
        $dias = (int) $fecha->copy()->startOfDay()->diffInDays(now()->startOfDay());

        return match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => 'hace 1 día',
            default => 'hace '.$dias.' días',
        };
    };
@endphp
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
            Tickets
            <a href="{{ route('tickets.index', ['estado' => 'pendiente']) }}"
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                title="Ver tickets pendientes">
                {{ $ticketsPendientesCount }} pendiente{{ (int) $ticketsPendientesCount === 1 ? '' : 's' }}
            </a>
        </h1>
        <a href="{{ route('tickets.create') }}"
            class="inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:bg-purple-600 dark:hover:bg-purple-500 dark:focus:ring-purple-400 dark:focus:ring-offset-gray-900">
            Nuevo ticket
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
        <form method="GET" action="{{ route('tickets.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-t-xl">
            @if($clienteFiltro && $busqueda === '')
                <input type="hidden" name="cliente_id" value="{{ $clienteFiltro->cliente_id }}">
            @endif
            <input type="hidden" name="per_page" value="{{ $tickets->perPage() }}">
            <div class="flex items-stretch gap-2">
                <div class="flex items-center flex-1 min-w-0 min-h-[2.75rem] sm:min-h-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-purple-500 focus-within:ring-2 focus-within:ring-purple-500/20">
                    <span class="pl-3 flex items-center shrink-0 text-gray-400 dark:text-gray-500 pointer-events-none" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                        </svg>
                    </span>
                    <input type="search" name="q" id="ticket-busqueda" value="{{ $busqueda }}"
                        placeholder="Cliente, cédula, IP, asunto o #ticket"
                        aria-label="Buscar tickets"
                        autocomplete="off"
                        enterkeyhint="search"
                        class="flex-1 min-w-0 border-0 bg-transparent pl-2 pr-3 py-2.5 sm:py-2 text-base sm:text-sm leading-normal focus:outline-none focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 [appearance:textfield] [&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden">
                </div>
                <div class="relative shrink-0" id="ticket-filtros-wrap">
                    <button
                        type="button"
                        id="ticket-filtros-btn"
                        class="relative inline-flex items-center gap-2 h-full px-4 py-2.5 rounded-lg border font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/20 {{ $cantidadFiltrosPanel ? 'border-purple-600 bg-purple-600 text-white hover:bg-purple-700' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                        aria-expanded="false"
                        aria-controls="ticket-filtros-menu"
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
                        id="ticket-filtros-menu"
                        class="hidden absolute right-0 mt-2 w-80 max-w-sm py-3 px-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl z-30"
                    >
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Filtros</p>
                            @if($cantidadFiltrosPanel || $busqueda !== '' || $clienteFiltro)
                                <a href="{{ route('tickets.index') }}" class="text-xs text-purple-600 dark:text-purple-400 hover:underline">Limpiar</a>
                            @endif
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
                                <select name="estado" aria-label="Estado"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos</option>
                                    @foreach (App\Models\Ticket::estados() as $key => $label)
                                        <option value="{{ $key }}" {{ request('estado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Asunto</label>
                                <select name="ticket_asunto_id" aria-label="Asunto"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos</option>
                                    @foreach ($asuntos as $a)
                                        <option value="{{ $a->id }}" {{ request('ticket_asunto_id') == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Técnico</label>
                                <select name="asignado_id" aria-label="Técnico"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                                    <option value="">Todos</option>
                                    @foreach ($tecnicos as $t)
                                        <option value="{{ $t->usuario_id }}" {{ (string) request('asignado_id') === (string) $t->usuario_id ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300 select-none">
                                <input type="checkbox" name="ocultar_resuelto_cerrado" value="1" {{ request()->boolean('ocultar_resuelto_cerrado') ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 dark:bg-gray-700">
                                <span>Ocultar resuelto y cerrado</span>
                            </label>
                            <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors">Aplicar</button>
                        </div>
                    </div>
                </div>
            </div>
            @if($clienteFiltro && $busqueda === '')
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Cliente: <span class="font-medium">{{ trim($clienteFiltro->nombre.' '.($clienteFiltro->apellido ?? '')) }}</span>
                    · <a href="{{ route('tickets.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todos</a>
                </p>
            @endif
            @if(request()->filled('asignado_id'))
                @php $tecnicoFiltro = ($tecnicos ?? collect())->firstWhere('usuario_id', (int) request('asignado_id')); @endphp
                @if($tecnicoFiltro)
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        Técnico: <span class="font-medium">{{ $tecnicoFiltro->name }}</span>
                        · <a href="{{ route('tickets.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todos</a>
                    </p>
                @endif
            @endif
        </form>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($tickets as $ticket)
                        @php
                            $servicioConIp = $ticket->cliente
                                ? $ticket->cliente->servicios
                                    ->filter(fn ($s) => filled($s->ip))
                                    ->sortBy(fn ($s) => $s->estado === \App\Models\Servicio::ESTADO_ACTIVO ? 0 : 1)
                                    ->first()
                                : null;
                            $ipClienteTicket = $servicioConIp?->ip;
                            $nombreClienteTicket = $ticket->cliente
                                ? trim($ticket->cliente->nombre.' '.($ticket->cliente->apellido ?? ''))
                                : '';
                            $estados = App\Models\Ticket::estados();
                            $prioridades = App\Models\Ticket::prioridades();
                            $reportado = App\Models\Ticket::reportadoDesdeOpciones();
                        @endphp
                        <article class="p-4 {{ $loop->even ? 'bg-gray-50 dark:bg-gray-700/30' : 'bg-white dark:bg-gray-800' }} hover:bg-gray-100 dark:hover:bg-gray-700/40">
                            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                                <div class="min-w-0">
                                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                        <span class="font-medium text-gray-400">#{!! $resaltar((string) $ticket->id) !!}</span>
                                        {!! $resaltar($ticket->ticketAsunto?->nombre ?? '—') !!}
                                    </p>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            @if($ticket->estado === 'resuelto' || $ticket->estado === 'cerrado') bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300
                                            @elseif($ticket->estado === 'cancelado') bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300
                                            @elseif($ticket->estado === 'en_proceso') bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300
                                            @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                                            {{ $estados[$ticket->estado] ?? $ticket->estado }}
                                        </span>
                                    </div>
                                </div>
                                <div class="min-w-0 lg:col-span-2">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 mt-1 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true" title="Cliente">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        <div class="min-w-0">
                                            @if($ticket->cliente)
                                                <a href="{{ route('clientes.detalle', $ticket->cliente) }}" class="inline-block text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline" title="Ver detalle del cliente">{!! $resaltar($nombreClienteTicket) !!}</a>
                                                @if($ticket->cliente->cedula)
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{!! $resaltar($ticket->cliente->cedula) !!}</p>
                                                @endif
                                            @else
                                                <p class="text-sm text-gray-500 dark:text-gray-400">—</p>
                                            @endif
                                            @if($ticket->pedido_id)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pedido #{!! $resaltar((string) $ticket->pedido_id) !!}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 mt-1 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true" title="Fecha">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                                            @if($ticket->created_at)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $etiquetaAntiguedad($ticket->created_at) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-span-2 lg:col-span-1 flex items-start justify-end gap-1">
                                @php
                                    $mapsUrl = $mapsUrlCliente($ticket->cliente);
                                    $detalleTicket = [
                                        'id' => $ticket->id,
                                        'asunto' => $ticket->ticketAsunto?->nombre,
                                        'cliente' => $nombreClienteTicket !== '' ? $nombreClienteTicket : null,
                                        'ip_cliente' => $ipClienteTicket,
                                        'pedido_id' => $ticket->pedido_id,
                                        'descripcion' => $ticket->descripcion,
                                        'observaciones' => $ticket->observaciones,
                                        'nota_tecnico' => $ticket->nota_tecnico,
                                        'diagnostico_vista' => (new \App\Support\TicketDiagnosticoPresenter($ticket->datos_diagnostico))->secciones(),
                                        'estado' => $estados[$ticket->estado] ?? $ticket->estado,
                                        'prioridad' => $prioridades[$ticket->prioridad ?? 'media'] ?? $ticket->prioridad,
                                        'reportado' => $ticket->reportado_desde ? ($reportado[$ticket->reportado_desde] ?? $ticket->reportado_desde) : null,
                                        'creador' => $ticket->usuario?->name,
                                        'asignado' => $ticket->asignado?->name,
                                        'imagen_url' => $ticket->imagen ? asset('storage/'.$ticket->imagen) : null,
                                        'created_at' => $ticket->created_at?->format('d/m/Y H:i'),
                                        'updated_at' => $ticket->updated_at?->format('d/m/Y H:i'),
                                        'fecha_cierre' => $ticket->fecha_cierre?->format('d/m/Y H:i'),
                                    ];
                                    $canServicioCrear = auth()->user()?->tienePermiso('servicios.crear') ?? false;
                                    $canServiciosVer = auth()->user()?->tienePermiso('servicios.ver') ?? false;
                                    $canFacturaInternaCrear = auth()->user()?->tienePermiso('factura-interna.crear') ?? false;
                                    $serviciosCliente = $ticket->cliente?->servicios ?? collect();
                                    $servicioMigrar = $canServicioCrear
                                        ? $serviciosCliente->first(fn ($s) => $s->pool?->router)
                                        : null;
                                    $menuCfg = [
                                        'estado' => $ticket->estado,
                                        'asignado_id' => $ticket->asignado_id,
                                        'ticket_id' => $ticket->id,
                                        'cliente' => $nombreClienteTicket !== '' ? $nombreClienteTicket : null,
                                        'update_estado_url' => route('tickets.update-estado', $ticket),
                                        'edit_ticket_url' => route('tickets.edit', $ticket),
                                        'agenda_url' => route('tickets.crear-agenda', $ticket),
                                        'destroy_url' => route('tickets.destroy', $ticket),
                                        'csrf' => csrf_token(),
                                        'imagen_url' => $ticket->imagen ? asset('storage/'.$ticket->imagen) : null,
                                        'puede_marcar_resuelto' => ! in_array($ticket->estado, ['resuelto', 'cerrado', 'cancelado'], true),
                                        'migrar_url' => $servicioMigrar
                                            ? route('servicios.migrar', $servicioMigrar)
                                            : null,
                                        'herramientas_red_url' => ($canServiciosVer && $servicioConIp)
                                            ? route('servicios.herramientas-red', $servicioConIp).'?ticket_id='.$ticket->id
                                            : null,
                                        'puede_facturar_ticket' => $canFacturaInternaCrear && $ticket->cliente_id && ! $ticket->factura_interna_id,
                                        'facturar_url' => $canFacturaInternaCrear ? route('tickets.facturar', $ticket) : '',
                                    ];
                                @endphp
                                    @if($ipClienteTicket)
                                        <a href="http://{{ $ipClienteTicket }}" target="_blank" rel="noopener noreferrer"
                                            class="p-2 rounded-lg text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 transition-colors"
                                            title="Abrir equipo {{ $ipClienteTicket }}"
                                            aria-label="Abrir IP del cliente">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                            </svg>
                                        </a>
                                    @endif
                                    @if($mapsUrl)
                                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                                            class="p-2 rounded-lg text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors"
                                            title="Abrir ubicación en Google Maps"
                                            aria-label="Abrir ubicación en Google Maps">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </a>
                                    @endif
                                    <button type="button"
                                        class="btn-ver-detalle-ticket p-2 rounded-lg {{ filled($ticket->observaciones) || filled($ticket->descripcion) ? 'text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700' }} hover:text-purple-600 dark:hover:text-purple-300 transition-colors"
                                        title="Ver observaciones"
                                        aria-label="Ver observaciones"
                                        data-detalle-b64="{{ base64_encode(json_encode($detalleTicket, JSON_UNESCAPED_UNICODE)) }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </button>
                                    <button type="button"
                                        class="ticket-acciones-kebab p-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        title="Acciones"
                                        aria-label="Menú de acciones"
                                        aria-expanded="false"
                                        data-menu-b64="{{ base64_encode(json_encode($menuCfg, JSON_UNESCAPED_UNICODE)) }}">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                    </button>
                                    <button type="button"
                                        class="ticket-expandir-btn p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        title="Más datos"
                                        aria-label="Mostrar más datos"
                                        aria-expanded="false"
                                        aria-controls="ticket-extra-{{ $ticket->id }}">
                                        <svg class="ticket-expandir-icon w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div id="ticket-extra-{{ $ticket->id }}" class="hidden mt-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3 grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Prioridad</p>
                                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @if(($ticket->prioridad ?? 'media') === 'alta') bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300
                                        @elseif(($ticket->prioridad ?? '') === 'baja') bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300
                                        @else bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 @endif">
                                        {{ $prioridades[$ticket->prioridad ?? 'media'] ?? $ticket->prioridad }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Reportado</p>
                                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $ticket->reportado_desde ? ($reportado[$ticket->reportado_desde] ?? $ticket->reportado_desde) : '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Asignado</p>
                                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{!! $resaltar($ticket->asignado?->name ?? '—') !!}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Cobro</p>
                                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                        @if ($ticket->factura_interna_id)
                                            @if (auth()->user()?->tienePermiso('factura-interna.ver') ?? false)
                                                <a href="{{ route('factura-internas.show', $ticket->factura_interna_id) }}" class="font-medium text-emerald-600 dark:text-emerald-400 hover:underline">Sí</a>
                                            @else
                                                <span class="font-medium text-emerald-600 dark:text-emerald-400">Sí</span>
                                            @endif
                                            <span class="text-gray-500 dark:text-gray-400"> · {{ number_format((float) ($ticket->monto_cobro_ticket ?? 0), 0, ',', '.') }} Gs.</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">No</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            @if($busqueda !== '')
                                Ningún ticket coincide con la búsqueda "{{ $busqueda }}".
                                <a href="{{ route('tickets.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ver todos</a>
                            @else
                                No hay tickets. <a href="{{ route('tickets.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear uno</a>.
                            @endif
                        </div>
                    @endforelse
        </div>

        @if ($tickets->total() > 0)
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-b-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <span>Filas</span>
                    <select id="ticket-per-page" aria-label="Filas por página"
                        class="px-2 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                        @foreach ([10, 15, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($tickets->perPage() === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                    <span>por página</span>
                </label>
                @if ($tickets->hasPages())
                    <div class="min-w-0">{{ $tickets->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- Menú acciones (mismo patrón visual que servicios: kebab + panel fijo) --}}
<div id="ticket-acciones-dropdown" class="fixed z-[9999] hidden py-1 min-w-[220px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg" role="menu" aria-hidden="true"></div>

@php
    $tecnicos = $tecnicos ?? collect();
    $claseEstadoTarjeta = function (string $estado): string {
        return match ($estado) {
            'resuelto', 'cerrado' => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
            'cancelado' => 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
            'en_proceso' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
            'en_camino' => 'bg-cyan-50 dark:bg-gray-700 text-cyan-600 dark:text-cyan-400',
            'no_realizado' => 'bg-amber-50 dark:bg-gray-700 text-amber-800 dark:text-gray-300',
            default => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
        };
    };
    $iconoEstadoTarjeta = function (string $estado): string {
        return match ($estado) {
            'pendiente' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'en_camino' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>',
            'en_proceso' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'resuelto' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'no_realizado' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
            'cerrado' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
            'cancelado' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        };
    };
@endphp
<div id="ticket-estado-modal" class="fixed inset-0 z-[9999] hidden" role="dialog" aria-modal="true" aria-labelledby="ticket-estado-modal-title" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" id="ticket-estado-modal-backdrop"></div>
    <div class="relative min-h-full flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-xl rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <h3 id="ticket-estado-modal-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cambiar estado</h3>
                    <p id="ticket-estado-modal-sub" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                </div>
                <button type="button" id="ticket-estado-modal-cerrar" class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 mb-2">Estado</p>
            <div id="ticket-estado-cards" class="grid grid-cols-4 gap-2 mb-4">
                @foreach (App\Models\Ticket::estados() as $key => $label)
                    <button type="button"
                        class="ticket-estado-card relative h-20 w-full rounded-lg border border-gray-200 dark:border-gray-600 p-2 cursor-pointer flex flex-col items-center justify-center {{ $claseEstadoTarjeta($key) }}"
                        data-estado="{{ $key }}">
                        <span class="ticket-estado-check hidden absolute top-1 right-2 w-4 h-4 rounded-full bg-purple-600 text-white flex items-center justify-center pointer-events-none" aria-hidden="true">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">{!! $iconoEstadoTarjeta($key) !!}</svg>
                        <span class="mt-1 block text-xs font-semibold leading-tight">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            <label for="ticket-estado-tecnico" class="block text-xs font-medium uppercase tracking-wide text-gray-400 mb-2">Asignar técnico</label>
            <select id="ticket-estado-tecnico"
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none">
                <option value="">Sin asignar</option>
                @foreach ($tecnicos as $t)
                    <option value="{{ $t->usuario_id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="ticket-estado-modal-cancelar" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
                <button type="button" id="ticket-estado-modal-guardar" class="px-4 py-2 text-sm rounded-lg bg-purple-600 text-white font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:opacity-50">Guardar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('partials.ticket-diagnostico-app-styles')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var filtroOcultarStorageKey = 'tickets_filtro_ocultar_resuelto_cerrado';
    var selPerPage = document.getElementById('ticket-per-page');
    if (selPerPage) {
        selPerPage.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', this.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ticket-expandir-btn');
        if (!btn) return;
        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;
        var abierto = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !abierto);
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        btn.setAttribute('title', abierto ? 'Ocultar datos' : 'Más datos');
        btn.setAttribute('aria-label', abierto ? 'Ocultar datos' : 'Mostrar más datos');
        var icono = btn.querySelector('.ticket-expandir-icon');
        if (icono) icono.classList.toggle('rotate-180', abierto);
    });

    var wrapFiltros = document.getElementById('ticket-filtros-wrap');
    var btnFiltros = document.getElementById('ticket-filtros-btn');
    var menuFiltros = document.getElementById('ticket-filtros-menu');
    if (wrapFiltros && btnFiltros && menuFiltros) {
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
            if (!wrapFiltros.contains(e.target)) setFiltrosOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setFiltrosOpen(false);
        });
    }

    var filtroOcultarCheck = document.querySelector('input[name="ocultar_resuelto_cerrado"]');
    if (filtroOcultarCheck) {
        var formularioFiltros = filtroOcultarCheck.closest('form');
        var hasFiltroOcultarQuery = new URLSearchParams(window.location.search).has('ocultar_resuelto_cerrado');
        if (hasFiltroOcultarQuery) {
            localStorage.setItem(filtroOcultarStorageKey, filtroOcultarCheck.checked ? '1' : '0');
        } else {
            var filtroOcultarGuardado = localStorage.getItem(filtroOcultarStorageKey);
            if (filtroOcultarGuardado !== null) {
                filtroOcultarCheck.checked = filtroOcultarGuardado === '1';
                if (filtroOcultarCheck.checked && formularioFiltros) {
                    formularioFiltros.submit();
                }
            }
        }

        filtroOcultarCheck.addEventListener('change', function() {
            localStorage.setItem(filtroOcultarStorageKey, this.checked ? '1' : '0');
        });
    }

    /** Decodifica base64 (UTF-8) a string; atob() solo devuelve bytes como Latin-1 y rompe tildes/ñ en JSON. */
    function base64ToUtf8(b64) {
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new TextDecoder('utf-8').decode(bytes);
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function filaDetalle(etiqueta, valor, isDark) {
        if (valor === null || valor === undefined || valor === '') return '';
        var b = isDark ? 'border-gray-700' : 'border-gray-100';
        var lbl = isDark ? 'text-gray-400' : 'text-gray-500';
        var val = isDark ? 'text-gray-100' : 'text-gray-900';
        return '<div class="flex flex-col sm:flex-row sm:gap-2 py-1.5 border-b ' + b + ' last:border-0"><span class="' + lbl + ' shrink-0 min-w-[7rem]">' + escapeHtml(etiqueta) + '</span><span class="' + val + ' break-words">' + escapeHtml(valor) + '</span></div>';
    }

    function toneClass(tone) {
        if (tone === 'good') return 'diag-app-metric__value--good';
        if (tone === 'ok') return 'diag-app-metric__value--ok';
        if (tone === 'warn') return 'diag-app-metric__value--warn';
        if (tone === 'bad') return 'diag-app-metric__value--bad';
        return '';
    }

    function renderDiagnosticoApp(secciones) {
        if (!Array.isArray(secciones) || secciones.length === 0) return '';
        var html = '<div class="diag-app-panel mt-3"><div class="diag-app-panel__head"><div><p class="diag-app-panel__title">Diagnóstico app cliente</p><p class="diag-app-panel__sub">Telemetría capturada al reportar el problema</p></div></div><div class="diag-app-panel__body">';
        secciones.forEach(function(sec) {
            html += '<div><p class="diag-app-section__title">' + escapeHtml(sec.titulo || '') + '</p>';
            if (sec.tipo === 'metricas' && Array.isArray(sec.items)) {
                html += '<div class="diag-app-metrics diag-app-metrics--wide">';
                sec.items.forEach(function(item) {
                    html += '<div class="diag-app-metric"><p class="diag-app-metric__label">' + escapeHtml(item.label) + '</p><p class="diag-app-metric__value ' + toneClass(item.tone) + '">' + escapeHtml(item.value) + '</p></div>';
                });
                html += '</div>';
            } else if (sec.tipo === 'ping' && Array.isArray(sec.items)) {
                html += '<div class="diag-app-ping-grid">';
                sec.items.forEach(function(item) {
                    html += '<div class="diag-app-ping"><p class="diag-app-ping__label">' + escapeHtml(item.label) + '</p><p class="diag-app-ping__value">' + escapeHtml(item.value) + '</p></div>';
                });
                html += '</div>';
            } else if (sec.tipo === 'traceroute' && Array.isArray(sec.filas)) {
                html += '<div class="diag-app-table-wrap"><table class="diag-app-table"><thead><tr><th>Salto</th><th>Destino</th><th>Latencia</th><th>Red</th><th>Estado</th></tr></thead><tbody>';
                sec.filas.forEach(function(f) {
                    html += '<tr class="' + (f.alcanzado ? 'diag-app-table__destino' : '') + '"><td class="font-mono">' + escapeHtml(f.ttl) + '</td><td class="break-all">' + escapeHtml(f.destino) + '</td><td>' + escapeHtml(f.latencia) + '</td><td>' + escapeHtml(f.marca) + '</td><td>' + (f.alcanzado ? '<span class="diag-app-badge diag-app-badge--destino">Destino</span>' : '<span class="diag-app-badge diag-app-badge--transito">Tránsito</span>') + '</td></tr>';
                });
                html += '</tbody></table></div>';
            } else if (sec.tipo === 'ubicacion') {
                html += '<div class="diag-app-location"><p class="diag-app-location__coords">' + escapeHtml(sec.texto) + '</p>';
                if (sec.maps_url) {
                    html += '<a href="' + escapeHtml(sec.maps_url) + '" target="_blank" rel="noopener" class="diag-app-link">Abrir en Google Maps →</a>';
                }
                html += '</div>';
            }
            html += '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function bloqueNota(titulo, texto, isDark) {
        var box = isDark ? 'bg-gray-900/40 border-gray-600' : 'bg-gray-50 border-gray-200';
        var lbl = isDark ? 'text-gray-400' : 'text-gray-500';
        var val = isDark ? 'text-gray-100' : 'text-gray-900';
        var cuerpo = (texto === null || texto === undefined || String(texto).trim() === '')
            ? '<p class="text-sm ' + (isDark ? 'text-gray-500' : 'text-gray-400') + '">Sin datos</p>'
            : '<p class="text-sm ' + val + ' whitespace-pre-wrap break-words">' + escapeHtml(texto) + '</p>';
        return '<div class="rounded-lg border p-3 ' + box + '"><p class="text-xs font-medium uppercase tracking-wide ' + lbl + ' mb-1">' + escapeHtml(titulo) + '</p>' + cuerpo + '</div>';
    }

    function abrirDetalleTicketDesdeB64(raw) {
        if (!raw) return;
        var t;
        try {
            t = JSON.parse(base64ToUtf8(raw));
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo leer las observaciones.' });
            return;
        }
        var isDark = document.documentElement.classList.contains('dark');
        var wrap = isDark ? 'text-left text-sm max-h-[60vh] overflow-y-auto text-gray-100 space-y-3' : 'text-left text-sm max-h-[60vh] overflow-y-auto text-gray-900 space-y-3';
        var html = '<div class="' + wrap + '">';
        if (t.asunto) {
            html += '<p class="' + (isDark ? 'text-gray-300' : 'text-gray-600') + '">' + escapeHtml(t.asunto) + (t.cliente ? ' · ' + escapeHtml(t.cliente) : '') + '</p>';
        }
        html += bloqueNota('Descripción', t.descripcion, isDark);
        html += bloqueNota('Observaciones', t.observaciones, isDark);
        if (t.nota_tecnico) html += bloqueNota('Nota del técnico', t.nota_tecnico, isDark);
        html += renderDiagnosticoApp(t.diagnostico_vista);
        if (t.imagen_url) {
            var lk2 = isDark ? 'text-purple-400 hover:text-purple-300' : 'text-purple-600 hover:text-purple-800';
            html += '<a href="' + escapeHtml(t.imagen_url) + '" target="_blank" rel="noopener" class="' + lk2 + ' underline-offset-2 hover:underline text-sm">Ver imagen adjunta</a>';
        }
        html += '</div>';
        Swal.fire({
            title: 'Notas — Ticket #' + t.id,
            html: html,
            width: (Array.isArray(t.diagnostico_vista) && t.diagnostico_vista.length) ? '42rem' : '36rem',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#9333ea',
            background: isDark ? '#1f2937' : '#ffffff',
            color: isDark ? '#f9fafb' : '#111827',
            customClass: {
                popup: isDark ? '!bg-gray-800 !text-gray-100 !rounded-xl !border !border-gray-700 !shadow-2xl' : '!rounded-xl !border !border-gray-200 !shadow-xl',
                title: isDark ? '!text-gray-100' : '!text-gray-900',
                htmlContainer: 'text-left !mt-2',
                confirmButton: isDark ? '!bg-purple-600 hover:!bg-purple-500 !text-white !shadow-lg' : ''
            }
        });
    }

    var ticketMenuEl = document.getElementById('ticket-acciones-dropdown');
    var ticketMenuOpenBtn = null;

    function cerrarMenuTicketAcciones() {
        if (!ticketMenuEl) return;
        ticketMenuEl.classList.add('hidden');
        ticketMenuEl.setAttribute('aria-hidden', 'true');
        if (ticketMenuOpenBtn) {
            ticketMenuOpenBtn.setAttribute('aria-expanded', 'false');
            ticketMenuOpenBtn = null;
        }
    }

    function posicionarMenuTicketAcciones(btn) {
        if (!ticketMenuEl) return;
        ticketMenuEl.classList.remove('hidden');
        ticketMenuEl.setAttribute('aria-hidden', 'false');
        var rect = btn.getBoundingClientRect();
        var mw = 228;
        var mh = ticketMenuEl.offsetHeight || 280;
        var left = Math.max(8, Math.min(rect.right - mw, window.innerWidth - mw - 8));
        var top = rect.bottom + 4;
        if (rect.bottom + mh + 12 > window.innerHeight) {
            top = Math.max(8, rect.top - mh - 4);
        }
        ticketMenuEl.style.left = left + 'px';
        ticketMenuEl.style.top = top + 'px';
    }

    document.querySelectorAll('.btn-ver-detalle-ticket').forEach(function(btn) {
        btn.addEventListener('click', function() {
            cerrarMenuTicketAcciones();
            abrirDetalleTicketDesdeB64(this.getAttribute('data-detalle-b64'));
        });
    });

    function construirHtmlMenuTicket(cfg) {
        var icLista = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>';
        var icDoc = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
        var icEdit = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>';
        var icMig = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>';
        var icCal = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        var icImg = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
        var icTrash = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
        var icCheck = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        var icWifi = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-2.912a10 10 0 0114.16 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path></svg>';
        var baseBtn = 'w-full px-4 py-2.5 text-left text-sm flex items-center gap-2';
        var h = '';
        if (cfg.herramientas_red_url) {
            h += '<a href="' + escapeHtml(cfg.herramientas_red_url) + '" class="block ' + baseBtn + ' text-teal-700 dark:text-teal-300 hover:bg-teal-50 dark:hover:bg-teal-900/30">' + icWifi + ' Herramientas de red</a>';
        }
        h += '<button type="button" class="' + baseBtn + ' text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 ticket-menu-item" data-accion="estado" data-url="' + escapeHtml(cfg.update_estado_url) + '" data-estado="' + escapeHtml(cfg.estado || '') + '" data-asignado-id="' + escapeHtml(cfg.asignado_id != null ? String(cfg.asignado_id) : '') + '" data-ticket-id="' + escapeHtml(cfg.ticket_id != null ? String(cfg.ticket_id) : '') + '" data-cliente="' + escapeHtml(cfg.cliente || '') + '">' + icLista + ' Cambiar estado</button>';
        if (cfg.puede_facturar_ticket && cfg.facturar_url) {
            h += '<button type="button" class="' + baseBtn + ' text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 ticket-menu-item" data-accion="facturar-ticket" data-facturar-url="' + escapeHtml(cfg.facturar_url) + '">' + icDoc + ' Crear factura por ticket</button>';
        }
        if (cfg.puede_marcar_resuelto) {
            h += '<button type="button" class="' + baseBtn + ' text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 ticket-menu-item" data-accion="resuelto" data-url="' + escapeHtml(cfg.update_estado_url) + '">' + icCheck + ' Marcar como resuelto</button>';
        }
        h += '<a href="' + escapeHtml(cfg.edit_ticket_url) + '" class="block ' + baseBtn + ' text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30">' + icEdit + ' Editar</a>';
        h += '<a href="' + escapeHtml(cfg.agenda_url) + '" class="block ' + baseBtn + ' text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">' + icCal + ' Crear cita en agenda</a>';
        
        if (cfg.imagen_url) {
            h += '<a href="' + escapeHtml(cfg.imagen_url) + '" target="_blank" rel="noopener" class="block ' + baseBtn + ' text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30">' + icImg + ' Ver imagen</a>';
        }
        h += '<form method="POST" action="' + escapeHtml(cfg.destroy_url) + '" class="block ticket-menu-eliminar-form" onsubmit="return confirm(\'¿Eliminar este ticket?\');"><input type="hidden" name="_token" value="' + escapeHtml(cfg.csrf) + '"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="' + baseBtn + ' text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">' + icTrash + ' Eliminar</button></form>';
        return h;
    }

    document.querySelectorAll('.ticket-acciones-kebab').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!ticketMenuEl) return;
            var raw = this.getAttribute('data-menu-b64');
            if (!raw) return;
            var cfg;
            try {
                cfg = JSON.parse(base64ToUtf8(raw));
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo abrir el menú.' });
                return;
            }
            if (ticketMenuOpenBtn === this) {
                cerrarMenuTicketAcciones();
                return;
            }
            cerrarMenuTicketAcciones();
            ticketMenuOpenBtn = this;
            this.setAttribute('aria-expanded', 'true');
            ticketMenuEl.innerHTML = construirHtmlMenuTicket(cfg);
            posicionarMenuTicketAcciones(this);
        });
    });

    document.addEventListener('click', function(e) {
        if (!ticketMenuEl || ticketMenuEl.classList.contains('hidden')) return;
        if (ticketMenuOpenBtn && (ticketMenuOpenBtn.contains(e.target) || ticketMenuEl.contains(e.target))) return;
        cerrarMenuTicketAcciones();
    });

    window.addEventListener('scroll', function() { cerrarMenuTicketAcciones(); }, true);
    window.addEventListener('resize', cerrarMenuTicketAcciones);

    if (ticketMenuEl) {
        ticketMenuEl.addEventListener('click', function(e) {
            var t = e.target.closest('.ticket-menu-item');
            if (!t) return;
            var accion = t.getAttribute('data-accion');
            if (accion === 'facturar-ticket') {
                e.preventDefault();
                var urlFact = t.getAttribute('data-facturar-url');
                cerrarMenuTicketAcciones();
                Swal.fire({
                    title: 'Facturar ticket',
                    html: '<p class="text-sm text-gray-600 dark:text-gray-400 mb-2 text-left">Monto en guaraníes (entero).</p>' +
                        '<input id="swal-monto-ticket" type="number" min="1" step="1" class="swal2-input" placeholder="Ej. 50000" autocomplete="off">',
                    showCancelButton: true,
                    confirmButtonText: 'Generar factura',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#9333ea',
                    focusConfirm: false,
                    preConfirm: function() {
                        var el = document.getElementById('swal-monto-ticket');
                        var n = Number(el && el.value ? el.value : '');
                        if (!Number.isFinite(n) || n < 1) {
                            Swal.showValidationMessage('Ingrese un monto válido (mínimo 1).');
                            return false;
                        }
                        return Math.round(n);
                    }
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    var monto = result.value;
                    fetch(urlFact, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ monto: monto })
                    }).then(function(r) {
                        return r.json().then(function(data) {
                            if (!r.ok) {
                                var msg = data.message || (data.errors && data.errors.monto && data.errors.monto[0]) || 'Error al generar la factura.';
                                throw new Error(msg);
                            }
                            return data;
                        });
                    }).then(function() {
                        return Swal.fire({ icon: 'success', title: 'Listo', text: 'Factura interna generada correctamente.' });
                    }).then(function() {
                        window.location.reload();
                    }).catch(function(err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo generar la factura.' });
                    });
                });
            } else if (accion === 'estado') {
                e.preventDefault();
                cerrarMenuTicketAcciones();
                abrirModalEstadoTicket({
                    url: t.getAttribute('data-url'),
                    estado: t.getAttribute('data-estado') || '',
                    asignadoId: t.getAttribute('data-asignado-id') || '',
                    ticketId: t.getAttribute('data-ticket-id') || '',
                    cliente: t.getAttribute('data-cliente') || ''
                });
            } else if (accion === 'resuelto') {
                e.preventDefault();
                var urlR = t.getAttribute('data-url');
                cerrarMenuTicketAcciones();
                Swal.fire({
                    title: '¿Marcar como resuelto?',
                    text: 'El ticket pasará al estado Resuelto.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, resuelto',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280'
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    enviarEstadoTicket(urlR, 'resuelto').then(function() {
                        return Swal.fire({ icon: 'success', title: 'Guardado', text: 'Ticket marcado como resuelto.' });
                    }).then(function() {
                        window.location.reload();
                    }).catch(function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el estado.' });
                    });
                });
            }
        });
    }

    function enviarEstadoTicket(url, nuevoEstado, asignadoId) {
        var body = { estado: nuevoEstado };
        if (typeof asignadoId !== 'undefined') {
            body.asignado_id = (asignadoId === '' || asignadoId === null) ? null : Number(asignadoId);
        }
        return fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function(r) {
            if (!r.ok) throw new Error();
            return r.json();
        });
    }

    var estadoModalEl = document.getElementById('ticket-estado-modal');
    var estadoModalSub = document.getElementById('ticket-estado-modal-sub');
    var estadoModalSelect = document.getElementById('ticket-estado-tecnico');
    var estadoModalGuardar = document.getElementById('ticket-estado-modal-guardar');
    var estadoModalCtx = { url: '', estado: '' };
    var estadoCardSel = 'ring-2 ring-purple-500 border-purple-500';

    function marcarTarjetaEstado(estado) {
        estadoModalCtx.estado = estado || '';
        document.querySelectorAll('.ticket-estado-card').forEach(function(card) {
            var activo = card.getAttribute('data-estado') === estado;
            estadoCardSel.split(' ').forEach(function(cls) {
                card.classList.toggle(cls, activo);
            });
            var check = card.querySelector('.ticket-estado-check');
            if (check) check.classList.toggle('hidden', !activo);
            card.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
    }

    function cerrarModalEstadoTicket() {
        if (!estadoModalEl) return;
        estadoModalEl.classList.add('hidden');
        estadoModalEl.setAttribute('aria-hidden', 'true');
        estadoModalCtx = { url: '', estado: '' };
        if (estadoModalGuardar) estadoModalGuardar.disabled = false;
    }

    function abrirModalEstadoTicket(opts) {
        if (!estadoModalEl) return;
        estadoModalCtx = { url: opts.url || '', estado: opts.estado || '' };
        var partes = [];
        if (opts.ticketId) partes.push('Ticket #' + opts.ticketId);
        if (opts.cliente) partes.push(opts.cliente);
        if (estadoModalSub) estadoModalSub.textContent = partes.join(' · ');
        marcarTarjetaEstado(opts.estado || '');
        if (estadoModalSelect) estadoModalSelect.value = opts.asignadoId || '';
        estadoModalEl.classList.remove('hidden');
        estadoModalEl.setAttribute('aria-hidden', 'false');
    }

    document.querySelectorAll('.ticket-estado-card').forEach(function(card) {
        card.addEventListener('click', function() {
            marcarTarjetaEstado(this.getAttribute('data-estado') || '');
        });
    });

    function cerrarSiModalEstado(el) {
        if (el) el.addEventListener('click', cerrarModalEstadoTicket);
    }
    cerrarSiModalEstado(document.getElementById('ticket-estado-modal-backdrop'));
    cerrarSiModalEstado(document.getElementById('ticket-estado-modal-cerrar'));
    cerrarSiModalEstado(document.getElementById('ticket-estado-modal-cancelar'));

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (estadoModalEl && !estadoModalEl.classList.contains('hidden')) {
            cerrarModalEstadoTicket();
        }
    });

    if (estadoModalGuardar) {
        estadoModalGuardar.addEventListener('click', function() {
            if (!estadoModalCtx.url || !estadoModalCtx.estado) {
                Swal.fire({ icon: 'warning', title: 'Seleccione un estado', text: 'Elija el estado del ticket.' });
                return;
            }
            var asignadoId = estadoModalSelect ? estadoModalSelect.value : '';
            estadoModalGuardar.disabled = true;
            enviarEstadoTicket(estadoModalCtx.url, estadoModalCtx.estado, asignadoId).then(function() {
                cerrarModalEstadoTicket();
                return Swal.fire({ icon: 'success', title: 'Guardado', text: 'Estado y técnico actualizados.' });
            }).then(function() {
                window.location.reload();
            }).catch(function() {
                estadoModalGuardar.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el ticket.' });
            });
        });
    }

})();
</script>
@endpush
@endsection
