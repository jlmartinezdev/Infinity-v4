@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
    $qty = fn ($v) => $v === null || $v === '' ? '—' : (is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') : $v);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Liquidación de sueldo</title>
    <style>
        @page { margin: 12mm 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
            line-height: 1.35;
        }
        h1 {
            font-size: 13px;
            text-align: center;
            margin: 0 0 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .meta { margin-bottom: 8px; }
        .meta table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .lbl { width: 22%; color: #333; }
        .section {
            font-weight: bold;
            font-size: 10px;
            margin: 10px 0 4px;
            padding: 3px 0;
            text-transform: uppercase;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        table.grid th, table.grid td {
            border: 1px solid #555;
            padding: 3px 5px;
        }
        table.grid th {
            background: #eee;
            font-weight: bold;
            text-align: left;
        }
        table.grid .num { text-align: right; white-space: nowrap; }
        table.grid .qty { text-align: center; width: 18%; }
        table.grid .tot td { font-weight: bold; background: #f5f5f5; }
        .neto {
            margin: 10px 0 8px;
            font-size: 12px;
            font-weight: bold;
            border: 1.5px solid #111;
            padding: 6px 8px;
            text-align: center;
        }
        .pago { margin: 6px 0 10px; }
        .obs { margin: 8px 0 14px; font-size: 9px; color: #333; }
        .firmas {
            width: 100%;
            margin-top: 28px;
            border-collapse: collapse;
        }
        .firmas td {
            width: 50%;
            text-align: center;
            padding: 0 12px;
            vertical-align: top;
        }
        .firmas .line {
            border-top: 1px solid #111;
            margin-top: 36px;
            padding-top: 4px;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <h1>Hoja de liquidación y recibo de haberes</h1>

    <div class="meta">
        <table>
            <tr>
                <td class="lbl">Ciudad:</td>
                <td>{{ $ciudad }}</td>
                <td class="lbl">Fecha:</td>
                <td>{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="lbl">Periodo:</td>
                <td colspan="3">{{ $periodo }}</td>
            </tr>
            <tr>
                <td class="lbl">Empleador:</td>
                <td colspan="3">{{ $empleador_nombre }} – C.I. {{ $empleador_ci }}</td>
            </tr>
            <tr>
                <td class="lbl">Trabajador/a:</td>
                <td colspan="3">{{ $trabajador }} – C.I. {{ $trabajador_ci ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Cargo:</td>
                <td colspan="3">{{ $cargo ?: '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">Haberes</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="qty">Cantidad</th>
                <th class="num">Monto (Gs.)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Salario básico (SML)</td>
                <td class="qty">—</td>
                <td class="num">{{ $fmt($salario) }}</td>
            </tr>
            <tr>
                <td>Jornadas trabajadas (N°)</td>
                <td class="qty">{{ $qty($jornadas) }}</td>
                <td class="num">—</td>
            </tr>
            <tr>
                <td>Horas extras diurnas (N°)</td>
                <td class="qty">{{ $qty($horas_extras_diurnas) }}</td>
                <td class="num">{{ $monto_extras_diurnas ? $fmt($monto_extras_diurnas) : '—' }}</td>
            </tr>
            <tr>
                <td>Horas extras nocturnas (N°)</td>
                <td class="qty">{{ $qty($horas_extras_nocturnas) }}</td>
                <td class="num">{{ $monto_extras_nocturnas ? $fmt($monto_extras_nocturnas) : '—' }}</td>
            </tr>
            <tr>
                <td>Feriados trabajados (N° horas)</td>
                <td class="qty">{{ $qty($horas_feriados) }}</td>
                <td class="num">{{ $monto_feriados ? $fmt($monto_feriados) : '—' }}</td>
            </tr>
            <tr>
                <td>Bonificaciones / comisiones / otros</td>
                <td class="qty">—</td>
                <td class="num">{{ $bonificaciones ? $fmt($bonificaciones) : '—' }}</td>
            </tr>
            <tr class="tot">
                <td colspan="2">TOTAL HABERES</td>
                <td class="num">{{ $fmt($total_haberes) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section">Descuentos</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="num">Monto (Gs.)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Aporte IPS (trabajador)</td>
                <td class="num">{{ $fmt($ips) }}</td>
            </tr>
            <tr>
                <td>Anticipo de salario</td>
                <td class="num">{{ $anticipo ? $fmt($anticipo) : '—' }}</td>
            </tr>
            <tr>
                <td>Otros descuentos autorizados</td>
                <td class="num">{{ $otros_descuentos ? $fmt($otros_descuentos) : '—' }}</td>
            </tr>
            <tr class="tot">
                <td>TOTAL DESCUENTOS</td>
                <td class="num">{{ $fmt($total_descuentos) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="neto">NETO A COBRAR: Gs. {{ $fmt($neto) }}</div>

    <div class="pago">
        <strong>Forma de pago:</strong> {{ $forma_pago }}
        @if($forma_pago === 'Transferencia bancaria')
            <br>
            <strong>Banco destino:</strong> {{ $banco ?: '—' }}
            &nbsp;|&nbsp;
            <strong>Cuenta destino:</strong> {{ $cuenta_bancaria ?: '—' }}
        @endif
    </div>

    <div class="obs">Observación: {{ $observacion }}</div>

    <table class="firmas">
        <tr>
            <td>
                <div class="line">Firma Empleador</div>
            </td>
            <td>
                <div class="line">Firma Trabajador/a</div>
            </td>
        </tr>
    </table>
</body>
</html>
