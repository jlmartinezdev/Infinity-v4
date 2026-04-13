@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Tickets</h1>
        <a href="{{ route('tickets.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
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
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 text-sm">
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
                                @endphp
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" class="btn-ver-detalle-ticket p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" title="Ver detalle" aria-label="Ver detalle"
                                        data-detalle-b64="{{ base64_encode(json_encode($detalleTicket, JSON_UNESCAPED_UNICODE)) }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <button type="button" class="btn-cambiar-estado p-2 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 hover:text-amber-800 dark:hover:text-amber-300 transition-colors" title="Cambiar estado" aria-label="Cambiar estado"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-estado="{{ $ticket->estado }}"
                                        data-url="{{ route('tickets.update-estado', $ticket) }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                    @if (! in_array($ticket->estado, ['resuelto', 'cerrado', 'cancelado'], true))
                                        <button type="button" class="btn-marcar-resuelto p-2 rounded-lg text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 hover:text-green-800 dark:hover:text-green-300 transition-colors" title="Marcar como resuelto" aria-label="Marcar como resuelto"
                                            data-url="{{ route('tickets.update-estado', $ticket) }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </button>
                                    @endif
                                    <a href="{{ route('tickets.crear-agenda', $ticket) }}" class="p-2 rounded-lg text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 hover:text-green-800 dark:hover:text-green-300 transition-colors" title="Crear cita en agenda" aria-label="Agenda">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </a>
                                    @if($ticket->imagen)
                                        <a href="{{ asset('storage/' . $ticket->imagen) }}" target="_blank" class="p-2 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-800 dark:hover:text-blue-300 transition-colors" title="Ver imagen" aria-label="Ver imagen">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </a>
                                    @endif
                                    <a href="{{ route('tickets.edit', $ticket) }}" class="p-2 rounded-lg text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 hover:text-purple-800 dark:hover:text-purple-300 transition-colors" title="Editar" aria-label="Editar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </a>
                                    <form action="{{ route('tickets.destroy', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este ticket?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 hover:text-red-800 dark:hover:text-red-300 transition-colors" title="Eliminar" aria-label="Eliminar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay tickets. <a href="{{ route('tickets.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear uno</a>.</td>
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

    document.querySelectorAll('.btn-ver-detalle-ticket').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var raw = this.getAttribute('data-detalle-b64');
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
                var bt = isDark ? 'border-gray-600' : 'border-gray-200';
                var lk = isDark ? 'text-purple-400 hover:text-purple-300' : 'text-purple-600 hover:text-purple-800';
                html += '<div class="mt-3 pt-2 border-t ' + bt + '"><a href="' + escapeHtml(t.imagen_url) + '" target="_blank" rel="noopener" class="' + lk + ' underline-offset-2 hover:underline">Ver imagen adjunta</a></div>';
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
        });
    });

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

    document.querySelectorAll('.btn-marcar-resuelto').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
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
                enviarEstadoTicket(url, 'resuelto').then(function() {
                    return Swal.fire({ icon: 'success', title: 'Guardado', text: 'Ticket marcado como resuelto.' });
                }).then(function() {
                    window.location.reload();
                }).catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el estado.' });
                });
            });
        });
    });

    document.querySelectorAll('.btn-cambiar-estado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var estadoActual = this.getAttribute('data-estado');

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
                var nuevoEstado = result.value;
                enviarEstadoTicket(url, nuevoEstado).then(function() {
                    return Swal.fire({ icon: 'success', title: 'Guardado', text: 'Estado actualizado correctamente.' });
                }).then(function() {
                    window.location.reload();
                }).catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el estado.' });
                });
            });
        });
    });
})();
</script>
@endpush
@endsection
