@php
    use App\Models\Servicio;
    use Illuminate\Support\Str;
    $ascii = fn (?string $s): string => Str::ascii($s ?? '');
    $facturas = $cobro->facturaInternas ?? collect();
    $reciboLineaSimple = !empty($reciboLineaSimple);
    $reciboSinGrafico = !empty($reciboSinGrafico);
    $saldoAFavorTotal = 0.0;
    if ($cobro->cliente) {
        if ($cobro->cliente->relationLoaded('servicios')) {
            $saldoAFavorTotal = (float) $cobro->cliente->servicios->sum(fn ($s) => (float) ($s->saldo_a_favor ?? 0));
        } else {
            $saldoAFavorTotal = (float) Servicio::where('cliente_id', $cobro->cliente->cliente_id)->sum('saldo_a_favor');
        }
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $cobro->numero_recibo }}</title>
    <style>
        @page { margin: 4mm; }
        body { font-family: {{ ($reciboSinGrafico && !$reciboLineaSimple) || $reciboLineaSimple ? "DejaVu Sans Mono, monospace" : "DejaVu Sans, sans-serif" }}; font-size: {{ $reciboLineaSimple ? "10px" : (($reciboSinGrafico && !$reciboLineaSimple) ? "10px" : "9px") }}; color: #111; margin: 0; padding: 4px 6px; max-width: 100%; }
        .center { text-align: center; }
        .logo { max-height: 48px; max-width: 100%; margin: 0 auto 6px; display: block; }
        .empresa { font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 0 0 4px 0; }
        .contacto { font-size: 9px; color: #444; line-height: 1.35; }
        .linea { border-top: {{ ($reciboSinGrafico && !$reciboLineaSimple) ? "2px dashed #000" : "1px solid #666" }}; margin: 10px 0; }
        .mono { font-size: 10px; }
        .row { display: table; width: 100%; margin: 3px 0; }
        .row span { display: table-cell; }
        .row .r { text-align: right; }
        .bold { font-weight: bold; }
        .total { font-size: 14px; margin-top: 6px; }
        .pie { text-align: center; margin-top: 14px; font-size: 9px; text-transform: uppercase; }
        .muted { color: #555; }
        .pl { padding-left: 8px; border-left: 2px solid #ccc; margin: 6px 0; }
        .linea-simple p { margin: 3px 0; }
    </style>
</head>
<body class="{{ $reciboLineaSimple ? 'linea-simple' : '' }}">
@if($reciboLineaSimple)
    <div class="linea-simple mono">
        <p class="empresa" style="text-align:left;margin:0 0 6px 0;">{{ $ascii($ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name')) }}</p>
        @if($ajustes && ($ajustes->direccion || $ajustes->telefono || $ajustes->email || $ajustes->sitio_web))
            <div class="contacto" style="text-align:left;">
                @if($ajustes->direccion)<p style="margin:0;">{{ $ascii($ajustes->direccion) }}</p>@endif
                @if($ajustes->telefono)<p style="margin:0;">TEL: {{ $ascii($ajustes->telefono) }}</p>@endif
                @if($ajustes->email)<p style="margin:0;">{{ $ascii($ajustes->email) }}</p>@endif
                @if($ajustes->sitio_web)<p style="margin:0;">{{ $ascii($ajustes->sitio_web) }}</p>@endif
            </div>
        @endif
        <p style="margin:8px 0 0 0;">FECHA: {{ $cobro->fecha_pago->format('d/m/Y H:i') }}</p>
        <p style="margin:3px 0;">RECIBO: #{{ $cobro->numero_recibo }}</p>
        @if(!empty($esMulticobro) && isset($indice, $total))
            <p style="margin:3px 0;">RECIBO: {{ $indice }} / {{ $total }}</p>
        @endif
        <p style="margin:8px 0 0 0;">CLIENTE: {{ $ascii($cobro->cliente->nombre) }} {{ $ascii($cobro->cliente->apellido) }}</p>
        <p style="margin:3px 0;">CEDULA: {{ $ascii($cobro->cliente->cedula ?? '') ?: '—' }}</p>
        @if($cobro->cliente->direccion)
            <p style="margin:3px 0;">DIRECCION: {{ $ascii($cobro->cliente->direccion) }}</p>
        @endif
        @if($facturas->isNotEmpty())
            <p class="bold" style="margin:8px 0 4px 0;">{{ $facturas->count() > 1 ? 'FACTURAS INTERNAS' : 'FACTURA INTERNA' }}</p>
            @foreach($facturas as $fi)
                <p style="margin:3px 0;">#{{ $fi->id }} {{ number_format($fi->pivot->monto ?? $fi->total, 0, ',', '.') }} PYG | PERIODO {{ $fi->periodo_desde?->format('d/m/Y') }} - {{ $fi->periodo_hasta?->format('d/m/Y') }}</p>
            @endforeach
        @endif
        @if($cobro->concepto)
            <p style="margin:6px 0 0 0;">{{ $ascii($cobro->concepto) }}</p>
        @endif
        <p class="bold" style="margin:8px 0 0 0;">TOTAL: {{ number_format($cobro->monto, 0, ',', '.') }} PYG</p>
        @if($saldoAFavorTotal > 0)
            <p style="margin:3px 0;">SALDO A FAVOR: {{ number_format($saldoAFavorTotal, 0, ',', '.') }} PYG</p>
        @endif
        <p style="margin:3px 0;">FORMA DE PAGO: {{ $ascii(\App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago) }}</p>
        @if($cobro->referencia)
            <p style="margin:3px 0;">REF: {{ $ascii($cobro->referencia) }}</p>
        @endif
        <p style="margin:3px 0;">CAJERO: {{ $ascii($cobro->usuario?->name ?? '') ?: '—' }}</p>
        @if($cobro->observaciones)
            <p style="margin:6px 0 0 0;">OBS: {{ $ascii($cobro->observaciones) }}</p>
        @endif
        <div class="pie">
            <p class="bold" style="margin:12px 0 0 0;">GRACIAS POR SU PAGO</p>
            <p style="margin:6px 0 0 0;">VALIDO COMO COMPROBANTE</p>
            <p class="muted" style="margin:8px 0 0 0;">#{{ $cobro->numero_recibo }}</p>
        </div>
    </div>
@else
    <div class="center">
        @if($reciboSinGrafico)
            <p class="empresa">{{ $ascii($ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name')) }}</p>
        @elseif(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" alt="" class="logo">
        @elseif($ajustes && $ajustes->nombre_empresa)
            <p class="empresa">{{ $ascii($ajustes->nombre_empresa) }}</p>
        @else
            <p class="empresa">{{ $ascii(config('app.name')) }}</p>
        @endif
        @if($ajustes && ($ajustes->direccion || $ajustes->telefono || $ajustes->sitio_web))
            <div class="contacto">
                @if($ajustes->direccion)<p style="margin:0;">{{ $ascii($ajustes->direccion) }}</p>@endif
                @if($ajustes->telefono)<p style="margin:0;">TEL: {{ $ascii($ajustes->telefono) }}</p>@endif
                @if($ajustes->email)<p style="margin:0;">{{ $ascii($ajustes->email) }}</p>@endif
                @if($ajustes->sitio_web)<p style="margin:0;">{{ $ascii($ajustes->sitio_web) }}</p>@endif
            </div>
        @endif
    </div>

    <div class="linea"></div>

    <div class="mono">
        <p style="margin:0;">{{ $cobro->fecha_pago->format('d/m/Y H:i') }}</p>
        <div class="row">
            <span>RECIBO: #{{ $cobro->numero_recibo }}</span>
            @if(!empty($esMulticobro) && isset($indice, $total))
                <span class="r muted">RECIBO {{ $indice }} / {{ $total }}</span>
            @endif
        </div>
    </div>

    <div class="linea"></div>

    <div class="mono">
        <p style="margin:4px 0;"><span class="bold">CLIENTE:</span> {{ $ascii($cobro->cliente->nombre) }} {{ $ascii($cobro->cliente->apellido) }}</p>
        <p style="margin:4px 0;">CEDULA: {{ $ascii($cobro->cliente->cedula ?? '') ?: '—' }}</p>
        @if($cobro->cliente->direccion)
            <p style="margin:4px 0;">DIRECCION: {{ $ascii($cobro->cliente->direccion) }}</p>
        @endif
    </div>

    @if($facturas->isNotEmpty())
        <div class="mono" style="margin-top:8px;">
            <p class="bold" style="margin:0 0 4px 0;">{{ $facturas->count() > 1 ? 'FACTURAS INTERNAS' : 'FACTURA INTERNA' }}</p>
            @foreach($facturas as $fi)
                <div class="pl">
                    <div class="row">
                        <span>#{{ $fi->id }}</span>
                        <span class="r">{{ number_format($fi->pivot->monto ?? $fi->total, 0, ',', '.') }} PYG</span>
                    </div>
                    <p class="muted" style="margin:2px 0 0 0;">PERIODO: {{ $fi->periodo_desde?->format('d/m/Y') }} - {{ $fi->periodo_hasta?->format('d/m/Y') }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if($cobro->concepto)
        <div class="mono" style="margin-top:8px;">
            <p style="margin:0;">{{ $ascii($cobro->concepto) }}</p>
        </div>
    @endif

    <div class="linea"></div>

    <div class="row mono">
        <span class="bold">TOTAL:</span>
        <span class="r bold total">{{ number_format($cobro->monto, 0, ',', '.') }} PYG</span>
    </div>

    @if($saldoAFavorTotal > 0)
        <div class="row mono" style="margin-top:4px;">
            <span class="bold">SALDO A FAVOR:</span>
            <span class="r">{{ number_format($saldoAFavorTotal, 0, ',', '.') }} PYG</span>
        </div>
    @endif

    <div class="linea"></div>

    <div class="mono">
        <div class="row">
            <span>FORMA DE PAGO:</span>
            <span class="r">{{ $ascii(\App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago) }}</span>
        </div>
        @if($cobro->referencia)
            <div class="row">
                <span>REF:</span>
                <span class="r">{{ $ascii($cobro->referencia) }}</span>
            </div>
        @endif
        <div class="row">
            <span>CAJERO:</span>
            <span class="r">{{ $ascii($cobro->usuario?->name ?? '') ?: '—' }}</span>
        </div>
    </div>

    @if($cobro->observaciones)
        <div class="mono" style="margin-top:8px;">
            <p class="bold" style="margin:0;">OBS:</p>
            <p style="margin:4px 0 0 0;">{{ $ascii($cobro->observaciones) }}</p>
        </div>
    @endif

    <div class="linea"></div>

    <div class="pie">
        <p class="bold" style="margin:0;">GRACIAS POR SU PAGO</p>
        <p style="margin:6px 0 0 0;">VALIDO COMO COMPROBANTE</p>
        <p class="muted" style="margin:8px 0 0 0;">#{{ $cobro->numero_recibo }}</p>
    </div>
@endif
</body>
</html>
