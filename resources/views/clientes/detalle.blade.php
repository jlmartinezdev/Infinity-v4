@extends('layouts.app')

@section('title', 'Detalle del cliente')

@push('styles')
    @include('partials.cliente-detalle-styles')
@endpush

@section('content')
@php
    $formasPago = \App\Models\Cobro::formasPago();
    $estadosTicket = \App\Models\Ticket::estados();
    $estadosServicio = \App\Models\Servicio::estadosDisponibles();
    $estadosFactura = \App\Models\FacturaInterna::estados();
    $u = auth()->user();
    $mapsUrl = null;
    if ($cliente->url_ubicacion) {
        $raw = trim((string) $cliente->url_ubicacion);
        if ($raw !== '') {
            if (preg_match('/^https?:\/\//i', $raw)) {
                $mapsUrl = $raw;
            } elseif (str_starts_with($raw, '//')) {
                $mapsUrl = 'https:'.$raw;
            } elseif (preg_match('/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/', $raw, $m)) {
                $mapsUrl = 'https://www.google.com/maps?q='.$m[1].','.$m[2];
            } else {
                $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($raw);
            }
        }
    }
    $ticketsActivos = $tickets->whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado']);
    $estadoCliente = mb_strtolower(trim((string) ($cliente->estado ?? '')));
    $clienteActivo = in_array($estadoCliente, ['activo', 'active', '1', ''], true) || $estadoCliente === '';
    $tieneWhatsapp = ! empty($whatsappVista['tiene'] ?? false);
@endphp

