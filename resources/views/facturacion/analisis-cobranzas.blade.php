@extends('layouts.app')

@section('title', 'Análisis de cobranzas')

@section('content')
@php
    $fmt = fn ($valor) => number_format((float) $valor, 0, ',', '.');
    $pct = fn ($valor) => number_format((float) $valor, 1, ',', '.').'%';
    $totales = $informe['totales'];
@endphp
<div class="max-w-7xl mx-auto">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Análisis de cobranzas</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 max-w-3xl">
                Informe de solo lectura desde julio de 2026. Las facturas normales pertenecen al mes siguiente de su creación.
                Las especiales se anclan al mes del primer pago. El cobrado es lo aplicado en cada factura, no el recibo entero.
            </p>
        </div>
        <form method="get" action="{{ route('facturacion.analisis-cobranzas') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="desde" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Desde</label>
                <input type="month" name="desde" id="desde" value="{{ $desde }}" min="{{ $minimo }}" max="{{ $maximo }}"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-2">
            </div>
            <div>
                <label for="hasta" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hasta</label>
                <input type="month" name="hasta" id="hasta" value="{{ $hasta }}" min="{{ $minimo }}" max="{{ $maximo }}"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-2">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">Ver</button>
            <a href="{{ route('facturacion.analisis-cobranzas.excel', ['desde' => $desde, 'hasta' => $hasta]) }}"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                Excel
            </a>
        </form>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Facturado</p>
            <p class="text-lg font-semibold text-blue-700 dark:text-blue-400 mt-1">{{ $fmt($totales['facturado']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Notas de crédito</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $fmt($totales['notas']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Cobrado aplicado</p>
            <p class="text-lg font-semibold text-green-700 dark:text-green-400 mt-1">{{ $fmt($totales['aplicado']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Pendiente</p>
            <p class="text-lg font-semibold text-amber-700 dark:text-amber-400 mt-1">{{ $fmt($totales['pendiente']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Saldo a favor</p>
            <p class="text-lg font-semibold text-purple-700 dark:text-purple-400 mt-1">{{ $fmt($totales['saldo_favor']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Cobranza</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $pct($totales['porcentaje']) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Facturación y cobranza</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Normales del ciclo más especiales ancladas a ese mes. El cobrado es el monto aplicado.</p>
        <div class="w-full" style="height: 340px;">
            <canvas id="analisisFacturadoCobrado"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Deuda pendiente</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Saldo real de las facturas del mes: total menos lo aplicado, con tope en el total, menos las notas de crédito.</p>
        <div class="w-full" style="height: 280px;">
            <canvas id="analisisPendiente"></canvas>
        </div>
    </div>

    @include('facturacion.partials.analisis-tabla', [
        'titulo' => 'Facturas normales por ciclo',
        'ayuda' => 'El pago se asigna al ciclo de la factura, aunque el recibo sea de otro mes. El porcentaje es cobrado aplicado sobre facturado menos notas.',
        'filas' => $informe['normales'],
        'modo' => 'normal',
    ])

    @include('facturacion.partials.analisis-tabla', [
        'titulo' => 'Facturas especiales',
        'ayuda' => 'No entran al ciclo de Internet. El facturado, las notas y el pendiente se anclan al mes del primer pago, o al mes de creación si todavía no se cobró. El cobrado del mes es lo aplicado con fecha de pago en ese mes.',
        'filas' => $informe['especiales'],
        'modo' => 'especial',
    ])

    @include('facturacion.partials.analisis-tabla', [
        'titulo' => 'Consolidado',
        'ayuda' => 'Suma normales y especiales sin repetir una factura. El saldo a favor es la parte del recibo que no quedó aplicada a ninguna factura, por mes calendario del pago.',
        'filas' => $informe['consolidado'],
        'modo' => 'consolidado',
        'totales' => $totales,
    ])

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Conciliación</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Compara el resumen guardado, el cálculo vivo del ciclo (el que usa el dashboard) y este análisis. No corrige ningún dato.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Ciclo</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Guardado facturado</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Vivo facturado</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Análisis neto</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Guardado cobrado</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Recibo entero</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Aplicado</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Guardado pendiente</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Vivo pendiente</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Saldo real</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($informe['conciliacion'] as $fila)
                        <tr class="align-top hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $fila['etiqueta'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['guardado_facturado']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['vivo_facturado']) }}</td>
                            <td class="px-3 py-2 text-right text-blue-700 dark:text-blue-400">{{ $fmt($fila['analisis_facturado']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['guardado_cobrado']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['vivo_cobrado']) }}</td>
                            <td class="px-3 py-2 text-right text-green-700 dark:text-green-400">{{ $fmt($fila['analisis_aplicado']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['guardado_pendiente']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['vivo_pendiente']) }}</td>
                            <td class="px-3 py-2 text-right text-amber-700 dark:text-amber-400">{{ $fmt($fila['analisis_pendiente']) }}</td>
                        </tr>
                        <tr class="bg-gray-50/70 dark:bg-gray-900/30">
                            <td colspan="10" class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                                {{ implode(' ', $fila['explicacion']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const filas = @json($informe['consolidado']);
        const labels = filas.map(item => item.etiqueta);
        const formatPYG = (value) => Number(value || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 }) + ' PYG';
        const T = window.InfinityTheme || {};
        const chartScales = (yCb) => T.chartAxisTheme ? T.chartAxisTheme(yCb) : { y: { beginAtZero: true, ticks: { callback: yCb } } };
        const chartLegend = (pos) => T.chartLegendTheme ? T.chartLegendTheme(pos) : { position: pos || 'top' };

        const facturado = document.getElementById('analisisFacturadoCobrado');
        if (facturado) {
            new Chart(facturado, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Facturado', data: filas.map(item => Number(item.facturado || 0)), backgroundColor: 'rgba(37, 99, 235, 0.75)', borderColor: 'rgba(37, 99, 235, 1)', borderWidth: 1 },
                        { label: 'Cobrado aplicado', data: filas.map(item => Number(item.aplicado || 0)), backgroundColor: 'rgba(22, 163, 74, 0.75)', borderColor: 'rgba(22, 163, 74, 1)', borderWidth: 1 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: chartLegend('top'), tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + formatPYG(ctx.parsed.y) } } },
                    scales: chartScales((value) => formatPYG(value))
                }
            });
        }

        const pendiente = document.getElementById('analisisPendiente');
        if (pendiente) {
            new Chart(pendiente, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Pendiente',
                        data: filas.map(item => Number(item.pendiente || 0)),
                        borderColor: 'rgba(245, 158, 11, 1)',
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        fill: true,
                        tension: 0.2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: chartLegend('top'), tooltip: { callbacks: { label: (ctx) => formatPYG(ctx.parsed.y) } } },
                    scales: chartScales((value) => formatPYG(value))
                }
            });
        }
    })();
</script>
@endpush
