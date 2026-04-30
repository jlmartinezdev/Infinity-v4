@extends('layouts.app')

@section('title', 'Dashboard de Clientes')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard de Clientes</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Cantidad de instalaciones registradas por mes (últimos 12 meses).
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Instalaciones por mes</h2>
        <div class="w-full" style="height: 360px;">
            <canvas id="clientesDashboardChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Instalaciones por año</h2>
        <div class="w-full" style="height: 360px;">
            <canvas id="clientesDashboardYearChart"></canvas>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const el = document.getElementById('clientesDashboardChart');
        const yearEl = document.getElementById('clientesDashboardYearChart');
        if (!el && !yearEl) return;

        const data = @json($series);
        const yearData = @json($seriesAnual);
        const labels = data.map(item => item.mes);
        const instalaciones = data.map(item => Number(item.cantidad_instalaciones || 0));
        const yearLabels = yearData.map(item => item.anio);
        const yearInstalaciones = yearData.map(item => Number(item.cantidad_instalaciones || 0));
        const yearColors = [
            'rgba(37, 99, 235, 0.85)',
            'rgba(22, 163, 74, 0.85)',
            'rgba(245, 158, 11, 0.85)',
            'rgba(220, 38, 38, 0.85)',
            'rgba(168, 85, 247, 0.85)',
            'rgba(14, 165, 233, 0.85)',
            'rgba(236, 72, 153, 0.85)',
            'rgba(15, 118, 110, 0.85)',
            'rgba(120, 113, 108, 0.85)',
            'rgba(79, 70, 229, 0.85)'
        ];

        if (el) {
            new Chart(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Instalaciones',
                            data: instalaciones,
                            backgroundColor: 'rgba(37, 99, 235, 0.75)',
                            borderColor: 'rgba(37, 99, 235, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        if (yearEl) {
            new Chart(yearEl, {
                type: 'doughnut',
                data: {
                    labels: yearLabels,
                    datasets: [
                        {
                            label: 'Instalaciones por año',
                            data: yearInstalaciones,
                            backgroundColor: yearLabels.map((_, index) => yearColors[index % yearColors.length]),
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
    })();
</script>
@endpush
