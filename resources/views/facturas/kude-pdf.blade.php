<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>KuDE {{ $factura->numero_completo ?? $factura->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .logo { max-height: 60px; max-width: 180px; }
        .empresa { font-size: 14px; font-weight: bold; margin: 0 0 4px; }
        .meta { font-size: 10px; color: #444; line-height: 1.4; }
        .titulo-doc { text-align: right; }
        .titulo-doc h1 { font-size: 18px; margin: 0 0 6px; }
        .titulo-doc .num { font-size: 13px; font-weight: bold; }
        .bloque { margin-bottom: 14px; }
        .bloque h2 { font-size: 11px; text-transform: uppercase; color: #555; margin: 0 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 8px; }
        table.lines th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .totales { margin-top: 12px; text-align: right; }
        .totales .total { font-size: 14px; font-weight: bold; margin-top: 4px; }
        .cdc-box { margin-top: 16px; padding: 10px; border: 1px dashed #999; font-size: 10px; }
        .cdc-box strong { display: block; margin-bottom: 4px; }
        .qr-box { text-align: center; margin-top: 12px; }
        .qr-box img { width: 120px; height: 120px; }
        .footer { margin-top: 20px; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo"><br>
                @endif
                <p class="empresa">{{ $config->razon_social }}</p>
                <div class="meta">
                    RUC: {{ $config->ruc }}-{{ $config->dv_ruc }}<br>
                    {{ $config->direccion }}<br>
                    Tel. {{ $config->telefono }} · {{ $config->email }}
                </div>
            </td>
            <td class="titulo-doc" style="width: 42%; vertical-align: top;">
                <h1>KuDE — FACTURA ELECTRÓNICA</h1>
                <p class="num">{{ $factura->numero_completo ?? '—' }}</p>
                <p class="meta" style="margin-top: 8px;">
                    Timbrado: {{ $factura->numero_timbrado }}<br>
                    Emisión: {{ ($factura->set_fecha_emision_de ?? $factura->fecha_emision)->format('d/m/Y H:i') }}<br>
                    @if($factura->fecha_vencimiento)
                        Vencimiento: {{ $factura->fecha_vencimiento->format('d/m/Y') }}<br>
                    @endif
                    Moneda: {{ $factura->moneda }}
                </p>
            </td>
        </tr>
    </table>

    <div class="bloque">
        <h2>Cliente</h2>
        <p style="margin: 0;">
            <strong>{{ $factura->cliente->nombre }} {{ $factura->cliente->apellido }}</strong><br>
            {{ $factura->cliente->cedula }}
            @if($factura->cliente->direccion)<br>{{ $factura->cliente->direccion }}@endif
        </p>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="text-align:left;">Descripción</th>
                <th style="width: 60px; text-align:right;">Cant.</th>
                <th style="width: 90px; text-align:right;">P. unit.</th>
                <th style="width: 90px; text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($factura->detalles as $d)
                <tr>
                    <td>{{ $d->descripcion }}</td>
                    <td style="text-align:right;">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($d->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <div>Subtotal: {{ number_format($factura->subtotal, 0, ',', '.') }} {{ $factura->moneda }}</div>
        <div>IVA: {{ number_format($factura->total_impuestos, 0, ',', '.') }} {{ $factura->moneda }}</div>
        <div class="total">TOTAL: {{ number_format($factura->total, 0, ',', '.') }} {{ $factura->moneda }}</div>
    </div>

    <div class="cdc-box">
        <strong>Código de Control (CDC)</strong>
        {{ $cdcFormateado ?? $factura->set_cdc }}
    </div>

    @if(!empty($qrImageUrl))
        <div class="qr-box">
            <img src="{{ $qrImageUrl }}" alt="QR SIFEN">
            <div class="meta">Consulte este documento en e-Kuatia</div>
        </div>
    @endif

    @if($factura->observaciones)
        <div class="bloque">
            <h2>Observaciones</h2>
            <p style="margin:0;">{{ $factura->observaciones }}</p>
        </div>
    @endif

    <div class="footer">
        Documento electrónico — SIFEN v150 · {{ config('sifen.ambiente') === 'production' ? 'Producción' : 'Ambiente de pruebas' }}
    </div>
</body>
</html>
