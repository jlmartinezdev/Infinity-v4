<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen de cobros</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #e5e7eb; font-weight: bold; }
        .text-right { text-align: right; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0 0 5px 0; }
        .header p { font-size: 10px; color: #666; margin: 0; }
        .total { font-weight: bold; font-size: 16px; margin-top: 15px; }
        .filtros { font-size: 10px; color: #666; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name') }}</h1>
        <p>Resumen de cobros</p>
        <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
        @if($desde || $hasta)
        <p class="filtros">Período: {{ $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : '—' }} al {{ $hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : '—' }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Recibo</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Factura</th>
                <th>Forma pago</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cobros as $cobro)
            <tr>
                <td>{{ $cobro->numero_recibo }}</td>
                <td>{{ $cobro->fecha_pago->format('d/m/Y H:i') }}</td>
                <td>{{ $cobro->cliente->nombre }} {{ $cobro->cliente->apellido }}</td>
                <td>{{ $cobro->facturaInternas->isNotEmpty() ? 'Interna #' . $cobro->facturaInternas->pluck('id')->implode(', #') : '—' }}</td>
                <td>{{ \App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago }}</td>
                <td class="text-right">{{ number_format($cobro->monto, 0, ',', '.') }} PYG</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total text-right">
        Total: {{ number_format($total, 0, ',', '.') }} PYG ({{ $cobros->count() }} cobros)
    </div>
</body>
</html>
