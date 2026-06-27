<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>KuDE {{ $factura->numero_completo ?? $factura->id }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            line-height: 1.25;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .border { border: 1px solid #000; }
        .border td, .border th { border: 1px solid #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; }
        .logo { max-height: 82px; max-width: 170px; }
        .titulo-kude { font-size: 11px; font-weight: bold; margin: 0 0 4px; }
        .razon-social { font-size: 9px; font-weight: bold; margin: 0 0 2px; }
        .actividad { font-size: 9px; margin: 0 0 4px; }
        .meta { font-size: 9px; }
        .caja-doc { border: 1px solid #000; padding: 6px; font-size: 11px; }
        .caja-doc .doc-tipo { font-size: 9px; font-weight: bold;  margin: 4px 0; }
        .caja-doc .doc-num { font-size: 11px; font-weight: bold; }
        .datos-grid td { padding: 3px 5px; font-size: 9px; border: 1px solid #000; }
        .datos-grid .lbl { font-weight: bold; white-space: nowrap; }
        .items th {
            background: #e8e8e8;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            padding: 3px 2px;
            border: 1px solid #000;
        }
        .items td {
            font-size: 9px;
            padding: 3px 2px;
            border: 1px solid #000;
            height: 14px;
        }
        .items .col-desc { text-align: left; }
        .items .col-num { text-align: right; }
        .items .col-cod { text-align: center; width: 9%; }
        .items .col-uni { text-align: center; width: 5%; }
        .items .col-cant { width: 6%; }
        .items .col-pu { width: 9%; }
        .items .col-descu { width: 6%; }
        .items .col-ant { width: 6%; }
        .items .col-iva { width: 8%; }
        .subtotal-row td { font-weight: bold; font-size: 9px; padding: 4px 3px; border: 1px solid #000; }
        .total-op { font-size: 11px; font-weight: bold; text-align: right; padding: 5px 8px; border: 1px solid #000; border-top: none; }
        .liq-iva { font-size: 9px; padding: 5px 8px; border: 1px solid #000; border-top: none; }
        .liq-iva span { margin-right: 18px; }
        .footer-kude { margin-top: 8px; }
        .footer-kude td { vertical-align: middle; }
        .qr img { width: 88px; height: 88px; }
        .cdc-block {  padding: 0 6px; }
        .cdc-label { font-size: 9px; font-weight: bold; margin-bottom: 3px; }
        .cdc-valor { font-size: 12px; font-weight: bold; letter-spacing: 1px; line-height: 1.35; }
        .legal { font-size: 8px; margin-top: 6px; line-height: 1.35; }
        .legal-center { font-weight: bold; margin: 4px 0; font-size: 9px; }
        .firma-table td { border: 1px solid #000; height: 28px; font-size: 9px; text-align: center; padding-top: 16px; }
        .firma-table .lbl-firma { font-size: 8px; padding-top: 2px; height: auto; border-top: none; }
        .barcode img { max-width: 130px; height: 36px; }
        .pagina { font-size: 9px; text-align: right; margin-top: 4px; }
        .info-adicional { font-size: 9px; padding: 4px 5px; min-height: 18px; }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <table>

        <tr class="caja-doc">
            <td style="width: 40%; padding: 6px;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                @endif
            </td>
            <td style="width: 30%; padding: 0 8px;">
                <p class="titulo-kude">{{ $factura->tituloKude() }}</p>
                <p class="razon-social upper">{{ $config->razon_social }}</p>
                @if($config->descripcion_actividad_economica)
                    <p class="actividad upper">{{ $config->descripcion_actividad_economica }}</p>
                @endif
                <p class="meta">YAGUARETE CORA, YUTY, CAAZAPA, PY</p>
                <p class="meta">{{ $config->email }} {{ $config->telefono }}</p>
            </td>
            <td style="width: 30%;">
                <div>
                    <div><span class="bold">RUC:</span> {{ $config->ruc }}-{{ $config->dv_ruc }}</div>
                    <div><span class="bold">Timbrado Nº:</span> {{ $factura->numero_timbrado ?? $config->numero_timbrado }}</div>
                    <div><span class="bold">Inicio de vigencia:</span> {{ $vigenciaTimbradoFmt }}</div>
                    <div class="doc-tipo">{{ $factura->descripcionTipoDocumentoSifen() }}</div>
                    <div class="meta">Tipo DE (iTiDE): {{ $factura->codigoTipoDocumentoSifen() }}</div>
                    <div class="doc-num">Nº: {{ $factura->numero_completo ?? '—' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Datos emisión / cliente --}}
    <table  style="margin-top: 6px;">
        <tr>
            <td style="width: 50%;">
                <span class="lbl">Fecha de emisión:</span> {{ $fechaEmisionFmt }}<br>
                <span class="lbl">RUC/documento de identidad:</span> {{ $factura->receptorDocumentoEfectivo() }}<br>
                <span class="lbl">Nombre o razón social:</span> {{ $factura->receptorNombreCompleto() }}<br>
                <span class="lbl">Tipo de transacción:</span> {{ $factura->tipoTransaccionKude() }}<br>
                @foreach($factura->lineasComplementariasKude() as $linea)
                    <span class="lbl">{{ $linea['label'] }}:</span> {{ $linea['value'] }}<br>
                @endforeach
        
            </td>
            <td style="width: 50%;">
                @if($factura->esFacturaComercial())
                    <span class="lbl">Condición de venta:</span> {{ $condicionVenta }}<br>
                @endif
            
                <span class="lbl">Moneda:</span> {{ $monedaDescripcion }}<br>
                <span class="lbl">Dirección:</span> {{ $factura->receptorDireccionEfectiva() ?: '—' }}<br>
                @if($factura->receptorEmailEfectivo())
                    <span class="lbl">Correo electrónico:</span> {{ $factura->receptorEmailEfectivo() }}<br>
                @endif
                @if($factura->receptorTelefonoEfectivo())
                    <span class="lbl">Teléfono:</span> {{ $factura->receptorTelefonoEfectivo() }}
                @endif
            </td>
        </tr>
    </table>

    @if($factura->observaciones)
        <div class="info-adicional">
            <span class="bold">Información adicional:</span> {{ $factura->observaciones }}
        </div>
    @endif

    {{-- Detalle de ítems --}}
    <table class="items" style="margin-top: 6px;">
        <thead>
            <tr>
                <th rowspan="2" class="col-cod">Código</th>
                <th rowspan="2" style="width: 22%;">Descripción</th>
                <th rowspan="2" class="col-cant">Cantidad</th>
                <th rowspan="2" class="col-pu">Precio Unitario</th>
                <th colspan="3">Valor de Venta</th>
            </tr>
            <tr>
                <th class="col-iva">Exentas</th>
                <th class="col-iva">5%</th>
                <th class="col-iva">10%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineas as $linea)
                <tr>
                    <td class="col-cod text-center">{{ $linea['codigo'] }}</td>
                    <td class="col-desc">{{ $linea['descripcion'] }}</td>
                    <td class="col-cant col-num">{{ number_format($linea['cantidad'], 2, ',', '.') }}</td>
                    <td class="col-pu col-num">{{ number_format($linea['precio_unitario'], 0, ',', '.') }}</td>
                    <td class="col-iva col-num">{{ $linea['exentas'] > 0 ? number_format($linea['importe'], 0, ',', '.') : '' }}</td>
                    <td class="col-iva col-num">{{ $linea['grav5'] > 0 ? number_format($linea['importe'], 0, ',', '.') : '' }}</td>
                    <td class="col-iva col-num">{{ $linea['grav10'] > 0 ? number_format($linea['importe'], 0, ',', '.') : '' }}</td>
                </tr>
            @endforeach
        
            <tr class="subtotal-row">
                <td colspan="4" class="text-right bold">SUBTOTAL:</td>
                <td class="col-num">{{ $sumExentas > 0 ? number_format($sumExentas, 0, ',', '.') : '0' }}</td>
                <td class="col-num">{{ $sumGrav5 > 0 ? number_format($sumGrav5, 0, ',', '.') : '0' }}</td>
                <td class="col-num">{{ $sumGrav10 > 0 ? number_format($sumGrav10, 0, ',', '.') : '0' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-op">
        TOTAL DE LA OPERACIÓN: {{ number_format($factura->total, 0, ',', '.') }}
    </div>

    <div class="liq-iva">
        <span class="bold">LIQUIDACIÓN IVA:</span>
        <span>(5%) {{ number_format($iva5, 0, ',', '.') }}</span>
        <span>(10%) {{ number_format($iva10, 0, ',', '.') }}</span>
        <span class="bold">TOTAL IVA: {{ number_format($totalIva, 0, ',', '.') }}</span>
    </div>

    {{-- Pie KuDE --}}
    <table class="footer-kude">
        <tr>
            <td style="width: 25%;" class="qr text-center">
                @if(!empty($qrImageUrl))
                    <img src="{{ $qrImageUrl }}" alt="QR SIFEN">
                @endif
            </td>
            <td style="width: 75%;" class="cdc-block">
                <div class="cdc-label">Consulte la validez de esta Factura Electrónica con el número de CDC impreso abajo en:</div>
                <div class="meta">{{ $consultaUrl }}</div>
                <div class="cdc-valor">{{ $cdcFormateado ?? $factura->set_cdc }}</div>
                <div class="legal-center">ESTE DOCUMENTO ES UNA REPRESENTACIÓN GRÁFICA DE UN DOCUMENTO ELECTRÓNICO (XML)</div>
          
            </td>
            
        </tr>
    </table>

</body>
</html>
