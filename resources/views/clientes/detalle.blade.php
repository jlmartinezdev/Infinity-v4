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
    $calificacionPagoEstrellas = match ($cliente->calificacion_pago) {
        \App\Models\Cliente::CALIFICACION_MALO => 1,
        \App\Models\Cliente::CALIFICACION_BUENO => 2,
        \App\Models\Cliente::CALIFICACION_EXCELENTE => 3,
        default => 0,
    };
    $calificacionPagoStarClass = match ($cliente->calificacion_pago) {
        \App\Models\Cliente::CALIFICACION_MALO => 'text-red-500 dark:text-red-400',
        \App\Models\Cliente::CALIFICACION_BUENO => 'text-blue-500 dark:text-blue-400',
        \App\Models\Cliente::CALIFICACION_EXCELENTE => 'text-amber-400 dark:text-amber-300',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="cliente-detalle-page cliente-detalle-container pb-8">
    <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 mb-1 inline-block">&larr; Volver a clientes</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Detalle general del cliente</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cliente->nombre }} {{ $cliente->apellido }} · #{{ $cliente->cliente_id }}</p>
        </div>
        <a href="{{ route('clientes.contrato', $cliente) }}" target="_blank" rel="noopener"
            class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 print:hidden">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
            </svg>
            Imprimir contrato
        </a>
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
                                        Servicio #{{ $sSaldo->servicio_id }} — {{ $sSaldo->etiqueta() }}
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
                        <a href="{{ route('clientes.contrato', $cliente) }}" target="_blank" rel="noopener" class="cd-header-btn">Imprimir contrato</a>
                        @if($u?->tienePermiso('clientes.editar'))
                            <a href="{{ route('clientes.edit', $cliente) }}" class="cd-header-btn cd-header-btn--primary">Editar</a>
                        @endif
                        @if($u?->tienePermiso('pedidos.crear') || $u?->tienePermiso('clientes-pedidos.crear'))
                            <a href="{{ route('pedidos.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn" title="Crear pedido de instalación para este cliente">Nuevo pedido</a>
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
                            <dd class="mt-0.5 flex items-center gap-1.5 cd-value">
                                @if($cliente->telefono)
                                    <span class="shrink-0 text-emerald-500 dark:text-emerald-400" title="{{ $tieneWhatsapp ? 'WhatsApp vinculado' : 'WhatsApp' }}">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                                        </svg>
                                    </span>
                                @endif
                                {{ $cliente->telefono ?: '—' }}
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
                            <dd class="mt-0.5 cd-value">
                                @if($calificacionPagoEstrellas > 0)
                                    <span class="inline-flex items-center gap-1.5 {{ $calificacionPagoStarClass }}">
                                        <span class="inline-flex items-center gap-0.5" aria-hidden="true">
                                            @for($i = 1; $i <= 3; $i++)
                                                <svg class="h-4 w-4 {{ $i <= $calificacionPagoEstrellas ? '' : 'opacity-30' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                        </span>
                                        <span>{{ $cliente->calificacion_pago_label }}</span>
                                    </span>
                                @else
                                    —
                                @endif
                            </dd>
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

            <div class="cd-card min-w-0">
                <div class="cd-panel__head">
                    <h2>Acciones en la app</h2>
                    @if($portalWifiBackup?->wifi_password)
                        <span class="text-xs text-gray-500 dark:text-gray-400">Backup Wi‑Fi</span>
                    @endif
                </div>
                @if($portalWifiBackup?->wifi_password)
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/60 text-sm">
                        <p class="cd-label">Última clave Wi‑Fi (app)</p>
                        <p class="mt-1 font-medium cd-value">
                            {{ $portalWifiBackup->ssid ? 'SSID '.$portalWifiBackup->ssid : 'Wi‑Fi' }}
                            · {{ $portalWifiBackup->created_at?->format('d/m/Y H:i') }}
                        </p>
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs text-blue-600 dark:text-blue-400">Ver clave de respaldo</summary>
                            <code class="mt-1 block font-mono text-sm break-all cd-value">{{ $portalWifiBackup->wifi_password }}</code>
                        </details>
                    </div>
                @endif
                @if(($portalAcciones ?? collect())->isEmpty())
                    <div class="cd-empty"><p>Todavía no hay cambios hechos desde la app.</p></div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead><tr><th>Fecha</th><th>Acción</th><th>Red</th><th>Clave</th></tr></thead>
                            <tbody>
                                @foreach($portalAcciones as $accion)
                                    <tr>
                                        <td class="whitespace-nowrap cd-muted">{{ $accion->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td>{{ $accion->titulo }}</td>
                                        <td class="max-w-[8rem] truncate" title="{{ $accion->ssid }}">{{ $accion->ssid ?: '—' }}</td>
                                        <td>
                                            @if($accion->wifi_password)
                                                <details>
                                                    <summary class="cursor-pointer text-xs text-blue-600 dark:text-blue-400">Ver</summary>
                                                    <code class="font-mono text-xs break-all">{{ $accion->wifi_password }}</code>
                                                </details>
                                            @else
                                                <span class="cd-muted">—</span>
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
                                    <th>Alias</th>
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
                                            'P' => 'cd-status--pendiente',
                                            default => '',
                                        };
                                    @endphp
                                    <tr @if($cliente->servicios->count() > 1) data-cd-servicio-row="{{ $s->servicio_id }}" class="{{ $loop->first ? 'is-selected' : '' }}" @endif>
                                        <td>{{ $s->servicio_id }}</td>
                                        <td>{{ $s->aliasNormalizado() ?? '—' }}</td>
                                        <td>{{ $s->plan?->nombre ?? '—' }}</td>
                                        <td class="cd-muted">{{ $s->fecha_instalacion?->format('d/m/Y') ?? '—' }}</td>
                                        <td>
                                            <span class="block text-sm">{{ $routerNombre }}</span>
                                            <span class="font-mono text-xs">
                                                @if($s->ip && $u?->tienePermiso('servicios.ver'))
                                                    <a href="http://{{ $s->ip }}" target="_blank" rel="noopener noreferrer" class="cd-link" title="Abrir web UI del CPE {{ $s->ip }}">{{ $s->ip }}</a>
                                                @else
                                                    <span class="cd-muted">{{ $s->ip ?? '—' }}</span>
                                                @endif
                                                <span data-badge-ipv6="{{ $s->servicio_id }}" class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-purple-600 dark:text-purple-400 {{ $s->ipv6_configurado ? '' : 'hidden' }}" title="IPv6 configurado">IPv6</span>
                                                <span data-badge-hotspot="{{ $s->servicio_id }}" class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-violet-600 dark:text-violet-400 {{ $s->punto_hotspot ? '' : 'hidden' }}" title="Punto hotspot">HS</span>
                                                @if($s->tvCuentaAsignaciones->isNotEmpty() || $s->app_tv)
                                                    <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-600 dark:text-fuchsia-400" title="Tiene cuenta TV">TV</span>
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
                        @if($cliente->servicios->count() > 1)
                            <p class="cd-panel__sub">Elegí el servicio en la tabla o en la lista; los botones se aplican a ese.</p>
                        @else
                            <p class="cd-panel__sub">Editar, facturar, PPPoE, baja y migración</p>
                        @endif
                    </div>
                    @if($cliente->servicios->count() > 1)
                        <select id="cd-servicio-acciones-select" class="cd-select" aria-label="Servicio para acciones">
                            @foreach($cliente->servicios as $s)
                                <option value="{{ $s->servicio_id }}">
                                    #{{ $s->servicio_id }}
                                    · {{ $s->etiqueta() }}
                                    @if($s->ip) · {{ $s->ip }} @endif
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
                            $puedeFinalizarInstalacion = $puedeEditarServicio
                                && $puedeFacturaServicio
                                && $noCancelado
                                && $s->esCandidatoFinalizarInstalacion()
                                && empty(($serviciosFacturadosMes ?? [])[$s->servicio_id]);
                            $acuerdoSinFactura = $s->acuerdoAplicaEnPeriodo(now()->startOfMonth(), now()->endOfMonth());
                            if ($acuerdoSinFactura) {
                                $textoFinalizarInstalacion = 'El servicio pasará a activo. No se genera factura porque tiene acuerdo de no facturación en este período.';
                                $subFinalizarInstalacion = 'Activar sin facturar (acuerdo)';
                            } elseif (\App\Services\FacturacionService::puedeEmitirFacturaPorInstalacion()) {
                                $textoFinalizarInstalacion = 'El servicio pasará a activo y se generará la factura interna prorrateada del mes (igual que al finalizar un pedido).';
                                $subFinalizarInstalacion = 'Activar y facturar prorrateo';
                            } else {
                                $textoFinalizarInstalacion = 'El servicio pasará a activo. No se emite factura entre el día 1 y 6; volvé a finalizar desde el día 7 para generar la factura prorrateada.';
                                $subFinalizarInstalacion = 'Activar (factura desde el día 7)';
                            }
                        @endphp
                        <div class="space-y-4" data-cd-servicio-acciones="{{ $s->servicio_id }}" @if($idx !== 0) hidden @endif>
                            {{-- 1. Opciones y Ajustes Rápidos (Switches AJAX) --}}
                            @if($puedeEditarServicio || $puedeFinalizarInstalacion)
                                <div class="cd-action-group">
                                    <div class="cd-action-group__title">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>
                                        <span>Opciones del Servicio (Ajustes en vivo)</span>
                                    </div>
                                    <div class="grid grid-cols-1 {{ $puedeFinalizarInstalacion ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-2.5">
                                        @if($puedeFinalizarInstalacion)
                                            <form action="{{ route('servicios.finalizar-instalacion', $s) }}" method="POST" class="js-swal-confirm flex m-0" data-swal-title="¿Finalizar instalación?" data-swal-text="{{ $textoFinalizarInstalacion }}" data-swal-confirm="Sí, finalizar" data-swal-icon="success" data-swal-color="#16a34a">
                                                @csrf
                                                <button type="submit" class="cd-action cd-action--ok w-full">
                                                    <span class="cd-action__icon cd-action__icon--green">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    </span>
                                                    <span>
                                                        <span class="cd-action__title">Finalizar instalación</span>
                                                        <span class="cd-action__sub">{{ $subFinalizarInstalacion }}</span>
                                                    </span>
                                                </button>
                                            </form>
                                        @endif
                                        @if($puedeEditarServicio)
                                            <div class="cd-switch-card {{ $s->ipv6_configurado ? 'is-active' : '' }}">
                                                <div class="cd-switch-card__main">
                                                    <span class="cd-action__icon cd-action__icon--violet shrink-0" aria-hidden="true">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 0 1 7.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                                    </span>
                                                    <div class="cd-switch-card__texts">
                                                        <span class="cd-switch-card__title">IPv6 configurado</span>
                                                        <span class="cd-switch-card__sub" data-switch-status="ipv6_{{ $s->servicio_id }}">
                                                            {{ $s->ipv6_configurado ? 'Habilitado en el servicio' : 'No configurado' }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <label class="cd-toggle" title="Alternar IPv6">
                                                    <input type="checkbox"
                                                        class="cd-ajax-toggle"
                                                        name="ipv6_configurado"
                                                        data-url="{{ route('servicios.ipv6', $s) }}"
                                                        data-status-id="ipv6_{{ $s->servicio_id }}"
                                                        data-text-active="Habilitado en el servicio"
                                                        data-text-inactive="No configurado"
                                                        data-badge-selector="[data-badge-ipv6='{{ $s->servicio_id }}']"
                                                        {{ $s->ipv6_configurado ? 'checked' : '' }}>
                                                    <span class="cd-toggle__track">
                                                        <span class="cd-toggle__thumb"></span>
                                                    </span>
                                                </label>
                                            </div>

                                            <div class="cd-switch-card {{ $s->punto_hotspot ? 'is-active' : '' }}">
                                                <div class="cd-switch-card__main">
                                                    <span class="cd-action__icon cd-action__icon--violet shrink-0" aria-hidden="true">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                                                    </span>
                                                    <div class="cd-switch-card__texts">
                                                        <span class="cd-switch-card__title">Punto Hotspot</span>
                                                        <span class="cd-switch-card__sub" data-switch-status="hotspot_{{ $s->servicio_id }}">
                                                            {{ $s->punto_hotspot ? 'Esta ONU emite hotspot' : 'No emite hotspot' }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <label class="cd-toggle" title="Alternar Punto Hotspot">
                                                    <input type="checkbox"
                                                        class="cd-ajax-toggle"
                                                        name="punto_hotspot"
                                                        data-url="{{ route('servicios.punto-hotspot', $s) }}"
                                                        data-status-id="hotspot_{{ $s->servicio_id }}"
                                                        data-text-active="Esta ONU emite hotspot"
                                                        data-text-inactive="No emite hotspot"
                                                        data-badge-selector="[data-badge-hotspot='{{ $s->servicio_id }}']"
                                                        {{ $s->punto_hotspot ? 'checked' : '' }}>
                                                    <span class="cd-toggle__track">
                                                        <span class="cd-toggle__thumb"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- 2. Conectividad y Operaciones de Red --}}
                            @if($puedeVerPppoe || $puedeSync || $puedeMigrar || ($puedeEditarServicio && $noCancelado))
                                <div class="cd-action-group">
                                    <div class="cd-action-group__title">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                        <span>Red y Conectividad</span>
                                    </div>
                                    <div class="cd-action-grid">
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
                                        @if($puedeSync)
                                            <form action="{{ route('servicios.sync-pppoe', $s) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="cd-action">
                                                    <span class="cd-action__icon cd-action__icon--cyan">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                    </span>
                                                    <span>
                                                        <span class="cd-action__title">Sincronizar PPPoE</span>
                                                        <span class="cd-action__sub">Actualizar secret en el router</span>
                                                    </span>
                                                </button>
                                            </form>
                                        @endif
                                        @if($puedeEditarServicio && $noCancelado)
                                            @php
                                                $subTec = match (true) {
                                                    \App\Support\HerramientasRedPayload::esAntena($s) => 'Antena → Fibra',
                                                    \App\Support\HerramientasRedPayload::esFibra($s) => 'Fibra → Antena',
                                                    default => 'Plan equivalente por precio',
                                                };
                                            @endphp
                                            <a href="{{ route('servicios.cambiar-tecnologia', $s) }}" class="cd-action">
                                                <span class="cd-action__icon cd-action__icon--emerald">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                                </span>
                                                <span>
                                                    <span class="cd-action__title">Cambiar tecnología</span>
                                                    <span class="cd-action__sub">{{ $subTec }}</span>
                                                </span>
                                            </a>
                                        @endif
                                        @if($puedeMigrar)
                                            <a href="{{ route('servicios.migrar', $s) }}" class="cd-action">
                                                <span class="cd-action__icon cd-action__icon--indigo">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                </span>
                                                <span>
                                                    <span class="cd-action__title">Migrar de nodo</span>
                                                    <span class="cd-action__sub">Cambiar pool y router</span>
                                                </span>
                                            </a>
                                        @endif
                                        @if($u?->tienePermiso('servicios.ver'))
                                            <button type="button" class="cd-action" data-cd-goto-tab="red" title="Ir a la pestaña de herramientas de red">
                                                <span class="cd-action__icon cd-action__icon--sky">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                                                </span>
                                                <span>
                                                    <span class="cd-action__title">Herramienta de red</span>
                                                    <span class="cd-action__sub">Diagnóstico en vivo, ping y tráfico</span>
                                                </span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- 3. Gestión y Facturación --}}
                            @if($puedeEditarServicio || $puedeFacturaServicio)
                                <div class="cd-action-group">
                                    <div class="cd-action-group__title">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0 0a60.073 60.073 0 0 0 15.797 2.101c.727.198 1.453-.342 1.453-1.096V6H19.5a.75.75 0 0 1-.75-.75V4.5m-15 0A2.25 2.25 0 0 1 6 2.25h12A2.25 2.25 0 0 1 20.25 4.5m-15 0H6"/></svg>
                                        <span>Gestión y Facturación</span>
                                    </div>
                                    <div class="cd-action-grid">
                                        @if($puedeEditarServicio)
                                            <a href="{{ route('servicios.edit', $s) }}" class="cd-action">
                                                <span class="cd-action__icon cd-action__icon--violet">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </span>
                                                <span>
                                                    <span class="cd-action__title">Editar servicio</span>
                                                    <span class="cd-action__sub">Datos del plan, equipo y notas</span>
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
                                    </div>
                                </div>
                            @endif

                            {{-- 4. Estado y Operaciones Críticas (Zona de Peligro) --}}
                            @if($noCancelado && ($puedeCancelarServicio || $puedeEditarServicio))
                                <div class="cd-action-group">
                                    <div class="cd-action-group__title cd-action-group__title--danger">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                        <span>Estado y Operaciones Críticas</span>
                                    </div>
                                    <div class="cd-action-grid">
                                        @if($puedeEditarServicio)
                                            @if($s->estado === 'A')
                                                <form action="{{ route('servicios.suspender', $s) }}" method="POST" class="js-swal-confirm" data-swal-title="¿Suspender este servicio?" data-swal-text="El servicio pasará a suspendido y se deshabilitará el acceso PPPoE en el router." data-swal-confirm="Sí, suspender" data-swal-icon="warning" data-swal-color="#f59e0b">
                                                    @csrf
                                                    <button type="submit" class="cd-action cd-action--warn">
                                                        <span class="cd-action__icon cd-action__icon--amber">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        </span>
                                                        <span>
                                                            <span class="cd-action__title">Suspender servicio</span>
                                                            <span class="cd-action__sub">Corte temporal (deshabilita PPPoE)</span>
                                                        </span>
                                                    </button>
                                                </form>
                                            @elseif($s->estado === 'S')
                                                <form action="{{ route('servicios.activar', $s) }}" method="POST" class="js-swal-confirm" data-swal-title="¿Reactivar este servicio?" data-swal-text="El servicio pasará a activo y se habilitará nuevamente en el router." data-swal-confirm="Sí, reactivar" data-swal-icon="success" data-swal-color="#16a34a">
                                                    @csrf
                                                    <button type="submit" class="cd-action cd-action--ok">
                                                        <span class="cd-action__icon cd-action__icon--green">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        </span>
                                                        <span>
                                                            <span class="cd-action__title">Reactivar servicio</span>
                                                            <span class="cd-action__sub">Habilitar en sistema y router</span>
                                                        </span>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        @if($puedeCancelarServicio)
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
                                        @if($puedeEditarServicio)
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
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($u?->tienePermiso('tv.ver'))
            @php
                $tvAsignacionesCliente = $cliente->servicios
                    ->flatMap(fn ($s) => $s->tvCuentaAsignaciones ?? collect())
                    ->filter(fn ($a) => $a->tvCuenta);
                $tvCuentasAgrupadas = $tvAsignacionesCliente->groupBy('tv_cuenta_id');
                $tvApps = \App\Models\TvCuenta::aplicaciones();
            @endphp
            <div class="cd-card">
                <div class="cd-panel__head">
                    <div>
                        <h2>Cuenta TV</h2>
                        <p class="cd-panel__sub">{{ $tvCuentasAgrupadas->isEmpty() ? 'Este cliente no tiene cuenta TV asignada' : ($tvCuentasAgrupadas->count().' cuenta'.($tvCuentasAgrupadas->count() === 1 ? '' : 's').' asignada'.($tvCuentasAgrupadas->count() === 1 ? '' : 's')) }}</p>
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('tv.editar') && $cliente->servicios->isNotEmpty())
                            <a href="{{ route('tv-cuentas.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn">Nueva cuenta</a>
                        @endif
                        <a href="{{ route('tv-cuentas.index', ['q' => trim($cliente->nombre.' '.$cliente->apellido)]) }}" class="cd-header-btn">Ver en TV streaming</a>
                    </div>
                </div>
                @if($tvCuentasAgrupadas->isEmpty())
                    <div class="cd-empty"><p>No tiene cuenta de TV.</p></div>
                @else
                    <div class="grid grid-cols-1 {{ $tvCuentasAgrupadas->count() > 1 ? 'lg:grid-cols-2' : '' }} gap-2 p-2 sm:p-3">
                        @foreach($tvCuentasAgrupadas as $tvGrupo)
                            @php
                                $tvCuenta = $tvGrupo->first()->tvCuenta;
                                $tvSlots = $tvGrupo->map(function ($a) {
                                    $nombreSlot = $a->tvCuenta?->nombreSlot((int) $a->perfil_numero) ?: ('Perfil '.$a->perfil_numero);
                                    return $nombreSlot.' · servicio #'.$a->servicio_id;
                                })->unique()->values();
                            @endphp
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tvCuenta->esLumix() ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200' }}">
                                        {{ $tvApps[$tvCuenta->aplicacion] ?? $tvCuenta->aplicacion }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tvCuenta->etiquetaEstadoVencimiento() }}</span>
                                    @if($u?->tienePermiso('tv.editar'))
                                        <a href="{{ route('tv-cuentas.edit', $tvCuenta) }}" class="ml-auto text-xs font-medium text-purple-600 dark:text-purple-300 hover:underline">Editar</a>
                                    @endif
                                </div>
                                @if($tvCuenta->nombre)
                                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $tvCuenta->nombre }}</p>
                                @endif
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $tvSlots->implode(' · ') }}</p>
                                <div class="mt-3 space-y-2">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Correo / usuario</p>
                                        <div class="mt-0.5 flex items-center gap-2 min-w-0">
                                            <p class="font-mono text-sm text-gray-900 dark:text-gray-100 break-all min-w-0">{{ $tvCuenta->usuario_app ?: '—' }}</p>
                                            @if(filled($tvCuenta->usuario_app))
                                                <button type="button"
                                                    class="shrink-0 p-1 rounded-md text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    data-tv-copiar="{{ $tvCuenta->usuario_app }}"
                                                    title="Copiar correo"
                                                    aria-label="Copiar correo">
                                                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contraseña</p>
                                        <div class="mt-0.5 flex items-center gap-2 min-w-0">
                                            <p class="font-mono text-sm text-gray-900 dark:text-gray-100 break-all min-w-0" data-tv-pass-mask>{{ filled($tvCuenta->password) ? '••••••••' : '—' }}</p>
                                            @if(filled($tvCuenta->password))
                                                <button type="button"
                                                    class="shrink-0 p-1 rounded-md text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    data-tv-pass-toggle
                                                    data-tv-pass="{{ $tvCuenta->password }}"
                                                    aria-pressed="false"
                                                    title="Mostrar contraseña"
                                                    aria-label="Mostrar contraseña">
                                                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                    class="shrink-0 p-1 rounded-md text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    data-tv-copiar="{{ $tvCuenta->password }}"
                                                    title="Copiar contraseña"
                                                    aria-label="Copiar contraseña">
                                                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif

            @if($u?->tienePermiso('servicios.ver'))
            @php
                $hsMax = \App\Models\ServicioHotspot::MAX_POR_CLIENTE;
                $hsPorSlot = ($cliente->servicioHotspots ?? collect())->keyBy(fn ($h) => (int) $h->slot_numero);
                $hsEstados = \App\Models\Servicio::estadosDisponibles();
            @endphp
            <div class="cd-card">
                <div class="cd-panel__head">
                    <h2>Usuarios Hotspot</h2>
                    <div class="cd-panel__actions">
                        <a href="{{ route('hotspot.clientes.edit', $cliente) }}" class="cd-header-btn">Gestionar ({{ $hsPorSlot->count() }} / {{ $hsMax }})</a>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @for($hsSlot = 1; $hsSlot <= $hsMax; $hsSlot++)
                        @php $hs = $hsPorSlot->get($hsSlot); @endphp
                        @if($hs)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Slot {{ $hsSlot }}</p>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 font-mono break-all">{{ $hs->username }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">PIN {{ $hs->password }} · {{ $hsEstados[$hs->servicio?->estado] ?? ($hs->servicio?->estado ?: '—') }}</p>
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-3 min-h-[6.5rem] flex flex-col">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Slot {{ $hsSlot }}</p>
                                <div class="flex-1 flex items-center justify-center">
                                    <a href="{{ route('hotspot.clientes.edit', ['cliente' => $cliente, 'slot' => $hsSlot]) }}#form-hotspot"
                                        class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-purple-300 dark:border-purple-600 text-purple-600 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/30"
                                        title="Crear usuario en el slot {{ $hsSlot }}"
                                        aria-label="Crear usuario en el slot {{ $hsSlot }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endfor
                </div>
            </div>
            @endif
            </div>

            <div class="cd-tab-panel" data-cd-panel="tickets" role="tabpanel" @if($cdTab !== 'tickets') hidden @endif>
            @php
                $ticketsAbiertos = $ticketsActivos;
                $ticketsResueltos = $tickets->where('estado', 'resuelto');
                $ticketsCerrados = $tickets->whereIn('estado', ['cerrado', 'cancelado', 'no_realizado']);
            @endphp

            {{-- KPIs / Mini-resumen de Tickets --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-1">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total tickets</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $tickets->count() }}</p>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Abiertos / Activos</p>
                        <p class="text-xl font-bold {{ $ticketsAbiertos->isNotEmpty() ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }} tabular-nums">{{ $ticketsAbiertos->count() }}</p>
                    </div>
                    <div class="w-8 h-8 rounded-lg {{ $ticketsAbiertos->isNotEmpty() ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400' : 'bg-gray-50 dark:bg-gray-700 text-gray-400' }} flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Resueltos</p>
                        <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $ticketsResueltos->count() }}</p>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Cerrados / Otros</p>
                        <p class="text-xl font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ $ticketsCerrados->count() }}</p>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                </div>
            </div>

            <div class="cd-card min-w-0">
                <div class="cd-panel__head">
                    <div>
                        <h2>Historial de tickets</h2>
                        <p class="cd-panel__sub">{{ $tickets->count() }} ticket(s) registrado(s) para este cliente</p>
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('tickets.crear'))
                            <a href="{{ route('tickets.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn cd-header-btn--primary">Nuevo ticket</a>
                        @endif
                        @if($u?->tienePermiso('tickets.ver'))
                            <a href="{{ route('tickets.index', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn">Ver en módulo</a>
                        @endif
                    </div>
                </div>

                @if($tickets->isEmpty())
                    <div class="cd-empty"><p>No hay tickets para este cliente.</p></div>
                @else
                    {{-- Filtros rápidos dentro de la pestaña --}}
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-gray-100 dark:border-gray-700/60 bg-gray-50/50 dark:bg-gray-800/40">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-1">Filtrar:</span>
                        <button type="button" class="cd-filter-pill is-active" data-ticket-filter="todos">
                            Todos <span class="text-[10px] font-bold opacity-75">({{ $tickets->count() }})</span>
                        </button>
                        <button type="button" class="cd-filter-pill" data-ticket-filter="activos">
                            Abiertos <span class="text-[10px] font-bold opacity-75">({{ $ticketsAbiertos->count() }})</span>
                        </button>
                        <button type="button" class="cd-filter-pill" data-ticket-filter="resueltos">
                            Resueltos <span class="text-[10px] font-bold opacity-75">({{ $ticketsResueltos->count() }})</span>
                        </button>
                    </div>

                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th class="w-16">#</th>
                                    <th>Fecha</th>
                                    <th>Asunto & Detalle</th>
                                    <th>Prioridad</th>
                                    <th>Técnico</th>
                                    <th>Estado</th>
                                    <th class="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="ticket-table-body">
                                <tr id="ticket-filter-empty" class="hidden">
                                    <td colspan="7" class="py-6 text-center text-sm text-gray-400">
                                        No se encontraron tickets con el filtro seleccionado.
                                    </td>
                                </tr>
                                @foreach($tickets as $t)
                                    @php
                                        $esActivo = !in_array($t->estado, ['resuelto', 'cerrado', 'cancelado'], true);
                                        $estadoClase = match($t->estado) {
                                            'pendiente' => 'cd-status--ticket-pendiente',
                                            'en_proceso', 'en_camino' => 'cd-status--ticket-en_proceso',
                                            'resuelto' => 'cd-status--ticket-resuelto',
                                            'cerrado' => 'cd-status--ticket-cerrado',
                                            'cancelado' => 'cd-status--ticket-cancelado',
                                            'no_realizado' => 'cd-status--ticket-no_realizado',
                                            default => 'cd-status--ticket-cerrado',
                                        };
                                        $prioridadClase = match($t->prioridad) {
                                            'alta' => 'cd-priority-pill--alta',
                                            'media' => 'cd-priority-pill--media',
                                            default => 'cd-priority-pill--baja',
                                        };
                                        $canalLabel = match($t->reportado_desde) {
                                            'whatsapp' => 'WhatsApp',
                                            'app' => 'App',
                                            'telefono' => 'Teléfono',
                                            'web' => 'Web',
                                            'presencial' => 'Presencial',
                                            default => null,
                                        };
                                        $canalClase = match($t->reportado_desde) {
                                            'whatsapp' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                                            'app' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300',
                                            'telefono' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        };
                                    @endphp
                                    <tr class="cd-ticket-row" data-ticket-id="{{ $t->id }}" data-ticket-activo="{{ $esActivo ? '1' : '0' }}" data-ticket-estado="{{ $t->estado }}">
                                        <td>
                                            @if($u?->tienePermiso('tickets.editar') || $u?->tienePermiso('tickets.crear'))
                                                <a href="{{ route('tickets.edit', $t) }}" class="cd-link font-mono font-semibold" title="Editar ticket">#{{ $t->id }}</a>
                                            @else
                                                <span class="font-mono font-semibold">#{{ $t->id }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="block text-xs font-medium cd-value">{{ $t->created_at?->format('d/m/Y') ?? '—' }}</span>
                                            <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $t->created_at?->format('H:i') }} · {{ $t->created_at?->diffForHumans() }}</span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $t->ticketAsunto?->nombre ?? 'Sin asunto' }}</span>
                                                @if($canalLabel)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $canalClase }}">
                                                        {{ $canalLabel }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($t->descripcion)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-sm mt-0.5" title="{{ $t->descripcion }}">{{ $t->descripcion }}</p>
                                            @elseif($t->nota_tecnico)
                                                <p class="text-xs text-blue-600 dark:text-blue-400 truncate max-w-sm mt-0.5">Nota: {{ $t->nota_tecnico }}</p>
                                            @endif
                                        </td>
                                        <td>
                                            @if($t->prioridad)
                                                <span class="cd-priority-pill {{ $prioridadClase }}">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $t->prioridad === 'alta' ? 'bg-red-500' : ($t->prioridad === 'media' ? 'bg-amber-500' : 'bg-gray-400') }}"></span>
                                                    {{ ucfirst($t->prioridad) }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if($t->asignado)
                                                <div class="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300">
                                                    <div class="w-5 h-5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold text-[10px] shrink-0">
                                                        {{ mb_substr($t->asignado->name, 0, 1) }}
                                                    </div>
                                                    <span class="truncate max-w-[9rem]" title="{{ $t->asignado->name }}">{{ $t->asignado->name }}</span>
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400 italic">Sin asignar</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="cd-status {{ $estadoClase }}">
                                                <span class="cd-status__dot"></span>
                                                {{ $estadosTicket[$t->estado] ?? $t->estado }}
                                            </span>
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" class="cd-icon-btn text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" data-ticket-toggle="{{ $t->id }}" title="Ver detalles y notas">
                                                    <svg class="w-4 h-4 transition-transform duration-200 pointer-events-none" data-ticket-arrow="{{ $t->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                @if($u?->tienePermiso('tickets.editar') || $u?->tienePermiso('tickets.crear'))
                                                    <a href="{{ route('tickets.edit', $t) }}" class="cd-icon-btn text-gray-400 hover:text-purple-600 dark:hover:text-purple-400" title="Editar ticket">
                                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="cd-ticket-detail-row" data-ticket-detail="{{ $t->id }}" hidden>
                                        <td colspan="7">
                                            <div class="cd-ticket-detail-box text-xs">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    <div class="space-y-1">
                                                        <p class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Descripción del reclamo</p>
                                                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-line leading-relaxed">{{ $t->descripcion ?: 'Sin descripción detallada registrada.' }}</p>
                                                    </div>
                                                    <div class="space-y-1">
                                                        <p class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Notas de visita / técnico</p>
                                                        <div class="space-y-1">
                                                            @if($t->nota_tecnico)
                                                                <p class="text-blue-700 dark:text-blue-300 font-medium">Nota: {{ $t->nota_tecnico }}</p>
                                                            @endif
                                                            @if($t->detalle_tecnico)
                                                                <p class="text-gray-700 dark:text-gray-300">{{ $t->detalle_tecnico }}</p>
                                                            @endif
                                                            @if($t->observaciones)
                                                                <p class="text-gray-600 dark:text-gray-400 italic">Obs: {{ $t->observaciones }}</p>
                                                            @endif
                                                            @if(!$t->nota_tecnico && !$t->detalle_tecnico && !$t->observaciones)
                                                                <p class="text-gray-400 italic">Sin notas técnicas cargadas.</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="space-y-1">
                                                        <p class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Información de gestión</p>
                                                        <ul class="space-y-0.5 text-gray-600 dark:text-gray-400">
                                                            <li><span class="font-medium text-gray-700 dark:text-gray-300">Creado por:</span> {{ $t->usuario?->name ?? 'Sistema / Web' }}</li>
                                                            @if($t->fecha_cierre)
                                                                <li><span class="font-medium text-gray-700 dark:text-gray-300">Cierre:</span> {{ $t->fecha_cierre->format('d/m/Y H:i') }}</li>
                                                            @endif
                                                            @if($t->factura_interna_id || ($t->monto_cobro_ticket ?? 0) > 0)
                                                                <li>
                                                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">Cobro:</span>
                                                                    {{ number_format((float) ($t->monto_cobro_ticket ?? 0), 0, ',', '.') }} PYG
                                                                    @if($t->factura_interna_id && $u?->tienePermiso('factura-interna.ver'))
                                                                        · <a href="{{ route('factura-internas.show', $t->factura_interna_id) }}" class="cd-link underline font-medium">Factura #{{ $t->factura_interna_id }}</a>
                                                                    @endif
                                                                </li>
                                                            @endif
                                                        </ul>
                                                        <div class="pt-2 flex items-center gap-3">
                                                            @if($u?->tienePermiso('tickets.crear'))
                                                                <a href="{{ route('tickets.crear-agenda', $t) }}" class="text-[11px] font-semibold text-purple-600 dark:text-purple-400 hover:underline">
                                                                    + Agendar visita
                                                                </a>
                                                            @endif
                                                            @if($cliente->servicios->isNotEmpty() && $u?->tienePermiso('servicios.ver'))
                                                                <a href="{{ route('servicios.herramientas-red', ['servicio_id' => $cliente->servicios->first()->servicio_id, 'ticket_id' => $t->id]) }}" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                                                    Diagnóstico de red &rarr;
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>

            <div class="cd-tab-panel" data-cd-panel="facturas" role="tabpanel" @if($cdTab !== 'facturas') hidden @endif>
            @php
                $totalFacturasCount = ($facturasInternas ?? collect())->count();
                $facturasPendientesLista = $facturasPendientes ?? collect();
                $facturasPagadasCount = max(0, $totalFacturasCount - $facturasPendientesLista->count());
                $totalFacturado = ($facturasInternas ?? collect())->sum('total');
                $totalCobrado = ($cobros ?? collect())->sum('monto');
                $proximaVencida = $facturasPendientesLista->sortBy(fn($f) => $f->fecha_vencimiento?->timestamp ?? PHP_INT_MAX)->first();
            @endphp

            {{-- KPIs / Resumen Financiero --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-1">
                {{-- KPI 1: Saldo Pendiente / Deuda --}}
                <div class="rounded-xl border {{ $totalPendientePago > 0 ? 'border-amber-200 dark:border-amber-900/60 bg-amber-50/40 dark:bg-amber-950/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' }} p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider {{ $totalPendientePago > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-400' }}">Saldo Pendiente</p>
                        <p class="text-xl font-bold {{ $totalPendientePago > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }} tabular-nums">
                            {{ number_format($totalPendientePago, 0, ',', '.') }} <span class="text-xs font-semibold">PYG</span>
                        </p>
                        <p class="text-[11px] {{ $totalPendientePago > 0 ? 'text-amber-600/90 dark:text-amber-400/90 font-medium' : 'text-emerald-600/90 dark:text-emerald-400/90' }} mt-0.5">
                            @if($totalPendientePago > 0)
                                {{ $facturasPendientesLista->count() }} factura(s) por cobrar
                            @else
                                Al día sin deuda
                            @endif
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-lg {{ $totalPendientePago > 0 ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' }} flex items-center justify-center shrink-0">
                        @if($totalPendientePago > 0)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                </div>

                {{-- KPI 2: Total Facturado Histórico --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Facturado</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">
                            {{ number_format($totalFacturado, 0, ',', '.') }} <span class="text-xs font-semibold text-gray-400">PYG</span>
                        </p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {{ $totalFacturasCount }} factura(s) emitida(s)
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                    </div>
                </div>

                {{-- KPI 3: Total Cobrado / Pagado --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Cobrado</p>
                        <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                            {{ number_format($totalCobrado, 0, ',', '.') }} <span class="text-xs font-semibold text-emerald-600/70 dark:text-emerald-400/70">PYG</span>
                        </p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {{ ($cobros ?? collect())->count() }} pago(s) registrado(s)
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>

                {{-- KPI 4: Saldo a Favor / Próximo Vencimiento --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex items-center justify-between shadow-xs">
                    <div>
                        @if($totalSaldoFavor > 0)
                            <p class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Saldo a Favor</p>
                            <p class="text-xl font-bold text-purple-600 dark:text-purple-400 tabular-nums">
                                {{ number_format($totalSaldoFavor, 0, ',', '.') }} <span class="text-xs font-semibold">PYG</span>
                            </p>
                            <p class="text-[11px] text-purple-500 dark:text-purple-400 mt-0.5">Disponible para aplicar</p>
                        @elseif($proximaVencida)
                            @php
                                $diasVenc = $proximaVencida->fecha_vencimiento ? (int) now()->startOfDay()->diffInDays($proximaVencida->fecha_vencimiento->startOfDay(), false) : null;
                            @endphp
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Próx. Vencimiento</p>
                            <p class="text-lg font-bold {{ $diasVenc !== null && $diasVenc < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }} tabular-nums">
                                {{ $proximaVencida->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
                            </p>
                            <p class="text-[11px] {{ $diasVenc !== null && $diasVenc < 0 ? 'text-red-500 font-semibold' : 'text-gray-400 dark:text-gray-500' }} mt-0.5">
                                @if($diasVenc !== null)
                                    {{ $diasVenc < 0 ? 'Vencida hace ' . abs($diasVenc) . ' d' : ($diasVenc == 0 ? 'Vence hoy' : 'Vence en ' . $diasVenc . ' días') }}
                                @endif
                            </p>
                        @else
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Estado de Cuenta</p>
                            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">Al día</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">Sin facturas pendientes</p>
                        @endif
                    </div>
                    <div class="w-9 h-9 rounded-lg {{ $totalSaldoFavor > 0 ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400' : 'bg-gray-50 dark:bg-gray-700 text-gray-400' }} flex items-center justify-center shrink-0">
                        @if($totalSaldoFavor > 0)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 1. Card: Facturas Generadas --}}
            <div class="cd-card min-w-0">
                <div class="cd-panel__head">
                    <div>
                        <h2>Facturas generadas</h2>
                        <p class="cd-panel__sub">{{ $totalFacturasCount }} factura(s) registrada(s) para este cliente</p>
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('cobros.crear'))
                            <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn cd-header-btn--green">
                                + Registrar Cobro
                            </a>
                        @endif
                        @if($u?->tienePermiso('facturas.crear'))
                            <a href="{{ route('facturas.generar-interna', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn">
                                Generar factura
                            </a>
                        @endif
                        @if($u?->tienePermiso('factura-interna.crear') && $facturaAccion)
                            <a href="{{ route('factura-internas.show', $facturaAccion) }}" class="cd-header-btn cd-header-btn--sky">
                                Nota de crédito
                            </a>
                        @endif
                        @if($u?->tienePermiso('factura-interna.ver'))
                            <a href="{{ route('factura-internas.index', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}" class="cd-header-btn">
                                Ver todas
                            </a>
                        @endif
                    </div>
                </div>

                @if(($facturasInternas ?? collect())->isEmpty())
                    <div class="cd-empty"><p>No hay facturas registradas para este cliente.</p></div>
                @else
                    {{-- Filtros rápidos dentro de la pestaña --}}
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-gray-100 dark:border-gray-700/60 bg-gray-50/50 dark:bg-gray-800/40">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-1">Filtrar:</span>
                        <button type="button" class="cd-filter-pill is-active" data-factura-filter="todas">
                            Todas <span class="text-[10px] font-bold opacity-75">({{ $totalFacturasCount }})</span>
                        </button>
                        <button type="button" class="cd-filter-pill" data-factura-filter="pendientes">
                            Pendientes <span class="text-[10px] font-bold opacity-75">({{ $facturasPendientesLista->count() }})</span>
                        </button>
                        <button type="button" class="cd-filter-pill" data-factura-filter="pagadas">
                            Pagadas <span class="text-[10px] font-bold opacity-75">({{ $facturasPagadasCount }})</span>
                        </button>
                    </div>

                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th class="w-16">#</th>
                                    <th>Emisión & Venc.</th>
                                    <th>Período / Concepto</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Pagado</th>
                                    <th class="text-right">Pendiente</th>
                                    <th>Estado</th>
                                    <th class="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="factura-table-body">
                                <tr id="factura-filter-empty" class="hidden">
                                    <td colspan="8" class="py-6 text-center text-sm text-gray-400">
                                        No se encontraron facturas con el filtro seleccionado.
                                    </td>
                                </tr>
                                @foreach($facturasInternas as $factura)
                                    @php
                                        $saldoFactura = (float) $factura->saldo_pendiente;
                                        $totalFactura = (float) $factura->total;
                                        $pagadoFactura = (float) $factura->monto_pagado;
                                        $vencida = $factura->fecha_vencimiento && $factura->fecha_vencimiento->isPast() && $saldoFactura > 0.009;
                                        $esPendiente = $saldoFactura > 0.009;
                                        $esParcial = $esPendiente && $pagadoFactura > 0.009;
                                        $esPagada = $saldoFactura <= 0.009 && !in_array($factura->estado, ['cancelada', 'anulada'], true);

                                        if ($factura->estado === 'cancelada' || $factura->estado === 'anulada') {
                                            $estadoClase = 'cd-status--factura-cancelada';
                                            $estadoTexto = $estadosFactura[$factura->estado] ?? ucfirst($factura->estado);
                                        } elseif ($vencida) {
                                            $estadoClase = 'cd-status--factura-vencida';
                                            $estadoTexto = 'Vencida';
                                        } elseif ($esParcial) {
                                            $estadoClase = 'cd-status--factura-parcial';
                                            $estadoTexto = 'Pago parcial';
                                        } elseif ($esPendiente) {
                                            $estadoClase = 'cd-status--factura-pendiente';
                                            $estadoTexto = 'Pendiente';
                                        } else {
                                            $estadoClase = 'cd-status--factura-pagada';
                                            $estadoTexto = 'Pagada';
                                        }
                                        $aliases = $factura->aliasesServicioTexto();
                                    @endphp
                                    <tr class="cd-factura-row" data-factura-id="{{ $factura->id }}" data-factura-pendiente="{{ $esPendiente ? '1' : '0' }}" data-factura-pagada="{{ $esPagada ? '1' : '0' }}">
                                        <td>
                                            @if($u?->tienePermiso('factura-interna.ver'))
                                                <a href="{{ route('factura-internas.show', $factura) }}" class="cd-link font-mono font-semibold" title="Ver detalle de factura">#{{ $factura->id }}</a>
                                            @else
                                                <span class="font-mono font-semibold">#{{ $factura->id }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="block text-xs font-medium cd-value">{{ $factura->fecha_emision?->format('d/m/Y') ?? '—' }}</span>
                                            @if($factura->fecha_vencimiento)
                                                <span class="block text-[11px] {{ $vencida ? 'text-red-500 dark:text-red-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                                                    Vence: {{ $factura->fecha_vencimiento->format('d/m/Y') }}
                                                    @if($vencida)
                                                        <span class="inline-block px-1 py-0.2 rounded bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300 text-[10px]">Vencida</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="block text-[11px] text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                @if($factura->esServicioEspecial())
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300">
                                                        {{ $factura->etiquetaTipoFactura() }}
                                                    </span>
                                                @elseif($factura->periodo_desde && $factura->periodo_hasta)
                                                    <span class="font-medium text-gray-900 dark:text-gray-100 text-xs">
                                                        {{ $factura->periodo_desde->format('d/m/Y') }} – {{ $factura->periodo_hasta->format('d/m/Y') }}
                                                    </span>
                                                @else
                                                    <span class="font-medium text-gray-900 dark:text-gray-100 text-xs">Servicio mensual</span>
                                                @endif
                                                @if($aliases)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                        {{ $aliases }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($factura->detalles->isNotEmpty())
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-sm mt-0.5" title="{{ $factura->detalles->first()->descripcion }}">
                                                    {{ $factura->detalles->first()->descripcion }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="text-right whitespace-nowrap tabular-nums font-medium text-gray-900 dark:text-gray-100">
                                            {{ number_format($totalFactura, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right whitespace-nowrap tabular-nums text-xs {{ $pagadoFactura > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-400' }}">
                                            {{ number_format($pagadoFactura, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right whitespace-nowrap tabular-nums font-semibold {{ $saldoFactura > 0.009 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                                            {{ number_format($saldoFactura, 0, ',', '.') }}
                                        </td>
                                        <td>
                                            <span class="cd-status {{ $estadoClase }}">
                                                <span class="cd-status__dot"></span>
                                                {{ $estadoTexto }}
                                            </span>
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" class="cd-icon-btn text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" data-factura-toggle="{{ $factura->id }}" title="Ver detalles y cobros aplicados">
                                                    <svg class="w-4 h-4 transition-transform duration-200 pointer-events-none" data-factura-arrow="{{ $factura->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                                @if($saldoFactura > 0.009 && $u?->tienePermiso('cobros.crear'))
                                                    <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id, 'factura_interna_id' => $factura->id]) }}" class="cd-icon-btn text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40" title="Cobrar factura">
                                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    </a>
                                                @endif
                                                @if($u?->tienePermiso('factura-interna.ver'))
                                                    <a href="{{ route('factura-internas.pdf', $factura) }}" target="_blank" rel="noopener noreferrer" class="cd-icon-btn text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="Descargar / Imprimir PDF">
                                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    </a>
                                                    <a href="{{ route('factura-internas.show', $factura) }}" class="cd-icon-btn text-gray-400 hover:text-purple-600 dark:hover:text-purple-400" title="Ver en módulo de facturas">
                                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="cd-factura-detail-row" data-factura-detail="{{ $factura->id }}" hidden>
                                        <td colspan="8">
                                            <div class="cd-factura-detail-box text-xs">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    {{-- Columna 1: Concepto & Desglose de ítems --}}
                                                    <div class="space-y-1.5">
                                                        <p class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Detalle de conceptos facturados</p>
                                                        @if($factura->detalles->isNotEmpty())
                                                            <div class="space-y-1">
                                                                @foreach($factura->detalles as $det)
                                                                    <div class="p-2 rounded-md bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-700/60 flex items-start justify-between gap-2">
                                                                        <div class="min-w-0">
                                                                            <p class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" title="{{ $det->descripcion }}">{{ $det->descripcion }}</p>
                                                                            <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                                                                Cant: {{ $det->cantidad ?? 1 }} · P.U.: {{ number_format((float) ($det->precio_unitario ?? 0), 0, ',', '.') }} PYG
                                                                            </p>
                                                                        </div>
                                                                        <span class="text-xs font-bold text-gray-900 dark:text-gray-100 tabular-nums shrink-0">
                                                                            {{ number_format((float) ($det->subtotal ?? 0), 0, ',', '.') }}
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <p class="text-gray-400 italic">Sin desglose de ítems disponible.</p>
                                                        @endif
                                                        @if($factura->observaciones)
                                                            <div class="pt-1">
                                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 italic"><span class="font-medium">Obs:</span> {{ $factura->observaciones }}</p>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- Columna 2: Pagos aplicados a esta factura --}}
                                                    <div class="space-y-1.5">
                                                        <p class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Pagos aplicados (Cobros)</p>
                                                        @if($factura->cobros->isNotEmpty())
                                                            <div class="space-y-1">
                                                                @foreach($factura->cobros as $fc)
                                                                    <div class="p-2 rounded-md bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-2">
                                                                        <div>
                                                                            <div class="flex items-center gap-1.5">
                                                                                @if($u?->tienePermiso('cobros.ver'))
                                                                                    <a href="{{ route('cobros.show', $fc) }}" class="font-mono text-xs font-semibold cd-link">Recibo {{ $fc->numero_recibo }}</a>
                                                                                @else
                                                                                    <span class="font-mono text-xs font-semibold text-gray-800 dark:text-gray-200">Recibo {{ $fc->numero_recibo }}</span>
                                                                                @endif
                                                                                <span class="text-[10px] text-gray-400">· {{ $fc->fecha_pago?->format('d/m/Y') }}</span>
                                                                            </div>
                                                                            <p class="text-[11px] text-gray-400 capitalize">{{ $fc->forma_pago }}</p>
                                                                        </div>
                                                                        <div class="text-right">
                                                                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                                                                {{ number_format((float) ($fc->pivot->monto ?? $fc->monto), 0, ',', '.') }} PYG
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <div class="p-2.5 rounded-md bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-700/60">
                                                                <p class="text-gray-400 italic">No hay cobros registrados aplicados a esta factura.</p>
                                                                @if($saldoFactura > 0)
                                                                    <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1 font-medium">Saldo pendiente total: {{ number_format($saldoFactura, 0, ',', '.') }} PYG</p>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- Columna 3: Información de gestión y accesos --}}
                                                    <div class="space-y-1.5">
                                                        <p class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Información de gestión</p>
                                                        <ul class="space-y-1 text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800/80 p-2.5 rounded-md border border-gray-100 dark:border-gray-700/60">
                                                            <li><span class="font-medium text-gray-700 dark:text-gray-300">Generada por:</span> {{ $factura->usuario?->name ?? 'Sistema' }}</li>
                                                            @if($factura->fecha_pago)
                                                                <li><span class="font-medium text-gray-700 dark:text-gray-300">Fecha último pago:</span> {{ $factura->fecha_pago->format('d/m/Y') }}</li>
                                                            @endif
                                                            @if($factura->promesaPago)
                                                                <li class="text-amber-600 dark:text-amber-400 font-medium">
                                                                    Promesa pactada: {{ $factura->promesaPago->fecha_promesa?->format('d/m/Y') }} ({{ $factura->promesaPago->estado }})
                                                                </li>
                                                            @endif
                                                            @if($factura->notasCredito->isNotEmpty())
                                                                <li class="text-purple-600 dark:text-purple-400 font-medium">
                                                                    Nota de crédito: -{{ number_format((float) $factura->notasCredito->sum('monto'), 0, ',', '.') }} PYG
                                                                </li>
                                                            @endif
                                                        </ul>
                                                        <div class="pt-1 flex items-center gap-3 flex-wrap">
                                                            @if($saldoFactura > 0.009 && $u?->tienePermiso('cobros.crear'))
                                                                <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id, 'factura_interna_id' => $factura->id]) }}" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                                                    + Cobrar esta factura &rarr;
                                                                </a>
                                                                <a href="{{ route('promesas-pago.create', $factura) }}" class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                                                                    Promesa de pago
                                                                </a>
                                                            @endif
                                                            @if($u?->tienePermiso('factura-interna.ver'))
                                                                <a href="{{ route('factura-internas.pdf', $factura) }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-semibold text-red-600 dark:text-red-400 hover:underline">
                                                                    Descargar PDF
                                                                </a>
                                                                <a href="{{ route('factura-internas.show', $factura) }}" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                                                    Ver en módulo &rarr;
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- 2. Card: Historial de Pagos Recibidos --}}
            <div class="cd-card min-w-0 mt-3">
                <div class="cd-panel__head">
                    <div>
                        <h2>Historial de pagos recibidos</h2>
                        <p class="cd-panel__sub">{{ ($cobros ?? collect())->count() }} pago(s) registrado(s)</p>
                    </div>
                    <div class="cd-panel__actions">
                        @if($u?->tienePermiso('cobros.crear'))
                            <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id]) }}" class="cd-header-btn cd-header-btn--green">
                                + Registrar Cobro
                            </a>
                        @endif
                    </div>
                </div>
                @if(($cobros ?? collect())->isEmpty())
                    <div class="cd-empty">
                        <p>No hay cobros registrados aún para este cliente.</p>
                    </div>
                @else
                    <div class="cd-table-scroll">
                        <table class="cd-table">
                            <thead>
                                <tr>
                                    <th>Fecha & Hora</th>
                                    <th>Recibo #</th>
                                    <th>Forma de Pago</th>
                                    <th>Concepto / Imputado</th>
                                    <th>Cajero / Operador</th>
                                    <th class="text-right">Monto</th>
                                    <th class="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cobros as $cobro)
                                    @php
                                        $formaClase = match($cobro->forma_pago) {
                                            'efectivo' => 'cd-pay-pill--efectivo',
                                            'transferencia' => 'cd-pay-pill--transferencia',
                                            'tarjeta' => 'cd-pay-pill--tarjeta',
                                            default => 'cd-pay-pill--otro',
                                        };
                                        $formaIcono = match($cobro->forma_pago) {
                                            'efectivo' => '<svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                                            'transferencia' => '<svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
                                            'tarjeta' => '<svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
                                            default => '<svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                                        };
                                        $fechaCobro = $cobro->fecha_pago ? $cobro->fecha_pago->timezone(config('app.timezone')) : null;
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap">
                                            <span class="block text-xs font-medium cd-value">{{ $fechaCobro?->format('d/m/Y') ?? '—' }}</span>
                                            <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $fechaCobro?->format('H:i') }} · {{ $fechaCobro?->diffForHumans() }}</span>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if($u?->tienePermiso('cobros.ver'))
                                                <a href="{{ route('cobros.show', $cobro) }}" class="cd-link font-mono font-semibold text-xs" title="Ver recibo de cobro">
                                                    {{ $cobro->numero_recibo }}
                                                </a>
                                            @else
                                                <span class="font-mono font-semibold text-xs text-gray-800 dark:text-gray-200">{{ $cobro->numero_recibo }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="cd-pay-pill {{ $formaClase }}">
                                                {!! $formaIcono !!}
                                                {{ ucfirst($cobro->forma_pago ?: 'otro') }}
                                            </span>
                                            @if($cobro->referencia)
                                                <span class="block text-[11px] font-mono text-gray-400 dark:text-gray-500 mt-0.5" title="Referencia bancaria: {{ $cobro->referencia }}">
                                                    Ref: {{ \Illuminate\Support\Str::limit($cobro->referencia, 18) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="max-w-md">
                                                <p class="text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $cobro->concepto }}">
                                                    {{ $cobro->concepto ?: 'Cobro de servicio' }}
                                                </p>
                                                @if($cobro->facturaInternas->isNotEmpty())
                                                    <div class="flex items-center gap-1 flex-wrap mt-0.5">
                                                        @foreach($cobro->facturaInternas as $fi)
                                                            <span class="inline-flex items-center text-[10px] font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/60 px-1.5 py-0.5 rounded">
                                                                Factura #{{ $fi->id }}
                                                                @if(isset($fi->pivot->monto))
                                                                    <span class="ml-1 text-gray-400">({{ number_format((float) $fi->pivot->monto, 0, ',', '.') }})</span>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if($cobro->usuario)
                                                <div class="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300">
                                                    <div class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 flex items-center justify-center font-bold text-[10px] shrink-0">
                                                        {{ mb_substr($cobro->usuario->name, 0, 1) }}
                                                    </div>
                                                    <span class="truncate max-w-[8rem]" title="{{ $cobro->usuario->name }}">{{ $cobro->usuario->name }}</span>
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400 italic">Sistema</span>
                                            @endif
                                        </td>
                                        <td class="text-right whitespace-nowrap tabular-nums font-semibold text-gray-900 dark:text-gray-100">
                                            {{ number_format((float) $cobro->monto, 0, ',', '.') }} <span class="text-[10px] font-normal text-gray-400">PYG</span>
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1">
                                                @if($u?->tienePermiso('cobros.ver'))
                                                    <a href="{{ route('cobros.show', $cobro) }}" class="cd-icon-btn text-gray-400 hover:text-purple-600 dark:hover:text-purple-400" title="Ver detalle del cobro">
                                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    </a>
                                                    <a href="{{ route('cobros.recibo-pdf', $cobro) }}" target="_blank" rel="noopener noreferrer" class="cd-icon-btn text-gray-400 hover:text-red-600 dark:hover:text-red-400" title="Descargar / Imprimir recibo PDF">
                                                        <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    </a>
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
                                    <th>Alias</th>
                                    <th>Plan</th>
                                    <th>Velocidad</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cliente->servicios as $s)
                                    <tr>
                                        <td>{{ $s->servicio_id }}</td>
                                        <td>{{ $s->aliasNormalizado() ?? '—' }}</td>
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
                        <div class="cd-empty"><p>No hay servicios activos para consultar en este cliente.</p></div>
                    </div>
                @else
                    @php
                        $primerServicio = $cliente->servicios->first();
                        $ticketConDiagnostico = $tickets->first(fn($t) => filled($t->datos_diagnostico));
                        $nodoDesc = $primerServicio?->pool?->router?->nodo?->descripcion;
                        $routerNom = $primerServicio?->pool?->router?->nombre;
                        $cajaNapActiva = $primerServicio?->cajaNapPuertoActivo?->cajaNap;
                        $puertoNapActivo = $primerServicio?->cajaNapPuertoActivo?->numero_puerto;
                        $oltNom = $primerServicio?->cajaNapPuertoActivo?->cajaNap?->salidaPon?->olt?->nombre
                            ?? $primerServicio?->pool?->olt?->nombre;
                        $esFibra = $primerServicio && ($primerServicio->cajaNapPuertoActivo || $primerServicio->pool?->olt || str_contains(strtolower($nodoDesc ?? ''), 'ftth') || str_contains(strtolower($primerServicio->plan?->nombre_plan ?? ''), 'ftth'));
                    @endphp

                    {{-- 1. Tarjetas de Resumen de Infraestructura & Topología de Red --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 mb-3">
                        {{-- KPI 1: IP & Conectividad --}}
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-xs">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Dirección IP CPE</span>
                                <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-base font-bold font-mono text-gray-900 dark:text-gray-100">{{ $primerServicio?->ip ?: 'Sin IP' }}</span>
                                @if($primerServicio?->ip)
                                    <button type="button" class="cd-copy-btn" data-cd-copy="{{ $primerServicio->ip }}" title="Copiar dirección IP">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 truncate">
                                Pool: {{ $primerServicio?->pool?->nombre_pool ?? 'Estándar' }}
                            </p>
                        </div>

                        {{-- KPI 2: PPPoE & Autenticación --}}
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-xs">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Usuario PPPoE</span>
                                <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-sm font-bold font-mono text-gray-900 dark:text-gray-100 truncate">{{ $primerServicio?->usuario_pppoe ?: 'Sin usuario' }}</span>
                                @if($primerServicio?->usuario_pppoe)
                                    <button type="button" class="cd-copy-btn shrink-0" data-cd-copy="{{ $primerServicio->usuario_pppoe }}" title="Copiar usuario PPPoE">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                    @if($primerServicio->password_pppoe)
                                        <button type="button" class="cd-copy-btn shrink-0 text-purple-600 dark:text-purple-400" data-cd-pppoe data-usuario="{{ $primerServicio->usuario_pppoe }}" data-password="{{ $primerServicio->password_pppoe }}" title="Ver contraseña PPPoE">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>
                                    @endif
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">
                                Autenticación activa en MikroTik
                            </p>
                        </div>

                        {{-- KPI 3: Router & Nodo --}}
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-xs">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Router & Nodo</span>
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                </div>
                            </div>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" title="{{ $routerNom ?: 'Sin router asignado' }}">
                                {{ $routerNom ?: 'Sin router' }}
                            </p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 truncate" title="{{ $nodoDesc ?: 'Sin nodo' }}">
                                Nodo: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $nodoDesc ?: '—' }}</span>
                            </p>
                        </div>

                        {{-- KPI 4: Topología Óptica (NAP / OLT / Tecnología) --}}
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-xs">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Tecnología & Caja NAP</span>
                                <div class="w-7 h-7 rounded-lg {{ $esFibra ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }} flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $esFibra ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300' }}">
                                    {{ $esFibra ? 'Fibra GPON' : 'Inalámbrico' }}
                                </span>
                                @if($oltNom)
                                    <span class="text-[11px] text-gray-400 dark:text-gray-500 truncate" title="OLT: {{ $oltNom }}">
                                        {{ $oltNom }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 truncate">
                                @if($cajaNapActiva)
                                    NAP: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $cajaNapActiva->nombre }}</span> (Pto. {{ $puertoNapActivo }})
                                @else
                                    <span class="text-gray-400 italic">Sin puerto NAP registrado</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- 2. Barra de Herramientas Operativas & Atajos --}}
                    <div class="cd-card min-w-0 mb-3">
                        <div class="cd-panel__head">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <div>
                                    <h2>Consola de Diagnóstico de Red (NOC)</h2>
                                    <p class="cd-panel__sub">
                                        Diagnóstico de CPE, potencias ópticas OLT, estado de sesión PPPoE y telemetría TR-069
                                    </p>
                                </div>
                            </div>
                            <div class="cd-panel__actions">
                                @if($primerServicio && $u?->tienePermiso('servicios.ver'))
                                    <a href="{{ route('servicios.herramientas-red', ['servicio_id' => $primerServicio->servicio_id]) }}" target="_blank" rel="noopener noreferrer" class="cd-header-btn cd-header-btn--primary flex items-center gap-1.5" title="Abrir NOC en pantalla completa">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        <span>Consola NOC externa</span>
                                    </a>
                                @endif
                                <button type="button" class="cd-header-btn" data-cd-goto-tab="servicio">
                                    Configurar servicio
                                </button>
                                <button type="button" class="cd-header-btn" data-cd-goto-tab="tickets">
                                    Tickets ({{ $tickets->count() }})
                                </button>
                            </div>
                        </div>

                        {{-- 3. Telemetría de App Móvil del Cliente (si existe en algún ticket reciente) --}}
                        @if ($ticketConDiagnostico)
                            <div class="p-3 border-b border-gray-100 dark:border-gray-700/60 bg-blue-50/30 dark:bg-blue-950/20">
                                @include('partials.ticket-diagnostico-app', [
                                    'datosDiagnostico' => $ticketConDiagnostico->datos_diagnostico,
                                    'ticketOrigen' => $ticketConDiagnostico,
                                    'wrapperClass' => '',
                                ])
                            </div>
                        @endif

                        {{-- 4. App de Herramientas de Red Vue 3 montada --}}
                        <div class="p-3 sm:p-4">
                            <div id="herramientas-red-app"></div>
                        </div>
                    </div>
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
    document.querySelectorAll('[data-cd-goto-tab]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = link.getAttribute('data-cd-goto-tab');
            if (target) {
                activate(target, true);
                root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
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
    var rows = document.querySelectorAll('[data-cd-servicio-row]');

    function showServicio(id) {
        if (!id) return;
        grids.forEach(function(grid) {
            grid.hidden = String(grid.getAttribute('data-cd-servicio-acciones')) !== String(id);
        });
        rows.forEach(function(row) {
            row.classList.toggle('is-selected', String(row.getAttribute('data-cd-servicio-row')) === String(id));
        });
        if (sel && String(sel.value) !== String(id)) {
            sel.value = String(id);
        }
    }

    if (sel) {
        sel.addEventListener('change', function() { showServicio(sel.value); });
        showServicio(sel.value);
    }

    rows.forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a, button, input, form, label')) return;
            showServicio(row.getAttribute('data-cd-servicio-row'));
        });
    });

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
<script>
(function() {
    var iconOk = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    var iconEye = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
    var iconEyeOff = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>';
    function fallbackCopy(texto) {
        var ta = document.createElement('textarea');
        ta.value = texto || '';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
    function copiar(texto, btn) {
        var done = function() {
            var prev = btn.innerHTML;
            btn.innerHTML = iconOk;
            btn.classList.add('text-emerald-500');
            setTimeout(function() {
                btn.innerHTML = prev;
                btn.classList.remove('text-emerald-500');
            }, 1400);
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
    document.querySelectorAll('[data-tv-copiar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            copiar(btn.getAttribute('data-tv-copiar') || '', btn);
        });
    });
    document.querySelectorAll('[data-tv-pass-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var wrap = toggle.parentElement;
            var mask = wrap ? wrap.querySelector('[data-tv-pass-mask]') : null;
            var pass = toggle.getAttribute('data-tv-pass') || '';
            var showing = toggle.getAttribute('aria-pressed') === 'true';
            if (mask) mask.textContent = showing ? '••••••••' : pass;
            toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
            toggle.setAttribute('title', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
            toggle.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
            toggle.innerHTML = showing ? iconEye : iconEyeOff;
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

    function showToast(icon, title) {
        if (typeof Swal !== 'undefined') {
            var Toast = Swal.mixin(Object.assign({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2400,
                timerProgressBar: true
            }, swalTheme()));
            Toast.fire({ icon: icon, title: title });
        }
    }

    document.querySelectorAll('.cd-ajax-toggle').forEach(function(toggleInput) {
        toggleInput.addEventListener('change', function() {
            var input = this;
            var toggleWrap = input.closest('.cd-toggle');
            var cardWrap = input.closest('.cd-switch-card');
            var isChecked = input.checked;
            var url = input.getAttribute('data-url');
            var paramName = input.name;
            var statusLabel = document.querySelector('[data-switch-status="' + input.getAttribute('data-status-id') + '"]');
            var badge = input.getAttribute('data-badge-selector') ? document.querySelector(input.getAttribute('data-badge-selector')) : null;
            var token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

            if (toggleWrap) toggleWrap.classList.add('is-loading');
            if (cardWrap) cardWrap.classList.toggle('is-active', isChecked);
            if (statusLabel) {
                statusLabel.textContent = isChecked ? input.getAttribute('data-text-active') : input.getAttribute('data-text-inactive');
            }
            if (badge) {
                badge.classList.toggle('hidden', !isChecked);
            }

            var formData = new FormData();
            formData.append('_token', token);
            formData.append(paramName, isChecked ? '1' : '0');

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(function(res) {
                if (!res.ok) throw new Error('Error en el servidor');
                return res.json();
            })
            .then(function(data) {
                if (toggleWrap) toggleWrap.classList.remove('is-loading');
                showToast('success', data.message || 'Configuración actualizada.');
            })
            .catch(function() {
                if (toggleWrap) toggleWrap.classList.remove('is-loading');
                input.checked = !isChecked;
                if (cardWrap) cardWrap.classList.toggle('is-active', !isChecked);
                if (statusLabel) {
                    statusLabel.textContent = !isChecked ? input.getAttribute('data-text-active') : input.getAttribute('data-text-inactive');
                }
                if (badge) {
                    badge.classList.toggle('hidden', isChecked);
                }
                showToast('error', 'No se pudo actualizar la opción.');
            });
        });
    });

    // Tickets: Acordeón de detalles y filtros
    function toggleTicket(ticketId) {
        if (!ticketId) return;
        var detailRow = document.querySelector('[data-ticket-detail="' + ticketId + '"]');
        var mainRow = document.querySelector('.cd-ticket-row[data-ticket-id="' + ticketId + '"]');
        var arrow = document.querySelector('[data-ticket-arrow="' + ticketId + '"]');
        if (!detailRow) return;

        var isHidden = detailRow.hidden;
        detailRow.hidden = !isHidden;
        if (mainRow) {
            mainRow.classList.toggle('is-open', isHidden);
        }
        if (arrow) {
            arrow.classList.toggle('rotate-180', isHidden);
        }
    }

    document.querySelectorAll('[data-ticket-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleTicket(btn.getAttribute('data-ticket-toggle'));
        });
    });

    document.querySelectorAll('.cd-ticket-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a, button, input')) return;
            var ticketId = row.getAttribute('data-ticket-id');
            toggleTicket(ticketId);
        });
    });

    var filterBtns = document.querySelectorAll('[data-ticket-filter]');
    var ticketRows = document.querySelectorAll('.cd-ticket-row');
    var emptyRow = document.getElementById('ticket-filter-empty');

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = btn.getAttribute('data-ticket-filter');
            filterBtns.forEach(function(b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');

            var visibleCount = 0;
            ticketRows.forEach(function(row) {
                var ticketId = row.getAttribute('data-ticket-id');
                var esActivo = row.getAttribute('data-ticket-activo') === '1';
                var estado = row.getAttribute('data-ticket-estado');
                var detailRow = document.querySelector('[data-ticket-detail="' + ticketId + '"]');
                var arrow = document.querySelector('[data-ticket-arrow="' + ticketId + '"]');

                var match = false;
                if (filter === 'todos') {
                    match = true;
                } else if (filter === 'activos') {
                    match = esActivo;
                } else if (filter === 'resueltos') {
                    match = (estado === 'resuelto');
                }

                if (match) {
                    row.hidden = false;
                    visibleCount++;
                } else {
                    row.hidden = true;
                    if (detailRow) detailRow.hidden = true;
                    row.classList.remove('is-open');
                    if (arrow) arrow.classList.remove('rotate-180');
                }
            });

            if (emptyRow) {
                emptyRow.classList.toggle('hidden', visibleCount > 0);
            }
        });
    });

    // Facturas: Acordeón de detalles y filtros
    function toggleFactura(facturaId) {
        if (!facturaId) return;
        var detailRow = document.querySelector('[data-factura-detail="' + facturaId + '"]');
        var mainRow = document.querySelector('.cd-factura-row[data-factura-id="' + facturaId + '"]');
        var arrow = document.querySelector('[data-factura-arrow="' + facturaId + '"]');
        if (!detailRow) return;

        var isHidden = detailRow.hidden;
        detailRow.hidden = !isHidden;
        if (mainRow) {
            mainRow.classList.toggle('is-open', isHidden);
        }
        if (arrow) {
            arrow.classList.toggle('rotate-180', isHidden);
        }
    }

    document.querySelectorAll('[data-factura-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleFactura(btn.getAttribute('data-factura-toggle'));
        });
    });

    document.querySelectorAll('.cd-factura-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a, button, input')) return;
            var facturaId = row.getAttribute('data-factura-id');
            toggleFactura(facturaId);
        });
    });

    var facturaFilterBtns = document.querySelectorAll('[data-factura-filter]');
    var facturaRows = document.querySelectorAll('.cd-factura-row');
    var facturaEmptyRow = document.getElementById('factura-filter-empty');

    facturaFilterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = btn.getAttribute('data-factura-filter');
            facturaFilterBtns.forEach(function(b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');

            var visibleCount = 0;
            facturaRows.forEach(function(row) {
                var facturaId = row.getAttribute('data-factura-id');
                var esPendiente = row.getAttribute('data-factura-pendiente') === '1';
                var esPagada = row.getAttribute('data-factura-pagada') === '1';
                var detailRow = document.querySelector('[data-factura-detail="' + facturaId + '"]');
                var arrow = document.querySelector('[data-factura-arrow="' + facturaId + '"]');

                var match = false;
                if (filter === 'todas') {
                    match = true;
                } else if (filter === 'pendientes') {
                    match = esPendiente;
                } else if (filter === 'pagadas') {
                    match = esPagada;
                }

                if (match) {
                    row.hidden = false;
                    visibleCount++;
                } else {
                    row.hidden = true;
                    if (detailRow) detailRow.hidden = true;
                    row.classList.remove('is-open');
                    if (arrow) arrow.classList.remove('rotate-180');
                }
            });

            if (facturaEmptyRow) {
                facturaEmptyRow.classList.toggle('hidden', visibleCount > 0);
            }
        });
    });

    // Copia rápida al portapapeles con Toast de SweetAlert
    function copyToClipboard(text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('success', 'Copiado: ' + text);
            }).catch(function() {
                execCopy(text);
            });
            return;
        }
        execCopy(text);
    }
    function execCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        showToast('success', 'Copiado: ' + text);
    }

    document.querySelectorAll('[data-cd-copy]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            copyToClipboard(btn.getAttribute('data-cd-copy') || '');
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
