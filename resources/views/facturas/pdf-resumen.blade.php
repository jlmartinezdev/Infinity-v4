<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Facturas electrónicas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 5px 6px; text-align: left; }
        th { background: #e5e7eb; font-weight: bold; }
        .text-right { text-align: right; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { font-size: 16px; margin: 0 0 4px 0; }
        .header p { font-size: 10px; color: #555; margin: 0; }
        .total { font-weight: bold; font-size: 13px; margin-top: 12px; }
        .filtros { font-size: 10px; color: #555; margin-top: 4px; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name') }}</h1>
        <p>Facturas electrónicas</p>
        <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
        @if(! empty($filtros))
            <p class="filtros">Filtros: {{ implode(' · ', $filtros) }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Número</th>
                <th>Emisión</th>
                <th>Aprobación</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($facturas as $f)
                <tr>
                    <td>{{ $f->id }}</td>
                    <td>{{ $f->receptorNombreCompleto() ?: '—' }}@if($f->esOcasional()) <span class="muted">(ocasional)</span>@endif</td>
                    <td>{{ $f->numero_completo ?? '—' }}</td>
                    <td>{{ $f->fecha_emision?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $f->set_fecha_autorizacion?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ \App\Models\Factura::tiposDocumento()[$f->tipo_documento] ?? $f->tipo_documento }}</td>
                    <td>{{ \App\Models\Factura::estados()[$f->estado] ?? $f->estado }}</td>
                    <td class="text-right">{{ number_format((float) $f->total, 0, ',', '.') }} {{ $f->moneda }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">No hay facturas con el filtro seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total text-right">
        Total: {{ number_format((float) $total, 0, ',', '.') }} PYG ({{ number_format((int) ($totalRegistrosFiltrados ?? $facturas->count()), 0, ',', '.') }} facturas)
    </div>
    @if(isset($totalRegistrosFiltrados) && $totalRegistrosFiltrados > 1000)
        <p class="filtros">Nota: la tabla muestra como máximo 1.000 filas; el total incluye todas las facturas que cumplen el filtro.</p>
    @endif
</body>
</html>
