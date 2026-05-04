<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 0; padding: 14px 16px 20px; }
        .doc-header { width: 100%; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #d1d5db; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { max-height: 40px; max-width: 160px; }
        .empresa { font-size: 11px; font-weight: bold; margin: 0 0 2px 0; }
        .meta-emp { color: #4b5563; font-size: 9px; line-height: 1.35; }
        .titulo-doc { text-align: right; }
        .titulo-doc h1 { font-size: 14px; margin: 0 0 2px 0; letter-spacing: 0.04em; }
        .titulo-doc .sub { font-size: 11px; color: #6b7280; margin: 0; }
        .bloque-cliente { margin-bottom: 10px; font-size: 9px; }
        .bloque-cliente h2 { font-size: 10px; text-transform: uppercase; color: #6b7280; margin: 0 0 3px 0; }
        .invoice-compact { margin-top: 10px; padding-top: 6px; border-top: 1px solid #e5e7eb; }
        .invoice-compact-head { font-size: 10px; font-weight: bold; color: #374151; margin-bottom: 4px; }
        .invoice-compact-head span { font-weight: normal; color: #6b7280; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.lines th, table.lines td { border: 1px solid #e5e7eb; padding: 2px 4px; }
        table.lines th { background: #f9fafb; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.lines td { font-size: 10px; }
        .right { text-align: right; }
        .totals { width: 220px; margin-left: auto; margin-top: 4px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 1px 0; font-size: 10px; }
        .totals .label { color: #4b5563; }
        .totals .grand { font-size: 11px; font-weight: bold; border-top: 1px solid #e5e7eb; padding-top: 3px; }
        .cobros { margin-top: 3px; font-size: 10px; color: #4b5563; }
        .cobros ul { margin: 2px 0 0 12px; padding: 0; }
        .obs-mini { margin-top: 3px; font-size: 10px; color: #4b5563; white-space: pre-wrap; }
        .doc-footer { margin-top: 12px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @php $first = $facturas->first(); @endphp

    <div class="doc-header">
        <table class="header-table">
            <tr>
                <td style="width: 58%;">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                    @endif
                    @if($ajustes)
                        <!--p class="empresa">{{ $ajustes->nombre_empresa ?? 'Empresa' }}</p -->
                        <div class="meta-emp">
                            @if($ajustes->direccion){{ $ajustes->direccion }}<br>@endif
                            @if($ajustes->telefono)Tel. {{ $ajustes->telefono }}@endif
                            @if($ajustes->email) · {{ $ajustes->email }}@endif
                        </div>
                    @endif
                </td>
                <td class="titulo-doc" style="width: 42%;">
                    <h1>FACTURAS PENDIENTES</h1>
                    <p class="sub">{{ $facturas->count() }} documento(s) en una sola hoja</p>
                </td>
            </tr>
        </table>
    </div>

    @if($first && $first->cliente)
        <div class="bloque-cliente">
            <h2>Cliente</h2>
            <p style="margin: 0;">
                <strong>{{ $first->cliente->nombre }} {{ $first->cliente->apellido }}</strong>
                · {{ $first->cliente->cedula }}
            </p>
        </div>
    @endif

    @foreach ($facturas as $factura_interna)
        <div class="invoice-compact">
            <div class="invoice-compact-head">
                Factura N.º {{ $factura_interna->id }}
                <span>· {{ $factura_interna->estado }}</span>
                <span>· Emisión {{ $factura_interna->fecha_emision->format('d/m/Y') }}</span>
                @if($factura_interna->fecha_vencimiento)
                    <span>· Venc. {{ $factura_interna->fecha_vencimiento->format('d/m/Y') }}</span>
                @endif
                <span>· Período {{ $factura_interna->periodo_desde->format('d/m/Y') }} – {{ $factura_interna->periodo_hasta->format('d/m/Y') }}</span>
            </div>

            <table class="lines">
                <thead>
                    <tr>
                        <th style="text-align: left;">Descripción</th>
                        <th class="right" style="width: 11%;">Cant.</th>
                        <th class="right" style="width: 13%;">P. unit.</th>
                        <th class="right" style="width: 13%;">Total</th>
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
                    <strong>Cobros:</strong>
                    <ul>
                        @foreach($factura_interna->cobros as $cobro)
                            <li>{{ $cobro->numero_recibo }} — {{ $cobro->fecha_pago->format('d/m/Y') }} — {{ number_format($cobro->pivot->monto ?? $cobro->monto, 0, ',', '.') }} {{ $factura_interna->moneda }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($factura_interna->observaciones)
                <div class="obs-mini"><strong>Obs.</strong> {{ $factura_interna->observaciones }}</div>
            @endif
        </div>
    @endforeach

    @isset($resumenTotales)
        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #374151;">
            <h2 style="font-size: 11px; text-transform: uppercase; color: #374151; margin: 0 0 4px 0;">Resumen consolidado</h2>
            <table style="width: 260px; margin-left: auto; border-collapse: collapse; font-size: 10px;">
                <tr>
                    <td style="color: #4b5563; padding: 1px 0;">Total facturado</td>
                    <td style="text-align: right; padding: 1px 0;">{{ number_format($resumenTotales['sum_total_facturado'], 0, ',', '.') }} {{ $resumenTotales['moneda'] }}</td>
                </tr>
                <tr>
                    <td style="color: #4b5563; padding: 1px 0;">Total cobrado</td>
                    <td style="text-align: right; padding: 1px 0;">{{ number_format($resumenTotales['sum_monto_cobrado'], 0, ',', '.') }} {{ $resumenTotales['moneda'] }}</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; padding: 4px 0 1px 0; border-top: 1px solid #e5e7eb;">Saldo total pendiente</td>
                    <td style="text-align: right; font-weight: bold; padding: 4px 0 1px 0; border-top: 1px solid #e5e7eb;">{{ number_format($resumenTotales['sum_saldo_pendiente'], 0, ',', '.') }} {{ $resumenTotales['moneda'] }}</td>
                </tr>
            </table>
        </div>
    @endisset

    <div class="doc-footer">
        Documento interno · generado {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
