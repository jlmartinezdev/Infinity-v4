@extends('layouts.app')

@section('title', 'Dashboard de Facturacion')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard de Facturacion</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Facturado, cobrado y pendiente del ciclo mensual: tabla `cobros_resumen` (facturas del mes anterior; las emitidas desde el día 20 cuentan en el mes siguiente).
            Por día: ingreso bruto por fecha de pago del mes actual.
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Totales mensuales</h2>
        <div class="w-full" style="height: 350px;">
            <canvas id="facturacionDashboardChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Monto real cobrado</h2>
            <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                <button id="btn-cobro-real-dia" type="button" class="px-3 py-1.5 text-sm font-medium bg-emerald-600 text-white">Por día</button>
                <button id="btn-cobro-real-mes" type="button" class="px-3 py-1.5 text-sm font-medium bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200">Por mes</button>
            </div>
        </div>
        <div class="w-full" style="height: 320px;">
            <canvas id="facturacionCobroRealChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cobro atrasado y saldo a favor (por mes)</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">El saldo a favor corresponde a cobros adelantados sin factura o con exceso sobre lo aplicado.</p>
            </div>
            <a href="{{ route('facturacion.cobros-saldo-favor') }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-purple-600 text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                Ver cobros saldo a favor
            </a>
        </div>
        <div class="w-full" style="height: 320px;">
            <canvas id="facturacionAtrasadoFavorChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Debug resumen por mes</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Valores de `cobros_resumen` usados en los gráficos mensuales.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Mes</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Facturado</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Cobrado</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Pendiente</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse(($series ?? []) as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row['mes'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-blue-700 dark:text-blue-400 font-medium">{{ number_format((float)($row['total_facturado'] ?? 0), 0, ',', '.') }} PYG</td>
                            <td class="px-3 py-2 text-right text-green-700 dark:text-green-400 font-medium">{{ number_format((float)($row['total_cobrado'] ?? 0), 0, ',', '.') }} PYG</td>
                            <td class="px-3 py-2 text-right text-amber-700 dark:text-amber-400 font-medium">{{ number_format((float)($row['total_pendiente'] ?? 0), 0, ',', '.') }} PYG</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">Sin datos.</td>
                        </tr>
                    @endforelse
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
        const el = document.getElementById('facturacionDashboardChart');
        if (!el) return;

        const data = @json($series);
        const dataCobroRealDia = @json($seriesCobroRealDia);
        const dataCobroRealMes = @json($seriesCobroRealMes);
        const dataAtrasadoFavor = @json($seriesAtrasadoFavor);
        const labels = data.map(item => item.mes);
        const totalFacturado = data.map(item => Number(item.total_facturado || 0));
        const totalCobrado = data.map(item => Number(item.total_cobrado || 0));
        const totalPendiente = data.map(item => Number(item.total_pendiente || 0));
        const labelsCobroRealDia = dataCobroRealDia.map(item => item.dia);
        const valuesCobroRealDia = dataCobroRealDia.map(item => Number(item.total_cobrado_real || 0));
        const labelsCobroRealMes = dataCobroRealMes.map(item => item.mes);
        const valuesCobroRealMes = dataCobroRealMes.map(item => Number(item.total_cobrado_real || 0));
        const labelsAtrasadoFavor = dataAtrasadoFavor.map(item => item.mes);
        const valuesCobroAtrasado = dataAtrasadoFavor.map(item => Number(item.cobro_atrasado || 0));
        const valuesSaldoFavor = dataAtrasadoFavor.map(item => Number(item.saldo_favor || 0));

        const formatPYG = (value) => {
            const n = Number(value || 0);
            return n.toLocaleString('es-PY', { maximumFractionDigits: 0 }) + ' PYG';
        };

        const T = window.InfinityTheme || {};
        const chartScales = (yCb) => T.chartAxisTheme ? T.chartAxisTheme(yCb) : { y: { beginAtZero: true, ticks: { callback: yCb } } };
        const chartLegend = (pos) => T.chartLegendTheme ? T.chartLegendTheme(pos) : { position: pos || 'top' };
        const charts = [];

        const mainChart = new Chart(el, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Total facturado',
                        data: totalFacturado,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total cobrado',
                        data: totalCobrado,
                        backgroundColor: 'rgba(22, 163, 74, 0.75)',
                        borderColor: 'rgba(22, 163, 74, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total pendiente',
                        data: totalPendiente,
                        backgroundColor: 'rgba(245, 158, 11, 0.75)',
                        borderColor: 'rgba(245, 158, 11, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: chartLegend('top'),
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.dataset.label || '';
                                return label + ': ' + formatPYG(context.parsed.y);
                            }
                        }
                    }
                },
                scales: chartScales(function (value) {
                    return formatPYG(value);
                })
            }
        });
        charts.push(mainChart);

        const elCobroReal = document.getElementById('facturacionCobroRealChart');
        if (!elCobroReal) return;
        const btnDia = document.getElementById('btn-cobro-real-dia');
        const btnMes = document.getElementById('btn-cobro-real-mes');

        const cobroRealChart = new Chart(elCobroReal, {
            type: 'line',
            data: {
                labels: labelsCobroRealDia,
                datasets: [
                    {
                        label: 'Monto real cobrado',
                        data: valuesCobroRealDia,
                        borderColor: 'rgba(5, 150, 105, 1)',
                        backgroundColor: 'rgba(5, 150, 105, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: chartLegend('top'),
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.dataset.label || '';
                                return label + ': ' + formatPYG(context.parsed.y);
                            }
                        }
                    }
                },
                scales: chartScales(function (value) {
                    return formatPYG(value);
                })
            }
        });
        charts.push(cobroRealChart);

        function setMode(mode) {
            const isDia = mode === 'dia';
            cobroRealChart.data.labels = isDia ? labelsCobroRealDia : labelsCobroRealMes;
            cobroRealChart.data.datasets[0].data = isDia ? valuesCobroRealDia : valuesCobroRealMes;
            cobroRealChart.update();

            if (btnDia && btnMes) {
                btnDia.className = isDia
                    ? 'px-3 py-1.5 text-sm font-medium bg-emerald-600 text-white'
                    : 'px-3 py-1.5 text-sm font-medium bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200';
                btnMes.className = !isDia
                    ? 'px-3 py-1.5 text-sm font-medium bg-emerald-600 text-white'
                    : 'px-3 py-1.5 text-sm font-medium bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200';
            }
        }

        btnDia?.addEventListener('click', () => setMode('dia'));
        btnMes?.addEventListener('click', () => setMode('mes'));

        const elAtrasadoFavor = document.getElementById('facturacionAtrasadoFavorChart');
        if (!elAtrasadoFavor) return;

        const atrasadoFavorChart = new Chart(elAtrasadoFavor, {
            type: 'bar',
            data: {
                labels: labelsAtrasadoFavor,
                datasets: [
                    {
                        label: 'Cobro atrasado',
                        data: valuesCobroAtrasado,
                        backgroundColor: 'rgba(59, 130, 246, 0.75)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Saldo a favor',
                        data: valuesSaldoFavor,
                        backgroundColor: 'rgba(168, 85, 247, 0.75)',
                        borderColor: 'rgba(168, 85, 247, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: chartLegend('top'),
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.dataset.label || '';
                                return label + ': ' + formatPYG(context.parsed.y);
                            }
                        }
                    }
                },
                scales: chartScales(function (value) {
                    return formatPYG(value);
                })
            }
        });
        charts.push(atrasadoFavorChart);

        if (T.watchThemeCharts) {
            T.watchThemeCharts(charts, [
                function (value) { return formatPYG(value); },
                function (value) { return formatPYG(value); },
                function (value) { return formatPYG(value); },
            ]);
        }
    })();
</script>
@endpush
