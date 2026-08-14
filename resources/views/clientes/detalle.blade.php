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
    $facturasPendientes = ($facturasInternas ?? collect())->filter(fn ($f) => (float) $f->saldo_pendiente > 0.009)->values();
    $cdTab = request()->query('tab', 'cliente');
    $cdTabsPermitidas = ['cliente', 'whatsapp', 'servicio', 'tickets', 'facturas', 'recordatorios', 'consumo', 'red'];
    if ($cdTab === 'acciones') {
        $cdTab = 'cliente';
    }
    if ($cdTab === 'trafico') {
        $cdTab = 'red';
    }
    if (! in_array($cdTab, $cdTabsPermitidas, true)) {
        $cdTab = 'cliente';
    }
    $facturaAccion = $facturasPendientes->sortBy(fn ($f) => [$f->fecha_emision?->timestamp ?? 0, $f->id])->first();
@endphp

<div class="cliente-detalle-page cliente-detalle-container pb-8">
    <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 mb-1 inline-block">&larr; Volver a clientes</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Detalle general del cliente</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cliente->nombre }} {{ $cliente->apellido }} · #{{ $cliente->cliente_id }}</p>
        </div>
    </div>

    <div class="cliente-detalle-layout">
        <main class="min-w-0 space-y-5">
            {{-- Resumen --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="cd-card cd-summary">
                    <p class="cd-label">Pendiente de pago</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums {{ ($totalPendientePago ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format((float) ($totalPendientePago ?? 0), 0, ',', '.') }} PYG
                    </p>
                    <p class="mt-1 text-xs cd-muted">Saldo en facturas internas vigentes</p>
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
                                class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline shrink-0">
                                Ajustar manualmente
                            </button>
                        @endif
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums {{ ($totalSaldoFavor ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format((float) ($totalSaldoFavor ?? 0), 0, ',', '.') }} PYG
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Crédito por cobros adelantados</p>
                    @if(($totalSaldoFavor ?? 0) > 0 && ($totalPendientePago ?? 0) > 0 && $u?->tienePermiso('cobros.crear'))
                        <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">
                            Hay facturas pendientes: abrí la factura y usá
                            <strong>Aplicar saldo a favor</strong>.
                            @if($u?->tienePermiso('pagos-pendientes.ver'))
                                <a href="{{ route('factura-internas.pendientes', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}"
                                    class="cd-link underline">Ver pendientes</a>
                            @endif
                        </p>
                    @endif
                    <svg class="cd-summary__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.756a4.5 4.5 0 100 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

                    @if($esAdministrador && $cliente->servicios->isNotEmpty())
                        <form id="form-saldo-favor" action="{{ route('clientes.actualizar-saldo-a-favor', $cliente) }}" method="POST"
                            class="hidden mt-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 p-4 space-y-3 relative z-10">
                            @csrf
                            @method('PUT')
                            @foreach($cliente->servicios as $sSaldo)
                                <div>
                                    <label for="saldo_servicio_{{ $sSaldo->servicio_id }}" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        Servicio #{{ $sSaldo->servicio_id }} — {{ $sSaldo->plan?->nombre ?? 'Sin plan' }}
                                    </label>
                                    <input type="number" name="saldos[{{ $sSaldo->servicio_id }}]" id="saldo_servicio_{{ $sSaldo->servicio_id }}"
                                        value="{{ old('saldos.'.$sSaldo->servicio_id, (float) ($sSaldo->saldo_a_favor ?? 0)) }}"
                                        min="0" step="1"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                            @endforeach
                            <input type="text" name="motivo" value="{{ old('motivo') }}" maxlength="500" placeholder="Motivo del ajuste (opcional)"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:border-blue-500 focus:outline-none">
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500">Guardar</button>
                                <button type="button" id="btn-cancel-saldo-favor" class="rounded-lg bg-gray-200 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</button>
                            </div>
                        </form>
                    @endif
                </div>
                <div class="cd-card cd-summary">
                    <p class="cd-label">Puntos Loyalty</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-violet-600 dark:text-violet-300">
                        {{ number_format((int) ($loyaltySaldo ?? 0)) }}
                        <span class="text-base font-normal text-gray-500 dark:text-gray-400">pts</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ ($loyaltyCanjes ?? collect())->count() }} canje(s) reciente(s)
                        @if($u?->tienePermiso('loyalty-puntos.ver'))
                            · <a href="{{ route('loyalty.puntos.index', ['cedula' => $cliente->cedula]) }}" class="cd-link">Gestionar</a>
                        @endif
                    </p>
                    <svg class="cd-summary__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
                </div>
            </div>

            <div id="cliente-detalle-tabs">
            <div class="cd-tabs" role="tablist" aria-label="Detalle del cliente">
                <button type="button" class="cd-tab {{ $cdTab === 'cliente' ? 'is-active' : '' }}" data-cd-tab="cliente" role="tab" aria-selected="{{ $cdTab === 'cliente' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    Cliente
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'whatsapp' ? 'is-active' : '' }}" data-cd-tab="whatsapp" role="tab" aria-selected="{{ $cdTab === 'whatsapp' ? 'true' : 'false' }}">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                    WhatsApp
                    @if(! empty($whatsappVista['mensajes']))
                        <span class="cd-tab__badge">{{ count($whatsappVista['mensajes']) }}</span>
                    @endif
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'servicio' ? 'is-active' : '' }}" data-cd-tab="servicio" role="tab" aria-selected="{{ $cdTab === 'servicio' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                    Servicio
                    @if($cliente->servicios->isNotEmpty())
                        <span class="cd-tab__badge">{{ $cliente->servicios->count() }}</span>
                    @endif
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'tickets' ? 'is-active' : '' }}" data-cd-tab="tickets" role="tab" aria-selected="{{ $cdTab === 'tickets' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    Tickets
                    @if($tickets->isNotEmpty())
                        <span class="cd-tab__badge">{{ $tickets->count() }}</span>
                    @endif
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'facturas' ? 'is-active' : '' }}" data-cd-tab="facturas" role="tab" aria-selected="{{ $cdTab === 'facturas' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                    Facturas
                    @if(($facturasInternas ?? collect())->isNotEmpty())
                        <span class="cd-tab__badge">{{ $facturasInternas->count() }}</span>
                    @endif
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'recordatorios' ? 'is-active' : '' }}" data-cd-tab="recordatorios" role="tab" aria-selected="{{ $cdTab === 'recordatorios' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                    Recordatorios
                    @if($facturasPendientes->isNotEmpty())
                        <span class="cd-tab__badge">{{ $facturasPendientes->count() }}</span>
                    @endif
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'consumo' ? 'is-active' : '' }}" data-cd-tab="consumo" role="tab" aria-selected="{{ $cdTab === 'consumo' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    Consumo
                </button>
                <button type="button" class="cd-tab {{ $cdTab === 'red' ? 'is-active' : '' }}" data-cd-tab="red" role="tab" aria-selected="{{ $cdTab === 'red' ? 'true' : 'false' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                    Herramienta de red
                </button>
            </div>

            <div class="cd-tab-panel" data-cd-panel="cliente" role="tabpanel" @if($cdTab !== 'cliente') hidden @endif>
            <div class="cd-card">
                <div class="cd-panel__head">
                    <h2>Datos del cliente</h2>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('clientes.editar'))
                            <a href="{{ route('clientes.edit', $cliente) }}" class="cd-header-btn cd-header-btn--primary">Editar</a>
                        @endif
                        @if($u?->tienePermiso('pedidos.crear') || $u?->tienePermiso('clientes-pedidos.crear'))
                            <a href="{{ route('pedidos.create') }}" class="cd-header-btn">Nuevo pedido</a>
                        @endif
                        @if($u?->tienePermiso('clientes.eliminar'))
                            <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="js-swal-confirm" data-swal-title="¿Eliminar este cliente?" data-swal-text="Esta acción no se puede deshacer." data-swal-confirm="Sí, eliminar" data-swal-icon="warning" data-swal-color="#dc2626">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cd-header-btn cd-header-btn--danger">Eliminar</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="cd-panel__body">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-3 text-sm">
                        <div>
                            <dt class="cd-label">Cédula / documento</dt>
                            <dd class="mt-0.5 font-medium cd-value">{{ $cliente->cedula ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="cd-label">Teléfono</dt>
                            <dd class="mt-0.5 flex items-center gap-2 cd-value">
                                {{ $cliente->telefono ?: '—' }}
                                @if($tieneWhatsapp && $cliente->telefono)
                                    <span class="text-emerald-600 dark:text-emerald-400" title="WhatsApp vinculado">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12c0 1.931.494 3.82 1.435 5.488L2 22l4.675-1.423A9.956 9.956 0 0012 22c5.514 0 10-4.486 10-10S17.514 2 12 2z"/></svg>
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="cd-label">Email</dt>
                            <dd class="mt-0.5 break-all cd-value">{{ $cliente->email ?: '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <dt class="cd-label">Dirección</dt>
                            <dd class="mt-0.5 cd-value">{{ $cliente->direccion ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="cd-label">Estado</dt>
                            <dd class="mt-0.5">
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
                                <dd class="mt-0.5">
                                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="cd-link inline-flex items-center gap-1">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        Ver en mapa
                                    </a>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="cd-label">Calificación de pago</dt>
                            <dd class="mt-0.5 cd-value">{{ $cliente->calificacion_pago_label ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="cd-card min-w-0">
                    <div class="cd-panel__head">
                        <h2>Movimientos de puntos</h2>
                        <span class="text-xs text-violet-600 dark:text-violet-300 tabular-nums">{{ number_format((int) ($loyaltySaldo ?? 0)) }} pts</span>
                    </div>
                    @if(($loyaltyMovimientos ?? collect())->isEmpty())
                        <div class="cd-empty"><p>Sin movimientos de puntos.</p></div>
                    @else
                        <div class="cd-table-scroll">
                            <table class="cd-table">
                                <thead><tr><th>Fecha</th><th>Pts</th><th>Concepto</th></tr></thead>
                                <tbody>
                                    @foreach($loyaltyMovimientos as $mov)
                                        <tr>
                                            <td class="whitespace-nowrap cd-muted">{{ $mov->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                            <td class="tabular-nums font-medium {{ $mov->puntos >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $mov->puntos >= 0 ? '+'.$mov->puntos : $mov->puntos }}
                                            </td>
                                            <td class="max-w-[10rem] truncate" title="{{ $mov->concepto }}">{{ $mov->concepto }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="cd-card min-w-0">
                    <div class="cd-panel__head"><h2>Canjes</h2></div>
                    @if(($loyaltyCanjes ?? collect())->isEmpty())
                        <div class="cd-empty"><p>Sin canjes registrados.</p></div>
                    @else
                        <div class="cd-table-scroll">
                            <table class="cd-table">
                                <thead><tr><th>Fecha</th><th>Premio</th><th>Pts</th><th>Estado</th></tr></thead>
                                <tbody>
                                    @foreach($loyaltyCanjes as $canje)
                                        <tr>
                                            <td class="whitespace-nowrap cd-muted">{{ $canje->created_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="max-w-[8rem] truncate" title="{{ $canje->premio?->nombre }}">{{ $canje->premio?->nombre ?? '—' }}</td>
                                            <td class="tabular-nums">{{ $canje->puntos_usados }}</td>
                                            <td>{{ \App\Models\Canje::estados()[$canje->estado] ?? $canje->estado }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            </div>

            <div class="cd-tab-panel" data-cd-panel="whatsapp" role="tabpanel" @if($cdTab !== 'whatsapp') hidden @endif>
                @include('partials.cliente-whatsapp-sidebar', [
                    'cliente' => $cliente,
                    'whatsappVista' => $whatsappVista ?? ['tiene' => false],
                    'wrapperClass' => 'cliente-wa-sidebar--tab',
                ])
            </div>

            <div class="cd-tab-panel" data-cd-panel="servicio" role="tabpanel" @if($cdTab !== 'servicio') hidden @endif>
            @php
                $puedeCrearServicio = (bool) $u?->tienePermiso('servicios.crear');
                $puedeEditarServicio = (bool) ($u?->tienePermiso('servicios.editar') || $puedeCrearServicio);
                $puedeFacturaServicio = (bool) $u?->tienePermiso('facturas.crear');
                $puedeCancelarServicio = $puedeEditarServicio && $puedeFacturaServicio;
                $puedeVerPppoe = (bool) $u?->tienePermiso('servicios.ver');
                $tienePppoeVisible = $puedeVerPppoe && $cliente->servicios->contains(fn ($s) => filled($s->usuario_pppoe));
                $mostrarAccionesServicio = $cliente->servicios->isNotEmpty()
                    && ($puedeEditarServicio || $puedeFacturaServicio || $tienePppoeVisible);
            @endphp
            <div class="cd-card">
                <div class="cd-panel__head">
                    <h2>Servicios asociados</h2>
                    <div class="cd-panel__actions">
                        @if($puedeCrearServicio)
                            <a href="{{ route('servicios.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn cd-header-btn--primary">Agregar servicio</a>
                        @endif
                    </div>
                </div>
                @if($cliente->servicios->isEmpty())
                    <div class="cd-empty"><p>No hay servicios registrados.</p></div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Plan</th>
                                    <th>Instalación</th>
                                    <th>Router / IP</th>
                                    <th>PPPoE</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cliente->servicios as $s)
                                    @php
                                        $routerNombre = $s->pool?->router?->nombre ?: ($s->pool?->router?->ip ?: '—');
                                        $estadoBadge = match ($s->estado) {
                                            'A' => 'cd-status--activo',
                                            'S' => 'cd-status--suspendido',
                                            'C' => 'cd-status--cortado',
                                            'X' => 'cd-status--cancelado',
                                            default => '',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $s->servicio_id }}</td>
                                        <td>{{ $s->plan?->nombre ?? '—' }}</td>
                                        <td class="cd-muted">{{ $s->fecha_instalacion?->format('d/m/Y') ?? '—' }}</td>
                                        <td>
                                            <span class="block text-sm">{{ $routerNombre }}</span>
                                            <span class="font-mono text-xs">
                                                @if($s->ip && $u?->tienePermiso('servicios.ver'))
                                                    <a href="{{ route('servicios.herramientas-red', $s) }}" class="cd-link">{{ $s->ip }}</a>
                                                @else
                                                    <span class="cd-muted">{{ $s->ip ?? '—' }}</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="font-mono text-xs cd-muted">{{ $s->usuario_pppoe ?? '—' }}</td>
                                        <td>
                                            <span class="cd-status {{ $estadoBadge }}">
                                                <span class="cd-status__dot"></span>
                                                {{ $estadosServicio[$s->estado] ?? $s->estado }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <div class="cd-row-actions">
                                                @if($puedeEditarServicio)
                                                    @if($s->estado === 'S')
                                                        <form action="{{ route('servicios.activar', $s) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="cd-icon-btn cd-icon-btn--activar" title="Activar servicio (sistema + router)" aria-label="Activar servicio">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            </button>
                                                        </form>
                                                    @elseif($s->estado === 'A')
                                                        <form action="{{ route('servicios.suspender', $s) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="cd-icon-btn cd-icon-btn--suspender" title="Suspender servicio (sistema + router)" aria-label="Suspender servicio">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($mostrarAccionesServicio)
            <div class="cd-card">
                <div class="cd-panel__head">
                    <div>
                        <h2>Acciones del servicio</h2>
                        <p class="cd-panel__sub">Editar, facturar, PPPoE, baja y migración</p>
                    </div>
                    @if($cliente->servicios->count() > 1)
                        <select id="cd-servicio-acciones-select" class="cd-select" aria-label="Servicio para acciones">
                            @foreach($cliente->servicios as $s)
                                <option value="{{ $s->servicio_id }}">
                                    #{{ $s->servicio_id }}
                                    @if($s->ip) · {{ $s->ip }} @endif
                                    @if($s->usuario_pppoe) · {{ $s->usuario_pppoe }} @endif
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="cd-panel__body">
                    @foreach($cliente->servicios as $idx => $s)
                        @php
                            $puedeMigrar = $puedeEditarServicio && $s->pool?->router?->nodo;
                            $puedeSync = $puedeEditarServicio && $s->usuario_pppoe && $s->pool?->router;
                            $noCancelado = $s->estado !== 'X';
                        @endphp
                        <div class="cd-action-grid" data-cd-servicio-acciones="{{ $s->servicio_id }}" @if($idx !== 0) hidden @endif>
                            @if($puedeEditarServicio)
                                <a href="{{ route('servicios.edit', $s) }}" class="cd-action">
                                    <span class="cd-action__icon cd-action__icon--violet">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </span>
                                    <span>
                                        <span class="cd-action__title">Editar</span>
                                        <span class="cd-action__sub">Datos del servicio</span>
                                    </span>
                                </a>
                            @endif
                            @if($puedeFacturaServicio)
                                <a href="{{ route('facturas.crear-interna-servicio', $s) }}" class="cd-action">
                                    <span class="cd-action__icon cd-action__icon--violet">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </span>
                                    <span>
                                        <span class="cd-action__title">Crear factura</span>
                                        <span class="cd-action__sub">Factura interna del plan</span>
                                    </span>
                                </a>
                                <a href="{{ route('facturas.crear-interna-servicio-especial', $s) }}" class="cd-action">
                                    <span class="cd-action__icon cd-action__icon--amber">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </span>
                                    <span>
                                        <span class="cd-action__title">Factura especial</span>
                                        <span class="cd-action__sub">Cargo o servicio extra</span>
                                    </span>
                                </a>
                            @endif
                            @if($puedeVerPppoe && $s->usuario_pppoe)
                                <button type="button" class="cd-action" data-cd-pppoe data-usuario="{{ $s->usuario_pppoe }}" data-password="{{ $s->password_pppoe }}">
                                    <span class="cd-action__icon cd-action__icon--slate">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    </span>
                                    <span>
                                        <span class="cd-action__title">Usuario PPPoE</span>
                                        <span class="cd-action__sub">Copiar usuario y contraseña</span>
                                    </span>
                                </button>
                            @endif
                            @if($puedeCancelarServicio && $noCancelado)
                                <form action="{{ route('servicios.cancelar', $s) }}" method="POST" class="js-swal-confirm" data-swal-title="¿Cancelar este servicio?" data-swal-text="Se generará una factura interna con el monto prorrateado desde el día 1 del mes hasta hoy, el servicio pasará a cancelado y se deshabilitará PPPoE en el router (si aplica). Si el cliente no tiene otros servicios no cancelados, quedará inactivo." data-swal-confirm="Sí, cancelar" data-swal-icon="warning" data-swal-color="#e11d48">
                                    @csrf
                                    <button type="submit" class="cd-action cd-action--danger">
                                        <span class="cd-action__icon cd-action__icon--rose">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        </span>
                                        <span>
                                            <span class="cd-action__title">Cancelar servicio</span>
                                            <span class="cd-action__sub">Con factura prorrateada</span>
                                        </span>
                                    </button>
                                </form>
                            @endif
                            @if($puedeEditarServicio && $noCancelado)
                                <form action="{{ route('servicios.dar-baja', $s) }}" method="POST" class="js-swal-confirm" data-swal-title="¿Dar de baja este servicio?" data-swal-text="Se dará de baja sin factura. Se liberará la IP y el puerto NAP (si aplica), el servicio quedará cancelado y se deshabilitará PPPoE en el router. Si el cliente no tiene otros servicios no cancelados, quedará inactivo." data-swal-confirm="Sí, dar de baja" data-swal-icon="warning" data-swal-color="#ea580c">
                                    @csrf
                                    <button type="submit" class="cd-action cd-action--warn">
                                        <span class="cd-action__icon cd-action__icon--orange">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        </span>
                                        <span>
                                            <span class="cd-action__title">Dar de baja</span>
                                            <span class="cd-action__sub">Sin factura · libera IP</span>
                                        </span>
                                    </button>
                                </form>
                            @endif
                            @if($puedeSync)
                                <form action="{{ route('servicios.sync-pppoe', $s) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="cd-action">
                                        <span class="cd-action__icon cd-action__icon--cyan">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        </span>
                                        <span>
                                            <span class="cd-action__title">Sincronizar PPPoE</span>
                                            <span class="cd-action__sub">Secret en el router</span>
                                        </span>
                                    </button>
                                </form>
                            @endif
                            @if($puedeMigrar)
                                <a href="{{ route('servicios.migrar', $s) }}" class="cd-action">
                                    <span class="cd-action__icon cd-action__icon--indigo">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </span>
                                    <span>
                                        <span class="cd-action__title">Migrar a otro nodo</span>
                                        <span class="cd-action__sub">Cambiar pool y router</span>
                                    </span>
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            </div>

            <div class="cd-tab-panel" data-cd-panel="tickets" role="tabpanel" @if($cdTab !== 'tickets') hidden @endif>
            <div class="cd-card min-w-0">
                <div class="cd-panel__head">
                    <div>
                        <h2>Historial de tickets</h2>
                        @if($ticketsActivos->isNotEmpty())
                            <p class="cd-panel__sub">{{ $ticketsActivos->count() }} activo(s)</p>
                        @endif
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('tickets.crear'))
                            <a href="{{ route('tickets.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn cd-header-btn--primary">Nuevo ticket</a>
                        @endif
                        @if($u?->tienePermiso('tickets.ver'))
                            <a href="{{ route('tickets.index', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn">Ver tickets</a>
                        @endif
                    </div>
                </div>
                @if($tickets->isEmpty())
                    <div class="cd-empty"><p>No hay tickets para este cliente.</p></div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead><tr><th>#</th><th>Fecha</th><th>Asunto</th><th>Estado</th></tr></thead>
                            <tbody>
                                @foreach($tickets as $t)
                                    <tr>
                                        <td>
                                            @if($u?->tienePermiso('tickets.crear'))
                                                <a href="{{ route('tickets.edit', $t) }}" class="cd-link font-medium">#{{ $t->id }}</a>
                                            @else #{{ $t->id }} @endif
                                        </td>
                                        <td class="cd-muted whitespace-nowrap">{{ $t->created_at?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="max-w-[12rem] truncate" title="{{ $t->ticketAsunto?->nombre }}">{{ $t->ticketAsunto?->nombre ?? '—' }}</td>
                                        <td>{{ $estadosTicket[$t->estado] ?? $t->estado }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>

            <div class="cd-tab-panel" data-cd-panel="facturas" role="tabpanel" @if($cdTab !== 'facturas') hidden @endif>
            <div class="cd-card">
                <div class="cd-panel__head">
                    <div>
                        <h2>Facturas generadas</h2>
                        <p class="cd-panel__sub">Últimas {{ ($facturasInternas ?? collect())->count() }} facturas</p>
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('cobros.crear'))
                            <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn cd-header-btn--green">Cobro</a>
                        @endif
                        @if($u?->tienePermiso('facturas.crear'))
                            <a href="{{ route('facturas.generar-interna', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn">Generar factura</a>
                        @endif
                        @if($u?->tienePermiso('factura-interna.crear') && $facturaAccion)
                            <a href="{{ route('factura-internas.show', $facturaAccion) }}" class="cd-header-btn cd-header-btn--sky">Nota de crédito</a>
                        @endif
                        @if($u?->tienePermiso('factura-interna.ver'))
                            <a href="{{ route('factura-internas.index', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}" class="cd-header-btn">Ver todas</a>
                        @endif
                    </div>
                </div>
                @if(($facturasInternas ?? collect())->isEmpty())
                    <div class="cd-empty"><p>No hay facturas registradas.</p></div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Emisión</th>
                                    <th>Período / tipo</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Pendiente</th>
                                    <th>Estado</th>
                                    <th></th>
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
                                        <td class="cd-muted whitespace-nowrap">{{ $factura->fecha_emision?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="cd-muted">
                                            @if($factura->esServicioEspecial())
                                                {{ $factura->etiquetaTipoFactura() }}
                                            @elseif($factura->periodo_desde && $factura->periodo_hasta)
                                                {{ $factura->periodo_desde->format('d/m/Y') }} – {{ $factura->periodo_hasta->format('d/m/Y') }}
                                            @else — @endif
                                        </td>
                                        <td class="text-right tabular-nums">{{ number_format((float) $factura->total, 0, ',', '.') }} PYG</td>
                                        <td class="text-right tabular-nums font-medium {{ $saldoFactura > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ number_format($saldoFactura, 0, ',', '.') }} PYG
                                        </td>
                                        <td>
                                            @if($saldoFactura > 0)
                                                <span class="cd-pill cd-pill--warn">{{ $estadosFactura[$factura->estado] ?? 'Pendiente' }}</span>
                                            @else
                                                <span class="cd-muted">{{ $estadosFactura[$factura->estado] ?? $factura->estado }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            @if($saldoFactura > 0)
                                                @if($u?->tienePermiso('cobros.crear'))
                                                    <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id, 'factura_interna_id' => $factura->id]) }}" class="cd-link text-xs mr-2">Cobro</a>
                                                    <a href="{{ route('promesas-pago.create', $factura) }}" class="cd-link text-xs mr-2">Promesa</a>
                                                @endif
                                                @if($u?->tienePermiso('factura-interna.crear'))
                                                    <a href="{{ route('factura-internas.show', $factura) }}" class="cd-link text-xs">NC</a>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="cd-card min-w-0">
                <div class="cd-panel__head"><h2>Historial de pagos</h2></div>
                @if($cobros->isEmpty())
                    <div class="cd-empty">
                        <p>No hay cobros registrados aún.</p>
                    </div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Recibo</th></tr></thead>
                            <tbody>
                                @foreach($cobros as $cobro)
                                    <tr>
                                        <td class="whitespace-nowrap cd-muted">{{ $cobro->fecha_pago?->timezone(config('app.timezone'))->format('d/m/Y') ?? '—' }}</td>
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
            </div>

            <div class="cd-tab-panel" data-cd-panel="recordatorios" role="tabpanel" @if($cdTab !== 'recordatorios') hidden @endif>
            <div class="cd-card">
                <div class="cd-panel__head">
                    <div>
                        <h2>Recordatorios de pago</h2>
                        <p class="cd-panel__sub">Facturas con saldo pendiente</p>
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('cobros.crear') && $facturaAccion)
                            <a href="{{ route('promesas-pago.create', $facturaAccion) }}" class="cd-header-btn cd-header-btn--amber">Promesa de pago</a>
                        @endif
                        @if($u?->tienePermiso('pagos-pendientes.ver'))
                            <a href="{{ route('factura-internas.pendientes', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}" class="cd-header-btn">Pendientes</a>
                        @endif
                    </div>
                </div>
                @if($facturasPendientes->isEmpty())
                    <div class="cd-empty"><p>No hay facturas pendientes para recordar.</p></div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Vencimiento</th>
                                    <th class="text-right">Pendiente</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($facturasPendientes as $factura)
                                    @php
                                        $saldoFactura = (float) $factura->saldo_pendiente;
                                        $vencida = $factura->fecha_vencimiento && $factura->fecha_vencimiento->isPast();
                                    @endphp
                                    <tr>
                                        <td class="font-medium">
                                            @if($u?->tienePermiso('factura-interna.ver'))
                                                <a href="{{ route('factura-internas.show', $factura) }}" class="cd-link">#{{ $factura->id }}</a>
                                            @else
                                                #{{ $factura->id }}
                                            @endif
                                        </td>
                                        <td class="cd-muted whitespace-nowrap">
                                            {{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
                                            @if($vencida)
                                                <span class="cd-pill cd-pill--warn ml-1">Vencida</span>
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums font-medium text-amber-600 dark:text-amber-400">
                                            {{ number_format($saldoFactura, 0, ',', '.') }} PYG
                                        </td>
                                        <td>{{ $estadosFactura[$factura->estado] ?? 'Pendiente' }}</td>
                                        <td class="text-right whitespace-nowrap">
                                            @if($u?->tienePermiso('cobros.crear'))
                                                <a href="{{ route('promesas-pago.create', $factura) }}" class="cd-link text-xs">Promesa</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>

            <div class="cd-tab-panel" data-cd-panel="consumo" role="tabpanel" @if($cdTab !== 'consumo') hidden @endif>
            <div class="cd-card">
                <div class="cd-panel__head">
                    <div>
                        <h2>Consumo contratado</h2>
                        <p class="cd-panel__sub">Plan y velocidad de cada servicio</p>
                    </div>
                </div>
                @if($cliente->servicios->isEmpty())
                    <div class="cd-empty"><p>No hay servicios para mostrar consumo.</p></div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Plan</th>
                                    <th>Velocidad</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cliente->servicios as $s)
                                    <tr>
                                        <td>{{ $s->servicio_id }}</td>
                                        <td>{{ $s->plan?->nombre ?? '—' }}</td>
                                        <td class="cd-muted">{{ $s->plan?->velocidad ?: '—' }}</td>
                                        <td>{{ $estadosServicio[$s->estado] ?? $s->estado }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>

            <div class="cd-tab-panel" data-cd-panel="red" role="tabpanel" @if($cdTab !== 'red') hidden @endif>
                @if(! ($u?->tienePermiso('servicios.ver')))
                    <div class="cd-card">
                        <div class="cd-empty"><p>No tenés permiso para consultar herramientas de red.</p></div>
                    </div>
                @elseif($cliente->servicios->isEmpty())
                    <div class="cd-card">
                        <div class="cd-empty"><p>No hay servicios para consultar.</p></div>
                    </div>
                @else
                    <div id="herramientas-red-app"></div>
                @endif
            </div>
            </div>
        </main>
    </div>

    @if($cliente->servicios->isNotEmpty() && $u?->tienePermiso('servicios.ver'))
    <div id="cd-pppoe-modal" class="cd-modal" hidden>
        <div class="cd-modal__box" role="dialog" aria-modal="true" aria-labelledby="cd-pppoe-title">
            <div class="cd-modal__head">
                <h3 id="cd-pppoe-title">Usuario y contraseña PPPoE</h3>
                <button type="button" class="cd-modal__ghost" data-cd-pppoe-close aria-label="Cerrar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="cd-modal__body">
                <div class="cd-modal__field">
                    <label for="cd-pppoe-usuario">Usuario</label>
                    <div class="cd-modal__row">
                        <input id="cd-pppoe-usuario" type="text" readonly value="">
                        <button type="button" class="cd-modal__copy" data-cd-pppoe-copy="usuario">Copiar</button>
                    </div>
                </div>
                <div class="cd-modal__field">
                    <label for="cd-pppoe-password">Contraseña</label>
                    <div class="cd-modal__row">
                        <input id="cd-pppoe-password" type="password" readonly value="">
                        <button type="button" class="cd-modal__ghost" id="cd-pppoe-toggle" title="Mostrar u ocultar">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                        <button type="button" class="cd-modal__copy" data-cd-pppoe-copy="password">Copiar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
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
<script>
(function() {
    var root = document.getElementById('cliente-detalle-tabs');
    if (!root) return;
    var buttons = root.querySelectorAll('[data-cd-tab]');
    var panels = root.querySelectorAll('[data-cd-panel]');
    var allowed = {};
    buttons.forEach(function(btn) { allowed[btn.getAttribute('data-cd-tab')] = true; });

    function activate(name, persist) {
        if (!allowed[name]) name = 'cliente';
        buttons.forEach(function(btn) {
            var on = btn.getAttribute('data-cd-tab') === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function(panel) {
            panel.hidden = panel.getAttribute('data-cd-panel') !== name;
        });
        if (name === 'whatsapp') {
            window.dispatchEvent(new Event('cliente-wa-tab-visible'));
            var hilo = root.querySelector('[data-cliente-wa-hilo]');
            if (hilo) {
                requestAnimationFrame(function() { hilo.scrollTop = hilo.scrollHeight; });
            }
        }
        if (!persist) return;
        var url = new URL(window.location.href);
        if (name === 'cliente') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', name);
        }
        history.replaceState(null, '', url);
    }

    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            activate(btn.getAttribute('data-cd-tab'), true);
        });
    });
    var activa = root.querySelector('[data-cd-tab].is-active');
    if (activa && activa.getAttribute('data-cd-tab') === 'whatsapp') {
        window.dispatchEvent(new Event('cliente-wa-tab-visible'));
    }
})();
</script>
<script>
(function() {
    var sel = document.getElementById('cd-servicio-acciones-select');
    var grids = document.querySelectorAll('[data-cd-servicio-acciones]');
    function showServicio(id) {
        grids.forEach(function(grid) {
            grid.hidden = String(grid.getAttribute('data-cd-servicio-acciones')) !== String(id);
        });
    }
    if (sel) {
        sel.addEventListener('change', function() { showServicio(sel.value); });
    }

    var modal = document.getElementById('cd-pppoe-modal');
    if (!modal) return;
    var inputUser = document.getElementById('cd-pppoe-usuario');
    var inputPass = document.getElementById('cd-pppoe-password');
    var btnToggle = document.getElementById('cd-pppoe-toggle');

    function closeModal() {
        modal.hidden = true;
        if (inputPass) inputPass.type = 'password';
        modal.querySelectorAll('[data-cd-pppoe-copy]').forEach(function(btn) {
            btn.textContent = 'Copiar';
        });
    }
    function openModal(usuario, password) {
        if (inputUser) inputUser.value = usuario || '';
        if (inputPass) {
            inputPass.value = password || '';
            inputPass.type = 'password';
        }
        modal.hidden = false;
        if (inputUser) inputUser.focus();
        inputUser && inputUser.select();
    }
    function copiar(texto, btn) {
        var done = function() {
            var prev = btn.textContent;
            btn.textContent = 'Copiado';
            setTimeout(function() { btn.textContent = prev || 'Copiar'; }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto || '').then(done).catch(function() {
                fallbackCopy(texto); done();
            });
            return;
        }
        fallbackCopy(texto);
        done();
    }
    function fallbackCopy(texto) {
        var ta = document.createElement('textarea');
        ta.value = texto || '';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    document.querySelectorAll('[data-cd-pppoe]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openModal(btn.getAttribute('data-usuario'), btn.getAttribute('data-password'));
        });
    });
    modal.addEventListener('click', function(ev) {
        if (ev.target === modal) closeModal();
    });
    modal.querySelectorAll('[data-cd-pppoe-close]').forEach(function(btn) {
        btn.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape' && !modal.hidden) closeModal();
    });
    if (btnToggle) {
        btnToggle.addEventListener('click', function() {
            inputPass.type = inputPass.type === 'password' ? 'text' : 'password';
        });
    }
    modal.querySelectorAll('[data-cd-pppoe-copy]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var campo = btn.getAttribute('data-cd-pppoe-copy');
            copiar(campo === 'password' ? inputPass.value : inputUser.value, btn);
        });
    });
})();
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
<script>
(function() {
    function swalTheme() {
        if (!document.documentElement.classList.contains('dark')) return {};
        return {
            background: '#1f2937',
            color: '#f3f4f6',
            customClass: { popup: 'border border-gray-700' }
        };
    }
    document.querySelectorAll('form.js-swal-confirm').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.swalOk === '1') return;
            e.preventDefault();
            if (typeof Swal === 'undefined') {
                if (window.confirm(form.dataset.swalTitle || '¿Confirmar?')) {
                    form.dataset.swalOk = '1';
                    form.submit();
                }
                return;
            }
            Swal.fire(Object.assign({
                icon: form.dataset.swalIcon || 'warning',
                title: form.dataset.swalTitle || '¿Confirmar?',
                text: form.dataset.swalText || '',
                showCancelButton: true,
                confirmButtonColor: form.dataset.swalColor || '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: form.dataset.swalConfirm || 'Sí, continuar',
                cancelButtonText: 'Volver',
                reverseButtons: true,
                focusCancel: true
            }, swalTheme())).then(function(result) {
                if (!result.isConfirmed) return;
                form.dataset.swalOk = '1';
                form.submit();
            });
        });
    });
})();
</script>
@endpush

@if(! empty($herramientasRedConfig))
@push('scripts')
<script>
    window.__HERRAMIENTAS_RED_CONFIG__ = @json($herramientasRedConfig);
</script>
<script src="{{ mix('js/herramientas-red.js') }}"></script>
@endpush
@endif
