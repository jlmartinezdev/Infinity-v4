<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $cobro->numero_recibo }}</title>
    <style>
        @page { margin: 4mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; padding: 4px 6px; max-width: 100%; }
        .center { text-align: center; }
        .logo { max-height: 48px; max-width: 100%; margin: 0 auto 6px; display: block; }
        .empresa { font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 0 0 4px 0; }
        .contacto { font-size: 9px; color: #444; line-height: 1.35; }
        .linea { border-top: 1px solid #666; margin: 10px 0; }
        .mono { font-size: 10px; }
        .row { display: table; width: 100%; margin: 3px 0; }
        .row span { display: table-cell; }
        .row .r { text-align: right; }
        .bold { font-weight: bold; }
        .total { font-size: 14px; margin-top: 6px; }
        .pie { text-align: center; margin-top: 14px; font-size: 9px; text-transform: uppercase; }
        .muted { color: #555; }
        .pl { padding-left: 8px; border-left: 2px solid #ccc; margin: 6px 0; }
    </style>
</head>
<body>
    <div class="center">
        @if(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" alt="" class="logo">
        @elseif($ajustes && $ajustes->nombre_empresa)
            <p class="empresa">{{ $ajustes->nombre_empresa }}</p>
        @else
            <p class="empresa">{{ config('app.name') }}</p>
        @endif
        @if($ajustes && ($ajustes->direccion || $ajustes->telefono || $ajustes->sitio_web))
            <div class="contacto">
                @if($ajustes->direccion)<p style="margin:0;">{{ $ajustes->direccion }}</p>@endif
                @if($ajustes->telefono)<p style="margin:0;">Tel: {{ $ajustes->telefono }}</p>@endif
                @if($ajustes->email)<p style="margin:0;">{{ $ajustes->email }}</p>@endif
                @if($ajustes->sitio_web)<p style="margin:0;">{{ $ajustes->sitio_web }}</p>@endif
            </div>
        @endif
    </div>

    <div class="linea"></div>

    <div class="mono">
        <p style="margin:0;">{{ $cobro->fecha_pago->format('d/m/Y H:i') }}</p>
        <div class="row">
            <span>RECIBO: #{{ $cobro->numero_recibo }}</span>
            @if(!empty($esMulticobro) && isset($indice, $total))
                <span class="r muted">Recibo {{ $indice }} de {{ $total }}</span>
            @endif
        </div>
    </div>

    <div class="linea"></div>

    <div class="mono">
        <p style="margin:4px 0;"><span class="bold">CLIENTE:</span> {{ $cobro->cliente->nombre }} {{ $cobro->cliente->apellido }}</p>
        <p style="margin:4px 0;">CÉDULA: {{ $cobro->cliente->cedula ?? '—' }}</p>
        @if($cobro->cliente->direccion)
            <p style="margin:4px 0;">DIRECCIÓN: {{ $cobro->cliente->direccion }}</p>
        @endif
    </div>

    @php $facturas = $cobro->facturaInternas ?? collect(); @endphp
    @if($facturas->isNotEmpty())
        <div class="mono" style="margin-top:8px;">
            <p class="bold" style="margin:0 0 4px 0;">{{ $facturas->count() > 1 ? 'FACTURAS INTERNAS' : 'FACTURA INTERNA' }}</p>
            @foreach($facturas as $fi)
                <div class="pl">
                    <div class="row">
                        <span>#{{ $fi->id }}</span>
                        <span class="r">{{ number_format($fi->pivot->monto ?? $fi->total, 0, ',', '.') }} PYG</span>
                    </div>
                    <p class="muted" style="margin:2px 0 0 0;">Período: {{ $fi->periodo_desde?->format('d/m/Y') }} - {{ $fi->periodo_hasta?->format('d/m/Y') }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if($cobro->concepto)
        <div class="mono" style="margin-top:8px;">
            <p style="margin:0;">{{ $cobro->concepto }}</p>
        </div>
    @endif

    <div class="linea"></div>

    <div class="row mono">
        <span class="bold">TOTAL:</span>
        <span class="r bold total">{{ number_format($cobro->monto, 0, ',', '.') }} PYG</span>
    </div>

    <div class="linea"></div>

    <div class="mono">
        <div class="row">
            <span>FORMA DE PAGO:</span>
            <span class="r">{{ \App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago }}</span>
        </div>
        @if($cobro->referencia)
            <div class="row">
                <span>REF:</span>
                <span class="r">{{ $cobro->referencia }}</span>
            </div>
        @endif
        <div class="row">
            <span>CAJERO:</span>
            <span class="r">{{ $cobro->usuario?->name ?? '—' }}</span>
        </div>
    </div>

    @if($cobro->observaciones)
        <div class="mono" style="margin-top:8px;">
            <p class="bold" style="margin:0;">OBS:</p>
            <p style="margin:4px 0 0 0;">{{ $cobro->observaciones }}</p>
        </div>
    @endif

    <div class="linea"></div>

    <div class="pie">
        <p class="bold" style="margin:0;">¡Gracias por su pago!</p>
        <p style="margin:6px 0 0 0;">Válido como comprobante</p>
        <p class="muted" style="margin:8px 0 0 0;">#{{ $cobro->numero_recibo }}</p>
    </div>
</body>
</html>
