@extends('layouts.app')

@section('title', 'Dashboard de Facturacion')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard de Facturacion</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Facturado: facturas creadas en el mes anterior al indicado en cada columna.
            Cobrado: igual que en Cobros y recibos — facturas de ese mismo mes anterior y cobros con fecha de pago desde el día 20 del mes anterior hasta el fin del mes de la columna.
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Totales mensuales</h2>
        <div class="w-full" style="height: 350px;">
            <canvas id="facturacionDashboardChart"></canvas>
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
        const labels = data.map(item => item.mes);
        const totalFacturado = data.map(item => Number(item.total_facturado || 0));
        const totalCobrado = data.map(item => Number(item.total_cobrado || 0));
        const totalPendiente = data.map(item => Number(item.total_pendiente || 0));

        const formatPYG = (value) => {
            const n = Number(value || 0);
            return n.toLocaleString('es-PY', { maximumFractionDigits: 0 }) + ' PYG';
        };

        new Chart(el, {
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
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.dataset.label || '';
                                return label + ': ' + formatPYG(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return formatPYG(value);
                            }
                        }
                    }
                }
            }
        });
    })();
</script>
@endpush