<div class="cliente-detalle-page cliente-detalle-container pb-8">
    <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('clientes.index') }}" class="text-sm text-slate-400 hover:text-blue-400 mb-1 inline-block">&larr; Volver a clientes</a>
            <h1 class="text-2xl font-bold text-white">Detalle general del cliente</h1>
            <p class="text-sm text-slate-400">{{ $cliente->nombre }} {{ $cliente->apellido }} · #{{ $cliente->cliente_id }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('clientes.acciones', $cliente) }}"
                class="inline-flex items-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-800">
                Acciones
            </a>
            @if($u?->tienePermiso('clientes.editar'))
                <a href="{{ route('clientes.edit', $cliente) }}"
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                    Editar cliente
                </a>
            @endif
        </div>
    </div>

    <div class="cliente-detalle-layout">
        <main class="min-w-0 space-y-5">
            {{-- Resumen --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="cd-card cd-summary">
                    <p class="cd-label">Pendiente de pago</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums {{ ($totalPendientePago ?? 0) > 0 ? 'text-amber-400' : 'text-white' }}">
                        {{ number_format((float) ($totalPendientePago ?? 0), 0, ',', '.') }} PYG
                    </p>
                    <p class="mt-1 text-xs text-slate-400">Saldo en facturas internas vigentes</p>
                    @if($u?->tienePermiso('pagos-pendientes.ver') && ($totalPendientePago ?? 0) > 0)
                        <a href="{{ route('factura-internas.pendientes', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}"
                            class="cd-link inline-block mt-2 text-xs">Ver en pendientes</a>
                    @endif
                    <svg class="cd-summary__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/></svg>
                </div>
                <div class="cd-card cd-summary">
                    <div class="flex items-start justify-between gap-3">
                        <p class="cd-label">Saldo a favor</p>
                        @if($esAdministrador && $cliente->servicios->isNotEmpty())
                            <button type="button" id="btn-toggle-saldo-favor"
                                class="text-xs font-medium text-blue-400 hover:underline shrink-0">
                                Ajustar manualmente
                            </button>
                        @endif
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums {{ ($totalSaldoFavor ?? 0) > 0 ? 'text-emerald-400' : 'text-white' }}">
                        {{ number_format((float) ($totalSaldoFavor ?? 0), 0, ',', '.') }} PYG
                    </p>
                    <p class="mt-1 text-xs text-slate-400">Crédito por cobros adelantados</p>
                    <svg class="cd-summary__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.756a4.5 4.5 0 100 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

                    @if($esAdministrador && $cliente->servicios->isNotEmpty())
                        <form id="form-saldo-favor" action="{{ route('clientes.actualizar-saldo-a-favor', $cliente) }}" method="POST"
                            class="hidden mt-4 rounded-lg border border-slate-600 bg-slate-900/50 p-4 space-y-3 relative z-10">
                            @csrf
                            @method('PUT')
                            @foreach($cliente->servicios as $sSaldo)
                                <div>
                                    <label for="saldo_servicio_{{ $sSaldo->servicio_id }}" class="block text-xs text-slate-400 mb-1">
                                        Servicio #{{ $sSaldo->servicio_id }} — {{ $sSaldo->plan?->nombre ?? 'Sin plan' }}
                                    </label>
                                    <input type="number" name="saldos[{{ $sSaldo->servicio_id }}]" id="saldo_servicio_{{ $sSaldo->servicio_id }}"
                                        value="{{ old('saldos.'.$sSaldo->servicio_id, (float) ($sSaldo->saldo_a_favor ?? 0)) }}"
                                        min="0" step="1"
                                        class="w-full px-3 py-2 rounded-lg border border-slate-600 bg-slate-800 text-white text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                            @endforeach
                            <input type="text" name="motivo" value="{{ old('motivo') }}" maxlength="500" placeholder="Motivo del ajuste (opcional)"
                                class="w-full px-3 py-2 rounded-lg border border-slate-600 bg-slate-800 text-white text-sm focus:border-blue-500 focus:outline-none">
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500">Guardar</button>
                                <button type="button" id="btn-cancel-saldo-favor" class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-600">Cancelar</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Datos --}}
            <div class="cd-card p-5">
                <h2 class="text-base font-semibold text-white mb-4">Datos del cliente</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="cd-label">Cédula / documento</dt>
                        <dd class="mt-1 font-medium text-slate-100">{{ $cliente->cedula ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="cd-label">Teléfono</dt>
                        <dd class="mt-1 flex items-center gap-2 text-slate-100">
                            {{ $cliente->telefono ?: '—' }}
                            @if($tieneWhatsapp && $cliente->telefono)
                                <span class="text-emerald-400" title="WhatsApp vinculado">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12c0 1.931.494 3.82 1.435 5.488L2 22l4.675-1.423A9.956 9.956 0 0012 22c5.514 0 10-4.486 10-10S17.514 2 12 2z"/></svg>
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="cd-label">Email</dt>
                        <dd class="mt-1 break-all text-slate-100">{{ $cliente->email ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <dt class="cd-label">Dirección</dt>
                        <dd class="mt-1 text-slate-100">{{ $cliente->direccion ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="cd-label">Estado</dt>
                        <dd class="mt-1">
                            @if($clienteActivo)
                                <span class="cd-pill cd-pill--ok">Activo</span>
                            @else
                                <span class="cd-pill cd-pill--warn capitalize">{{ $cliente->estado ?: '—' }}</span>
                            @endif
                        </dd>
                    </div>
                    @if($mapsUrl)
                        <div>
                            <dt class="cd-label">Ubicación</dt>
                            <dd class="mt-1">
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="cd-link inline-flex items-center gap-1">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Ver en mapa
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="cd-label">Calificación de pago</dt>
                        <dd class="mt-1 text-slate-100">{{ $cliente->calificacion_pago_label ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Servicios --}}
            <div class="cd-card p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-base font-semibold text-white">Servicios asociados</h2>
                    @if($u?->tienePermiso('servicios.crear'))
                        <a href="{{ route('servicios.create', ['cliente_id' => $cliente->cliente_id]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-700 text-slate-200 hover:bg-slate-600" title="Nuevo servicio">+</a>
                    @endif
                </div>
                @if($cliente->servicios->isEmpty())
                    <p class="text-sm text-slate-400">No hay servicios registrados.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-slate-600/80">
                        <table class="cd-table min-w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Plan</th>
                                    <th>Instalación</th>
                                    <th>IP</th>
                                    <th>PPPoE</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cliente->servicios as $s)
                                    <tr>
                                        <td>{{ $s->servicio_id }}</td>
                                        <td>{{ $s->plan?->nombre ?? '—' }}</td>
                                        <td class="text-slate-400">{{ $s->fecha_instalacion?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="font-mono text-xs">
                                            @if($s->ip && $u?->tienePermiso('servicios.ver'))
                                                <a href="{{ route('servicios.herramientas-red', $s) }}" class="cd-link">{{ $s->ip }}</a>
                                            @else
                                                {{ $s->ip ?? '—' }}
                                            @endif
                                        </td>
                                        <td class="font-mono text-xs text-slate-400">{{ $s->usuario_pppoe ?? '—' }}</td>
                                        <td>{{ $estadosServicio[$s->estado] ?? $s->estado }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Facturas --}}
            <div class="cd-card p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-white">Facturas generadas</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Últimas {{ ($facturasInternas ?? collect())->count() }} facturas</p>
                    </div>
                    @if($u?->tienePermiso('factura-interna.ver'))
                        <a href="{{ route('factura-internas.index', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}" class="cd-link text-sm">Ver todas</a>
                    @endif
                </div>
                @if(($facturasInternas ?? collect())->isEmpty())
                    <p class="text-sm text-slate-400">No hay facturas registradas.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-slate-600/80">
                        <table class="cd-table min-w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Emisión</th>
                                    <th>Período / tipo</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Pendiente</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($facturasInternas as $factura)
                                    @php $saldoFactura = (float) $factura->saldo_pendiente; @endphp
                                    <tr>
                                        <td class="font-medium">
                                            @if($u?->tienePermiso('factura-interna.ver'))
                                                <a href="{{ route('factura-internas.show', $factura) }}" class="cd-link">#{{ $factura->id }}</a>
                                            @else
                                                #{{ $factura->id }}
                                            @endif
                                        </td>
                                        <td class="text-slate-400 whitespace-nowrap">{{ $factura->fecha_emision?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="text-slate-400">
                                            @if($factura->esServicioEspecial())
                                                {{ $factura->etiquetaTipoFactura() }}
                                            @elseif($factura->periodo_desde && $factura->periodo_hasta)
                                                {{ $factura->periodo_desde->format('d/m/Y') }} – {{ $factura->periodo_hasta->format('d/m/Y') }}
                                            @else — @endif
                                        </td>
                                        <td class="text-right tabular-nums">{{ number_format((float) $factura->total, 0, ',', '.') }} PYG</td>
                                        <td class="text-right tabular-nums font-medium {{ $saldoFactura > 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                                            {{ number_format($saldoFactura, 0, ',', '.') }} PYG
                                        </td>
                                        <td>
                                            @if($saldoFactura > 0)
                                                <span class="cd-pill cd-pill--warn">{{ $estadosFactura[$factura->estado] ?? 'Pendiente' }}</span>
                                            @else
                                                <span class="text-slate-400">{{ $estadosFactura[$factura->estado] ?? $factura->estado }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Historial compacto --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="cd-card p-5 min-w-0">
                    <h2 class="text-base font-semibold text-white mb-1">Historial de pagos</h2>
                    @if($cobros->isEmpty())
                        <div class="cd-empty">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.375M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                            <p>No hay cobros registrados aún.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto max-h-52 overflow-y-auto rounded-lg border border-slate-600/80 mt-3">
                            <table class="cd-table min-w-full">
                                <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Recibo</th></tr></thead>
                                <tbody>
                                    @foreach($cobros->take(8) as $cobro)
                                        <tr>
                                            <td class="whitespace-nowrap text-slate-400">{{ $cobro->fecha_pago?->timezone(config('app.timezone'))->format('d/m/Y') ?? '—' }}</td>
                                            <td class="text-right tabular-nums">{{ number_format((float) $cobro->monto, 0, ',', '.') }}</td>
                                            <td>
                                                @if($u?->tienePermiso('cobros.ver'))
                                                    <a href="{{ route('cobros.show', $cobro) }}" class="cd-link font-mono text-xs">{{ $cobro->numero_recibo }}</a>
                                                @else
                                                    <span class="font-mono text-xs">{{ $cobro->numero_recibo }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="cd-card p-5 min-w-0">
                    <h2 class="text-base font-semibold text-white mb-1">Historial de tickets</h2>
                    @if($ticketsActivos->isEmpty() && $tickets->isEmpty())
                        <div class="cd-empty">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/></svg>
                            <p>No hay tickets activos para este cliente.</p>
                        </div>
                    @elseif($tickets->isEmpty())
                        <div class="cd-empty"><p>No hay tickets para este cliente.</p></div>
                    @else
                        <div class="overflow-x-auto max-h-52 overflow-y-auto rounded-lg border border-slate-600/80 mt-3">
                            <table class="cd-table min-w-full">
                                <thead><tr><th>#</th><th>Fecha</th><th>Asunto</th><th>Estado</th></tr></thead>
                                <tbody>
                                    @foreach($tickets->take(8) as $t)
                                        <tr>
                                            <td>
                                                @if($u?->tienePermiso('tickets.crear'))
                                                    <a href="{{ route('tickets.edit', $t) }}" class="cd-link font-medium">#{{ $t->id }}</a>
                                                @else #{{ $t->id }} @endif
                                            </td>
                                            <td class="text-slate-400 whitespace-nowrap">{{ $t->created_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="max-w-[8rem] truncate" title="{{ $t->ticketAsunto?->nombre }}">{{ $t->ticketAsunto?->nombre ?? '—' }}</td>
                                            <td>{{ $estadosTicket[$t->estado] ?? $t->estado }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </main>

        <aside class="cliente-detalle-chat">
            @include('partials.cliente-whatsapp-sidebar', [
                'cliente' => $cliente,
                'whatsappVista' => $whatsappVista ?? ['tiene' => false],
            ])
        </aside>
    </div>
</div>

@if($esAdministrador && $cliente->servicios->isNotEmpty())
<script>
(function() {
    var form = document.getElementById('form-saldo-favor');
    var toggleBtn = document.getElementById('btn-toggle-saldo-favor');
    var cancelBtn = document.getElementById('btn-cancel-saldo-favor');
    var shouldOpen = {{ $errors->has('saldos.*') || $errors->has('saldos') || old('motivo') ? 'true' : 'false' }};
    function setOpen(open) {
        if (!form) return;
        form.classList.toggle('hidden', !open);
        if (toggleBtn) toggleBtn.textContent = open ? 'Ocultar ajuste' : 'Ajustar manualmente';
    }
    toggleBtn?.addEventListener('click', function() { setOpen(form.classList.contains('hidden')); });
    cancelBtn?.addEventListener('click', function() { setOpen(false); });
    if (shouldOpen) setOpen(true);
})();
</script>
@endif
@endsection
