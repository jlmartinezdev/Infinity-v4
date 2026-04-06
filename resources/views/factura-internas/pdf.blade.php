<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 0; padding: 24px; }
        .header { width: 100%; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { max-height: 56px; max-width: 200px; }
        .empresa { font-size: 14px; font-weight: bold; margin: 0 0 4px 0; }
        .meta { color: #4b5563; font-size: 9px; line-height: 1.4; }
        .titulo-doc { text-align: right; }
        .titulo-doc h1 { font-size: 16px; margin: 0 0 4px 0; letter-spacing: 0.05em; }
        .titulo-doc .num { font-size: 12px; color: #374151; }
        .bloque { margin-bottom: 16px; }
        .bloque h2 { font-size: 10px; text-transform: uppercase; color: #6b7280; margin: 0 0 6px 0; font-weight: bold; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        table.lines th { background: #f9fafb; font-size: 9px; text-transform: uppercase; color: #6b7280; }
        table.lines td { font-size: 10px; }
        .right { text-align: right; }
        .totals { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 4px 0; font-size: 10px; }
        .totals .label { color: #4b5563; }
        .totals .grand { font-size: 13px; font-weight: bold; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .cobros { margin-top: 16px; font-size: 9px; color: #374151; }
        .cobros ul { margin: 4px 0 0 16px; padding: 0; }
        .footer { margin-top: 24px; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                    @endif
                    @if($ajustes)
                        <p class="empresa">{{ $ajustes->nombre_empresa ?? 'Empresa' }}</p>
                        <div class="meta">
                            @if($ajustes->direccion){{ $ajustes->direccion }}<br>@endif
                            @if($ajustes->telefono)Tel. {{ $ajustes->telefono }}@endif
                            @if($ajustes->email) · {{ $ajustes->email }}@endif
                            @if($ajustes->sitio_web)<br>{{ $ajustes->sitio_web }}@endif
                        </div>
                    @endif
                </td>
                <td class="titulo-doc" style="width: 40%;">
                    <h1>FACTURA INTERNA</h1>
                    <p class="num">N.º {{ $factura_interna->id }}</p>
                    <p class="meta" style="margin-top: 8px;">
                        Emisión: {{ $factura_interna->fecha_emision->format('d/m/Y') }}<br>
                        @if($factura_interna->fecha_vencimiento)
                            Vencimiento: {{ $factura_interna->fecha_vencimiento->format('d/m/Y') }}<br>
                        @endif
                        Período: {{ $factura_interna->periodo_desde->format('d/m/Y') }} – {{ $factura_interna->periodo_hasta->format('d/m/Y') }}
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="bloque">
        <h2>Cliente</h2>
        <p style="margin: 0; font-size: 11px;">
            <strong>{{ $factura_interna->cliente->nombre }} {{ $factura_interna->cliente->apellido }}</strong><br>
            {{ $factura_interna->cliente->cedula }}
        </p>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="text-align: left;">Descripción</th>
                <th class="right" style="width: 12%;">Cant.</th>
                <th class="right" style="width: 14%;">P. unit.</th>
                <th class="right" style="width: 14%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($factura_interna->detalles as $d)
                <tr>
                    <td>{{ $d->descripcion }}</td>
                    <td class="right">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($d->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td class="label">Subtotal</td>
                <td class="right">{{ number_format($factura_interna->subtotal, 0, ',', '.') }} {{ $factura_interna->moneda }}</td>
            </tr>
            <tr>
                <td class="label">Impuestos</td>
                <td class="right">{{ number_format($factura_interna->total_impuestos, 0, ',', '.') }} {{ $factura_interna->moneda }}</td>
            </tr>
            @if((float) ($factura_interna->descuento ?? 0) > 0)
                <tr>
                    <td class="label">Descuento</td>
                    <td class="right">−{{ number_format($factura_interna->descuento, 0, ',', '.') }} {{ $factura_interna->moneda }}</td>
                </tr>
            @endif
            <tr>
                <td class="grand">Total</td>
                <td class="right grand">{{ number_format($factura_interna->total, 0, ',', '.') }} {{ $factura_interna->moneda }}</td>
            </tr>
            <tr>
                <td class="label">Cobrado</td>
                <td class="right">{{ number_format($factura_interna->monto_pagado, 0, ',', '.') }} {{ $factura_interna->moneda }}</td>
            </tr>
            <tr>
                <td class="label">Saldo pendiente</td>
                <td class="right">{{ number_format($factura_interna->saldo_pendiente, 0, ',', '.') }} {{ $factura_interna->moneda }}@if($factura_interna->esta_pagada) (Pagada)@endif</td>
            </tr>
        </table>
    </div>

    @if($factura_interna->cobros->isNotEmpty())
        <div class="cobros">
            <strong>Cobros aplicados</strong>
            <ul>
                @foreach($factura_interna->cobros as $cobro)
                    <li>
                        {{ $cobro->numero_recibo }} — {{ $cobro->fecha_pago->format('d/m/Y H:i') }} — {{ number_format($cobro->pivot->monto ?? $cobro->monto, 0, ',', '.') }} {{ $factura_interna->moneda }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($factura_interna->observaciones)
        <div class="bloque" style="margin-top: 16px;">
            <h2>Observaciones</h2>
            <p style="margin: 0; font-size: 9px; white-space: pre-wrap;">{{ $factura_interna->observaciones }}</p>
        </div>
    @endif

    <div class="footer">
        Documento interno · generado {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
