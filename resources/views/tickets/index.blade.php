@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Tickets</h1>
        <a href="{{ route('tickets.create') }}"
            class="inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:bg-purple-600 dark:hover:bg-purple-500 dark:focus:ring-purple-400 dark:focus:ring-offset-gray-900">
            Nuevo ticket
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('tickets.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
                <div class="sm:w-48">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
                    <select name="estado" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach (App\Models\Ticket::estados() as $key => $label)
                            <option value="{{ $key }}" {{ request('estado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-56">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Asunto</label>
                    <select name="ticket_asunto_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach ($asuntos as $a)
                            <option value="{{ $a->id }}" {{ request('ticket_asunto_id') == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-48">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</label>
                    <select name="cliente_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->cliente_id }}" {{ request('cliente_id') == $c->cliente_id ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end pb-0.5">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300 select-none">
                        <input type="checkbox" name="ocultar_resuelto_cerrado" value="1" {{ request()->boolean('ocultar_resuelto_cerrado') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500 dark:bg-gray-700">
                        <span>Ocultar resuelto y cerrado</span>
                    </label>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:bg-purple-600 dark:hover:bg-purple-500 dark:focus:ring-purple-400 dark:focus:ring-offset-gray-900">
                        Filtrar
                    </button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asunto</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cliente / Pedido</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Prioridad</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reportado</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asignado</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fecha</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cobro</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($tickets as $ticket)
                        @php
                            $ipClienteTicket = $ticket->cliente
                                ? $ticket->cliente->servicios
                                    ->filter(fn ($s) => filled($s->ip))
                                    ->sortBy(fn ($s) => $s->estado === \App\Models\Servicio::ESTADO_ACTIVO ? 0 : 1)
                                    ->first()?->ip
                                : null;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $ticket->id }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $ticket->ticketAsunto?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($ticket->cliente)
                                    <a href="{{ route('clientes.edit', $ticket->cliente) }}" class="text-purple-600 dark:text-purple-400 hover:underline">{{ $ticket->cliente->nombre }} {{ $ticket->cliente->apellido }}</a>
                                    @if($ipClienteTicket)
                                        <div class="mt-1">
                                            <a href="http://{{ $ipClienteTicket }}" target="_blank" rel="noopener noreferrer" title="Abrir en el navegador (equipo del cliente)" class="font-mono text-xs text-cyan-600 dark:text-cyan-400 hover:underline">{{ $ipClienteTicket }}</a>
                                        </div>
                                    @endif
                                @else
                                    —
                                @endif
                                @if($ticket->pedido_id)
                                    <span class="text-gray-500 dark:text-gray-400"> · Pedido #{{ $ticket->pedido_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php $prioridades = App\Models\Ticket::prioridades(); @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if(($ticket->prioridad ?? 'media') === 'alta') bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300
                                    @elseif(($ticket->prioridad ?? '') === 'baja') bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300
                                    @else bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 @endif">
                                    {{ $prioridades[$ticket->prioridad ?? 'media'] ?? $ticket->prioridad }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                @php $reportado = App\Models\Ticket::reportadoDesdeOpciones(); @endphp
                                {{ $ticket->reportado_desde ? ($reportado[$ticket->reportado_desde] ?? $ticket->reportado_desde) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @php $estados = App\Models\Ticket::estados(); @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($ticket->estado === 'resuelto' || $ticket->estado === 'cerrado') bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300
                                    @elseif($ticket->estado === 'cancelado') bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300
                                    @elseif($ticket->estado === 'en_proceso') bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300
                                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                                    {{ $estados[$ticket->estado] ?? $ticket->estado }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $ticket->asignado?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
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
                            </td>
                            <td class="px-4 py-3 text-right">
                                @php
                                    $_est = App\Models\Ticket::estados();
                                    $_pri = App\Models\Ticket::prioridades();
                                    $_rep = App\Models\Ticket::reportadoDesdeOpciones();
                                    $detalleTicket = [
                                        'id' => $ticket->id,
                                        'asunto' => $ticket->ticketAsunto?->nombre,
                                        'cliente' => $ticket->cliente ? trim($ticket->cliente->nombre.' '.($ticket->cliente->apellido ?? '')) : null,
                                        'ip_cliente' => $ipClienteTicket,
                                        'pedido_id' => $ticket->pedido_id,
                                        'descripcion' => $ticket->descripcion,
                                        'observaciones' => $ticket->observaciones,
                                        'estado' => $_est[$ticket->estado] ?? $ticket->estado,
                                        'prioridad' => $_pri[$ticket->prioridad ?? 'media'] ?? $ticket->prioridad,
                                        'reportado' => $ticket->reportado_desde ? ($_rep[$ticket->reportado_desde] ?? $ticket->reportado_desde) : null,
                                        'creador' => $ticket->usuario?->name,
                                        'asignado' => $ticket->asignado?->name,
                                        'imagen_url' => $ticket->imagen ? asset('storage/'.$ticket->imagen) : null,
                                        'created_at' => $ticket->created_at?->format('d/m/Y H:i'),
                                        'updated_at' => $ticket->updated_at?->format('d/m/Y H:i'),
                                        'fecha_cierre' => $ticket->fecha_cierre?->format('d/m/Y H:i'),
                                    ];
                                    $canServicioCrear = auth()->user()?->tienePermiso('servicios.crear') ?? false;
                                    $canFacturaInternaCrear = auth()->user()?->tienePermiso('factura-interna.crear') ?? false;
                                    $serviciosCliente = $ticket->cliente?->servicios ?? collect();
                                    $servicioMigrar = $canServicioCrear
                                        ? $serviciosCliente->first(fn ($s) => $s->pool?->router)
                                        : null;
                                    $menuCfg = [
                                        'estado' => $ticket->estado,
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
                                        'puede_facturar_ticket' => $canFacturaInternaCrear && $ticket->cliente_id && ! $ticket->factura_interna_id,
                                        'facturar_url' => $canFacturaInternaCrear ? route('tickets.facturar', $ticket) : '',
                                    ];
                                @endphp
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button type="button"
                                        class="btn-ver-detalle-ticket p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                        title="Ver detalle"
                                        aria-label="Ver detalle"
                                        data-detalle-b64="{{ base64_encode(json_encode($detalleTicket, JSON_UNESCAPED_UNICODE)) }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button type="button"
                                        class="ticket-acciones-kebab p-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        title="Acciones"
                                        aria-label="Menú de acciones"
                                        aria-expanded="false"
                                        data-menu-b64="{{ base64_encode(json_encode($menuCfg, JSON_UNESCAPED_UNICODE)) }}">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay tickets. <a href="{{ route('tickets.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear uno</a>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Menú acciones (mismo patrón visual que servicios: kebab + panel fijo) --}}
<div id="ticket-acciones-dropdown" class="fixed z-[9999] hidden py-1 min-w-[220px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg" role="menu" aria-hidden="true"></div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    var estados = @json(App\Models\Ticket::estados());
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var filtroOcultarStorageKey = 'tickets_filtro_ocultar_resuelto_cerrado';

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

    function abrirDetalleTicketDesdeB64(raw) {
        if (!raw) return;
        var t;
        try {
            t = JSON.parse(base64ToUtf8(raw));
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo leer el detalle.' });
            return;
        }
        var isDark = document.documentElement.classList.contains('dark');
        var wrap = isDark ? 'text-left text-sm max-h-[60vh] overflow-y-auto text-gray-100' : 'text-left text-sm max-h-[60vh] overflow-y-auto text-gray-900';
        var html = '<div class="' + wrap + '">';
        html += filaDetalle('Asunto', t.asunto, isDark);
        html += filaDetalle('Cliente', t.cliente, isDark);
        if (t.ip_cliente) {
            var bt = isDark ? 'border-gray-700' : 'border-gray-100';
            var lk = isDark ? 'text-cyan-400 hover:text-cyan-300' : 'text-cyan-600 hover:text-cyan-800';
            html += '<div class="flex flex-col sm:flex-row sm:gap-2 py-1.5 border-b ' + bt + ' last:border-0"><span class="' + (isDark ? 'text-gray-400' : 'text-gray-500') + ' shrink-0 min-w-[7rem]">IP cliente</span><span class="' + (isDark ? 'text-gray-100' : 'text-gray-900') + ' break-words"><a href="http://' + escapeHtml(t.ip_cliente) + '" target="_blank" rel="noopener noreferrer" class="' + lk + ' underline-offset-2 hover:underline font-mono">' + escapeHtml(t.ip_cliente) + '</a></span></div>';
        }
        if (t.pedido_id) html += filaDetalle('Pedido', '#' + t.pedido_id, isDark);
        html += filaDetalle('Estado', t.estado, isDark);
        html += filaDetalle('Prioridad', t.prioridad, isDark);
        html += filaDetalle('Reportado desde', t.reportado, isDark);
        html += filaDetalle('Creado por', t.creador, isDark);
        html += filaDetalle('Asignado', t.asignado, isDark);
        html += filaDetalle('Alta', t.created_at, isDark);
        html += filaDetalle('Última actualización', t.updated_at, isDark);
        if (t.fecha_cierre) html += filaDetalle('Cierre', t.fecha_cierre, isDark);
        html += filaDetalle('Descripción', t.descripcion, isDark);
        html += filaDetalle('Observaciones', t.observaciones, isDark);
        if (t.imagen_url) {
            var bt2 = isDark ? 'border-gray-600' : 'border-gray-200';
            var lk2 = isDark ? 'text-purple-400 hover:text-purple-300' : 'text-purple-600 hover:text-purple-800';
            html += '<div class="mt-3 pt-2 border-t ' + bt2 + '"><a href="' + escapeHtml(t.imagen_url) + '" target="_blank" rel="noopener" class="' + lk2 + ' underline-offset-2 hover:underline">Ver imagen adjunta</a></div>';
        }
        html += '</div>';
        Swal.fire({
            title: 'Ticket #' + t.id,
            html: html,
            width: '36rem',
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
        var baseBtn = 'w-full px-4 py-2.5 text-left text-sm flex items-center gap-2';
        var h = '';
        h += '<button type="button" class="' + baseBtn + ' text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 ticket-menu-item" data-accion="estado" data-url="' + escapeHtml(cfg.update_estado_url) + '" data-estado="' + escapeHtml(cfg.estado) + '">' + icLista + ' Cambiar estado</button>';
        if (cfg.puede_facturar_ticket && cfg.facturar_url) {
            h += '<button type="button" class="' + baseBtn + ' text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 ticket-menu-item" data-accion="facturar-ticket" data-facturar-url="' + escapeHtml(cfg.facturar_url) + '">' + icDoc + ' Crear factura por ticket</button>';
        }
        if (cfg.puede_marcar_resuelto) {
            h += '<button type="button" class="' + baseBtn + ' text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 ticket-menu-item" data-accion="resuelto" data-url="' + escapeHtml(cfg.update_estado_url) + '">' + icCheck + ' Marcar como resuelto</button>';
        }
        h += '<a href="' + escapeHtml(cfg.edit_ticket_url) + '" class="block ' + baseBtn + ' text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30">' + icEdit + ' Editar</a>';
        h += '<a href="' + escapeHtml(cfg.agenda_url) + '" class="block ' + baseBtn + ' text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">' + icCal + ' Crear cita en agenda</a>';
        if (cfg.migrar_url) {
            h += '<a href="' + escapeHtml(cfg.migrar_url) + '" class="block ' + baseBtn + ' text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">' + icMig + ' Migrar a otro nodo</a>';
        }
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
                var urlE = t.getAttribute('data-url');
                var estadoActual = t.getAttribute('data-estado');
                Swal.fire({
                    title: 'Cambiar estado del ticket',
                    html: '<p class="text-gray-600 text-sm mb-3">Seleccione el nuevo estado:</p><select id="swal-estado" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">' +
                        Object.keys(estados).map(function(k) {
                            return '<option value="' + k + '"' + (k === estadoActual ? ' selected' : '') + '>' + estados[k] + '</option>';
                        }).join('') + '</select>',
                    showCancelButton: true,
                    confirmButtonText: 'Actualizar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#9333ea',
                    focusConfirm: false,
                    preConfirm: function() {
                        return document.getElementById('swal-estado').value;
                    }
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    enviarEstadoTicket(urlE, result.value).then(function() {
                        return Swal.fire({ icon: 'success', title: 'Guardado', text: 'Estado actualizado correctamente.' });
                    }).then(function() {
                        window.location.reload();
                    }).catch(function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el estado.' });
                    });
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

    function enviarEstadoTicket(url, nuevoEstado) {
        return fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ estado: nuevoEstado })
        }).then(function(r) {
            if (!r.ok) throw new Error();
            return r.json();
        });
    }

})();
</script>
@endpush
@endsection
