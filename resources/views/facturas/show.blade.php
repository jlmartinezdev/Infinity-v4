@extends('layouts.app')

@section('title', 'Factura #' . $factura->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Factura {{ $factura->numero_completo ?? '#' . $factura->id }}</h1>
        @php
            $btnAccion = 'inline-flex items-center justify-center gap-2 h-9 px-3 rounded-lg text-sm font-medium whitespace-nowrap';
            $iconoAccion = 'w-4 h-4 shrink-0';
            $kudePendiente = $factura->puedeImprimirKude() && $factura->estado !== 'emitida';
            $telWhatsapp = trim((string) ($factura->receptorTelefonoEfectivo() ?? ''));
        @endphp
        <div class="flex gap-2 flex-wrap">
            @if($factura->estado === 'borrador')
                @if($factura->enColaSifen())
                    <span class="{{ $btnAccion }} bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">
                        {{ $factura->set_estado_envio === 'consultando' ? 'Consultando…' : 'En cola…' }}
                    </span>
                    <a href="{{ route('facturas.show', $factura) }}" class="{{ $btnAccion }} bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600" title="Actualizar">
                        <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Actualizar
                    </a>
                @elseif($factura->lotePendienteSifen())
                    <form action="{{ route('facturas.consultar-lote', $factura) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="{{ $btnAccion }} bg-indigo-600 text-white hover:bg-indigo-700" title="Consultar lote SIFEN">
                            <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                            Consultar
                        </button>
                    </form>
                @else
                    <a href="{{ route('facturas.edit', $factura) }}" class="{{ $btnAccion }} bg-purple-600 text-white hover:bg-purple-700" title="Editar">
                        <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Editar
                    </a>
                    <form action="{{ route('facturas.emitir', $factura) }}" method="POST" class="inline" onsubmit="return confirm('¿Emitir factura electrónica y enviar a SIFEN en segundo plano?');">
                        @csrf
                        <button type="submit" class="{{ $btnAccion }} bg-green-600 text-white hover:bg-green-700" title="Emitir e-Kuatia">
                            <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Emitir
                        </button>
                    </form>
                @endif
            @endif
            @if($factura->puedeImprimirKude())
                <a href="{{ route('facturas.kude-pos', $factura) }}" target="_blank" rel="noopener"
                   class="{{ $btnAccion }} bg-amber-600 text-white hover:bg-amber-700"
                   title="{{ $kudePendiente ? 'KuDE POS 80 mm (pendiente)' : 'KuDE POS 80 mm' }}">
                    <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    POS
                </a>
                <a href="{{ route('facturas.kude', $factura) }}"
                   class="{{ $btnAccion }} bg-blue-600 text-white hover:bg-blue-700"
                   title="{{ $kudePendiente ? 'KuDE PDF (pendiente)' : 'KuDE PDF' }}">
                    <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    PDF
                </a>
            @endif
            @if($factura->xml_path && ! str_contains((string) $factura->xml_path, 'DE_borrador_'))
                <a href="{{ route('facturas.xml', $factura) }}" class="{{ $btnAccion }} bg-gray-600 text-white hover:bg-gray-700" title="Descargar XML">
                    <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    XML
                </a>
            @endif
            @if($factura->puedeImprimirKude() && config('whatsapp.enabled'))
                <button type="button" id="btn-wa-kude" class="{{ $btnAccion }} bg-emerald-600 text-white hover:bg-emerald-700" title="Enviar KuDE por WhatsApp">
                    <svg class="{{ $iconoAccion }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                    WhatsApp
                </button>
            @endif
            @include('facturas._acciones-sifen', ['factura' => $factura, 'estilo' => 'botones', 'claseBoton' => $btnAccion, 'iconoAccion' => $iconoAccion])
            <a href="{{ route('facturas.index') }}" class="{{ $btnAccion }} bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600" title="Volver al listado">
                <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Volver
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800 text-sm">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-600">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $factura->esOcasional() ? 'Receptor (factura ocasional)' : 'Cliente' }}</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura->receptorNombreCompleto() }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $factura->receptorDocumentoEfectivo() }}</p>
                    @if($factura->receptorDireccionEfectiva())
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $factura->receptorDireccionEfectiva() }}</p>
                    @endif
                    @if($factura->esOcasional())
                        <span class="inline-flex mt-2 px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300">Ocasional</span>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fecha emisión</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura->fecha_emision->format('d/m/Y') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Tipo</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ App\Models\Factura::tiposDocumento()[$factura->tipo_documento] ?? $factura->tipo_documento }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Estado</p>
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                        @if($factura->estado === 'emitida') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                        @elseif($factura->estado === 'anulada') bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300
                        @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                        {{ App\Models\Factura::estados()[$factura->estado] ?? $factura->estado }}
                    </span>
                </div>
            </div>
            @if($factura->numero_timbrado)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">Timbrado: {{ $factura->numero_timbrado }} · Vigencia: {{ $factura->timbrado_vigencia_desde?->format('d/m/Y') }} - {{ $factura->timbrado_vigencia_hasta?->format('d/m/Y') }}</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">P. unit.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Impuesto</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800">
                    @foreach ($factura->detalles as $d)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->descripcion }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->subtotal, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->monto_impuesto, 0, ',', '.') }} @if($d->porcentaje_impuesto)({{ $d->porcentaje_impuesto }}%)@endif</td>
                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($d->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex justify-end">
                <div class="text-right space-y-1">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Subtotal: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura->subtotal, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Impuestos: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura->total_impuestos, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Total: {{ number_format($factura->total, 0, ',', '.') }} {{ $factura->moneda }}</p>
                </div>
            </div>
            @if($factura->observaciones)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"><span class="font-medium">Observaciones:</span> {{ $factura->observaciones }}</p>
            @endif
            @if($factura->enColaSifen())
                <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm">
                    <p class="font-medium text-amber-900 dark:text-amber-200 mb-1">Procesando en segundo plano</p>
                    <p class="text-xs text-amber-800 dark:text-amber-300">
                        Estado: {{ $factura->set_estado_envio === 'consultando' ? 'Consultando lote SIFEN' : 'Emitiendo / enviando a SIFEN' }}.
                        Actualice la página en unos segundos.
                    </p>
                </div>
            @endif
            @if($factura->lotePendienteSifen() && $factura->set_estado_envio !== 'consultando')
                <div class="mt-4 p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 text-sm">
                    <p class="font-medium text-indigo-900 dark:text-indigo-200 mb-1">Lote asíncrono pendiente</p>
                    <p class="text-xs text-indigo-800 dark:text-indigo-300">
                        Número de lote: <span class="font-mono font-semibold">{{ $factura->set_nro_lote }}</span>
                        · Estado: {{ ucfirst($factura->set_estado_envio) }}
                    </p>
                    <p class="text-xs text-indigo-700 dark:text-indigo-400 mt-1">DNIT puede demorar varios minutos. Ya puede imprimir el KuDE mientras espera; use «Consultar lote SIFEN» para obtener la autorización.</p>
                </div>
            @endif
            @php $sifenResumen = $factura->set_estado_envio === 'rechazado' ? $factura->respuestaSifenResumen() : null; @endphp
            @if($factura->set_estado_envio === 'rechazado')
                <div class="mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm">
                    <p class="font-medium text-red-900 dark:text-red-200 mb-1">SIFEN rechazó el documento</p>
                    @if($sifenResumen && ($sifenResumen['codigo'] || $sifenResumen['mensaje']))
                        @if(!empty($sifenResumen['codigo']))
                            <p class="text-xs text-red-800 dark:text-red-300">
                                Código: <span class="font-mono font-semibold">{{ $sifenResumen['codigo'] }}</span>
                            </p>
                        @endif
                        @if(!empty($sifenResumen['mensaje']))
                            <p class="text-xs text-red-800 dark:text-red-300 mt-1 break-words">{{ $sifenResumen['mensaje'] }}</p>
                        @endif
                        @if(!empty($sifenResumen['detalles']) && count($sifenResumen['detalles']) > 1)
                            <ul class="mt-2 list-disc list-inside text-xs text-red-700 dark:text-red-300 space-y-0.5">
                                @foreach($sifenResumen['detalles'] as $det)
                                    <li>[{{ $det['codigo'] ?? '?' }}] {{ $det['mensaje'] ?? '—' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <p class="text-xs text-red-800 dark:text-red-300">No se pudo obtener el detalle del rechazo. Revise el XML de respuesta o reintente la emisión.</p>
                    @endif
                </div>
            @endif
            @if($factura->set_cdc)
                <div class="mt-4 p-3 rounded-lg bg-gray-100 dark:bg-gray-700/50 text-sm">
                    <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">Factura electrónica (SIFEN)</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 break-all">CDC: {{ $factura->set_cdc }}</p>
                    @if($factura->set_nro_lote)
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Lote: <span class="font-mono">{{ $factura->set_nro_lote }}</span></p>
                    @endif
                    @if($factura->set_estado_envio)
                        <p class="text-xs mt-1 text-gray-600 dark:text-gray-400">
                            Estado SIFEN:
                            <span class="font-medium @if($factura->set_estado_envio === 'autorizado') text-green-700 dark:text-green-400 @elseif($factura->set_estado_envio === 'rechazado') text-red-700 dark:text-red-400 @elseif(in_array($factura->set_estado_envio, ['en_proceso', 'consultando', 'en_cola'], true)) text-indigo-700 dark:text-indigo-400 @else text-amber-700 dark:text-amber-400 @endif">
                                {{ ucfirst(str_replace('_', ' ', $factura->set_estado_envio)) }}
                            </span>
                            @if($factura->set_fecha_autorizacion)
                                · {{ $factura->set_fecha_autorizacion->format('d/m/Y H:i') }}
                            @endif
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('facturas._sifen-acciones-script')

@if($factura->puedeImprimirKude() && config('whatsapp.enabled'))
<div id="modal-wa-kude" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" data-wa-cerrar></div>
    <div class="relative mx-auto mt-20 w-full max-w-md rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Enviar KuDE por WhatsApp</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Se envía el PDF de la factura al número elegido.</p>

        <form action="{{ route('facturas.enviar-whatsapp', $factura) }}" method="POST" id="form-wa-kude" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="guardar_telefono" id="wa-guardar-telefono" value="0">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600 {{ $telWhatsapp === '' ? 'opacity-50' : '' }}">
                <input type="radio" name="destino" value="registrado" class="mt-1 text-emerald-600 focus:ring-emerald-500"
                       {{ $telWhatsapp !== '' ? 'checked' : 'disabled' }}>
                <span>
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Número registrado</span>
                    <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">
                        {{ $telWhatsapp !== '' ? $telWhatsapp : 'Sin teléfono en el receptor' }}
                    </span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
                <input type="radio" name="destino" value="otro" class="mt-1 text-emerald-600 focus:ring-emerald-500"
                       {{ $telWhatsapp === '' ? 'checked' : '' }}>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Otro número</span>
                    <input type="text" name="telefono" id="wa-telefono-otro" maxlength="40"
                           placeholder="Ej: 0981 123 456"
                           class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                           {{ $telWhatsapp === '' ? 'required' : 'disabled' }}>
                </span>
            </label>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-wa-cerrar
                        class="{{ $btnAccion }} bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                    <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancelar
                </button>
                <button type="submit" id="btn-wa-kude-enviar"
                        class="{{ $btnAccion }} bg-emerald-600 text-white hover:bg-emerald-700">
                    <svg class="{{ $iconoAccion }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                    <span id="btn-wa-kude-enviar-label">Enviar</span>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('modal-wa-kude');
    var btn = document.getElementById('btn-wa-kude');
    if (!modal || !btn) return;

    var inputOtro = document.getElementById('wa-telefono-otro');
    var radios = modal.querySelectorAll('input[name="destino"]');
    var form = document.getElementById('form-wa-kude');
    var btnEnviar = document.getElementById('btn-wa-kude-enviar');
    var guardarInput = document.getElementById('wa-guardar-telefono');
    var telRegistrado = @json($telWhatsapp);
    var enviando = false;

    function syncDestino() {
        var destino = (modal.querySelector('input[name="destino"]:checked') || {}).value;
        var esOtro = destino === 'otro';
        if (inputOtro) {
            inputOtro.disabled = !esOtro;
            inputOtro.required = esOtro;
            if (esOtro) inputOtro.focus();
        }
    }
    function abrir() {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        syncDestino();
    }
    function cerrar() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }

    btn.addEventListener('click', abrir);
    modal.querySelectorAll('[data-wa-cerrar]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!enviando) cerrar();
        });
    });
    radios.forEach(function (r) {
        r.addEventListener('change', syncDestino);
    });
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function digitos(s) {
        return String(s || '').replace(/\D/g, '');
    }

    function marcarEnviando(guardar) {
        if (guardarInput) guardarInput.value = guardar ? '1' : '0';
        enviando = true;
        if (btnEnviar) {
            btnEnviar.disabled = true;
            var label = document.getElementById('btn-wa-kude-enviar-label');
            if (label) label.textContent = 'Enviando…';
        }
    }

    function enviarAhora(guardar) {
        marcarEnviando(guardar);
        form.submit();
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            if (enviando) {
                e.preventDefault();
                return;
            }
            if (guardarInput) guardarInput.value = '0';
            var destino = (modal.querySelector('input[name="destino"]:checked') || {}).value;
            var tel = inputOtro ? String(inputOtro.value || '').trim() : '';
            var esOtroNuevo = destino === 'otro' && tel !== '' && digitos(tel) !== digitos(telRegistrado);

            if (!esOtroNuevo) {
                marcarEnviando(false);
                return;
            }

            e.preventDefault();
            cerrar();

            if (typeof Swal === 'undefined') {
                enviarAhora(window.confirm('¿Guardar este número en el receptor?'));
                return;
            }

            Swal.fire(Object.assign({
                icon: 'question',
                title: '¿Guardar este número?',
                html: telRegistrado
                    ? 'Se enviará a <strong>' + esc(tel) + '</strong>.<br>El número actual es <strong>' + esc(telRegistrado) + '</strong>.'
                    : 'Se enviará a <strong>' + esc(tel) + '</strong>. ¿Lo guardamos en el receptor?',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Guardar y enviar',
                denyButtonText: 'Solo enviar',
                cancelButtonText: 'Volver',
                confirmButtonColor: '#059669',
                denyButtonColor: '#334155',
                focusCancel: false,
            }, typeof infinitySwalTheme === 'function' ? infinitySwalTheme() : {})).then(function (result) {
                if (result.isConfirmed) enviarAhora(true);
                else if (result.isDenied) enviarAhora(false);
                else abrir();
            });
        });
    }
})();
</script>
@endif
@endsection
