@extends('layouts.app')

@section('title', 'KuDE POS ' . ($factura->numero_completo ?? $factura->id))

@section('content')
<div class="max-w-lg mx-auto" id="kude-pos-print">
    <div class="mb-4 flex flex-wrap gap-2 print:hidden">
        <a href="{{ route('facturas.show', $factura) }}" class="text-blue-600 dark:text-blue-400 hover:underline text-sm font-medium">&larr; Volver a factura</a>
        <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
            Imprimir ticket 80 mm
        </button>
    </div>

    <div id="kude-pos-contenido" class="kude-pos-ticket bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 mx-auto shadow border border-gray-300 dark:border-gray-600 rounded-lg print:shadow-none print:border-0 print:rounded-none">
        @if(!empty($logoBase64))
            <div class="text-center mb-2">
                <img src="{{ $logoBase64 }}" alt="Logo" class="kude-pos-logo inline-block">
            </div>
        @endif

        <p class="text-center font-bold text-sm uppercase leading-tight">{{ $nombreEmisor }}</p>
        @if($config->descripcion_actividad_economica)
            <p class="text-center text-xs leading-tight mt-0.5">{{ $config->descripcion_actividad_economica }}</p>
        @endif
        <p class="text-center text-xs mt-1">RUC: {{ $config->ruc }}-{{ $config->dv_ruc }}</p>
        <p class="text-center text-xs">{{ $direccionEmisor }}</p>
        @if($config->telefono || $config->email)
            <p class="text-center text-xs">{{ $config->telefono }}@if($config->telefono && $config->email) · @endif{{ $config->email }}</p>
        @endif

        <div class="kude-pos-sep"></div>

        <p class="text-center text-xs font-bold uppercase">{{ $factura->descripcionTipoDocumentoSifen() }}</p>
        <p class="text-center text-sm font-bold">Nº {{ $factura->numero_completo ?? '—' }}</p>
        <p class="text-center text-xs">Timbrado: {{ $factura->numero_timbrado ?? $config->numero_timbrado }}</p>
        <p class="text-center text-xs">Vigencia: {{ $vigenciaTimbradoFmt }}</p>

        <div class="kude-pos-sep"></div>

        <p class="text-xs"><span class="font-semibold">Fecha:</span> {{ $fechaEmisionFmt }}</p>
        <p class="text-xs"><span class="font-semibold">Cliente:</span> {{ $factura->receptorNombreCompleto() }}</p>
        <p class="text-xs"><span class="font-semibold">RUC/CI:</span> {{ $factura->receptorDocumentoEfectivo() }}</p>
        @if($factura->receptorDireccionEfectiva())
            <p class="text-xs"><span class="font-semibold">Dir.:</span> {{ $factura->receptorDireccionEfectiva() }}</p>
        @endif
        @if($factura->esFacturaComercial())
            <p class="text-xs"><span class="font-semibold">Condición:</span> {{ $condicionVenta }}</p>
        @endif
        <p class="text-xs"><span class="font-semibold">Moneda:</span> {{ $monedaDescripcion }}</p>

        <div class="kude-pos-sep"></div>

        @foreach($lineas as $linea)
            <div class="kude-pos-item text-xs">
                <p class="font-medium leading-tight">{{ $linea['descripcion'] }}</p>
                <p class="flex justify-between gap-2">
                    <span>{{ number_format($linea['cantidad'], 2, ',', '.') }} x {{ number_format($linea['precio_unitario'], 0, ',', '.') }}</span>
                    <span class="font-semibold shrink-0">{{ number_format($linea['importe'], 0, ',', '.') }}</span>
                </p>
            </div>
        @endforeach

        <div class="kude-pos-sep"></div>

        <p class="flex justify-between text-xs"><span>Subtotal exentas</span><span>{{ number_format($sumExentas, 0, ',', '.') }}</span></p>
        <p class="flex justify-between text-xs"><span>Gravadas 5%</span><span>{{ number_format($sumGrav5, 0, ',', '.') }}</span></p>
        <p class="flex justify-between text-xs"><span>Gravadas 10%</span><span>{{ number_format($sumGrav10, 0, ',', '.') }}</span></p>
        <p class="flex justify-between text-xs mt-1"><span>IVA 5%</span><span>{{ number_format($iva5, 0, ',', '.') }}</span></p>
        <p class="flex justify-between text-xs"><span>IVA 10%</span><span>{{ number_format($iva10, 0, ',', '.') }}</span></p>

        <div class="kude-pos-sep"></div>

        <p class="flex justify-between text-sm font-bold">
            <span>TOTAL</span>
            <span>{{ number_format($factura->total, 0, ',', '.') }} {{ $factura->moneda }}</span>
        </p>

        @if($factura->observaciones)
            <div class="kude-pos-sep"></div>
            <p class="text-xs"><span class="font-semibold">Obs.:</span> {{ $factura->observaciones }}</p>
        @endif

        <div class="kude-pos-sep"></div>

        @if(!empty($qrImageUrl))
            <div class="text-center my-2">
                <img src="{{ $qrImageUrl }}" alt="QR SIFEN" class="kude-pos-qr inline-block">
            </div>
        @endif

        <p class="text-center text-[10px] leading-tight">Consulte en {{ $consultaUrl }}/</p>
        <p class="text-center text-xs mt-1">{{ $cdcFormateado ?? $factura->set_cdc }}</p>
        <p class="text-center text-[10px] font-semibold mt-2 leading-tight">REPRESENTACIÓN GRÁFICA DE DOCUMENTO ELECTRÓNICO (XML)</p>
        <p class="text-center text-[10px] mt-1">{{ $factura->tituloKude() }}</p>
    </div>
</div>

<style>
.kude-pos-ticket {
    max-width: 80mm;
    padding: 10px 8px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 11px;
    line-height: 1.35;
}
.kude-pos-logo { max-height: 48px; max-width: 120px; }
.kude-pos-qr { width: 150px; height: 150px; }
.kude-pos-sep {
    border-top: 1px dashed currentColor;
    opacity: 0.45;
    margin: 8px 0;
}
.kude-pos-item + .kude-pos-item { margin-top: 6px; }

@media print {
    @page { margin: 3mm; size: 80mm auto; }
    body * { visibility: hidden; }
    #kude-pos-print, #kude-pos-print * { visibility: visible; }
    #kude-pos-print {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: none !important;
        margin: 0;
        padding: 0;
    }
    #kude-pos-contenido {
        max-width: 80mm !important;
        margin: 0 auto;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        color: #000 !important;
        background: #fff !important;
    }
    #kude-pos-contenido * { color: #000 !important; }
    .kude-pos-sep { border-color: #000; opacity: 1; }
}
</style>
@endsection
