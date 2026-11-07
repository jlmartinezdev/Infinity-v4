@extends('layouts.app')

@section('title', 'Finanzas')

@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); @endphp
<div class="max-w-7xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Finanzas</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        @if($corte)
            Los recibos entran al libro desde {{ $corte->format('d/m/Y') }}.
            @if($fueraDelLibro > 0)
                Hay {{ number_format($fueraDelLibro, 0, ',', '.') }} recibos de ese período que todavía no tienen cuenta asignada o no se contabilizaron.
            @endif
        @else
            La fecha de corte está vacía: los recibos siguen solo en cobranzas. Se define en Configuración, parámetros financieros.
        @endif
        <a class="underline" href="{{ route('finanzas.cobros-mes') }}">Cargar los cobros ya registrados del mes.</a>
    </p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach($saldos as $fila)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['cuenta']->nombre }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $fmt($fila['saldo']) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $fila['cuenta']->tipo === 'pasarela' ? 'Pendiente de liquidación' : 'Disponible' }}</p>
            </div>
        @endforeach
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Total consolidado</p>
            <p class="text-lg font-semibold text-emerald-700 dark:text-emerald-400 mt-1">{{ $fmt($consolidado) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach(['Ingresos del mes' => $ingresos, 'OPEX' => $opex, 'CAPEX' => $capex, 'Retiros' => $retiros, 'Resultado' => $resultado] as $titulo => $valor)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $titulo }}</p>
                <p class="text-lg font-semibold mt-1 {{ $titulo === 'Resultado' ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $fmt($valor) }}</p>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">Efectivo todavía no rendido por los cobradores: {{ $fmt($efectivoSinRendir) }} PYG. Ya está dentro del saldo de efectivo si el recibo entró al libro. La rendición no lo vuelve a sumar.</p>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Evolución mensual</h2>
        <div class="w-full" style="height: 320px;"><canvas id="finanzasEvolucion"></canvas></div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Saldo por cuenta</h2>
        <div class="w-full" style="height: 280px;"><canvas id="finanzasCuentas"></canvas></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const meses = @json($meses);
    const cuentas = @json($porCuenta);
    const pyg = (v) => Number(v || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 }) + ' PYG';
    const T = window.InfinityTheme || {};
    const scales = (cb) => T.chartAxisTheme ? T.chartAxisTheme(cb) : { y: { beginAtZero: true } };
    const legend = T.chartLegendTheme ? T.chartLegendTheme('top') : { position: 'top' };
    new Chart(document.getElementById('finanzasEvolucion'), {
        type: 'bar',
        data: {
            labels: meses.map(m => m.mes),
            datasets: [
                { label: 'Ingresos', data: meses.map(m => m.ingresos), backgroundColor: 'rgba(22, 163, 74, 0.75)' },
                { label: 'Egresos', data: meses.map(m => m.egresos), backgroundColor: 'rgba(220, 38, 38, 0.7)' },
                { label: 'Saldo consolidado', type: 'line', data: meses.map(m => m.saldo), borderColor: 'rgba(37, 99, 235, 1)', backgroundColor: 'transparent' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + pyg(c.parsed.y) } } }, scales: scales(pyg) }
    });
    new Chart(document.getElementById('finanzasCuentas'), {
        type: 'bar',
        data: { labels: cuentas.map(c => c.nombre), datasets: [{ label: 'Saldo', data: cuentas.map(c => c.saldo), backgroundColor: 'rgba(16, 185, 129, 0.75)' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => pyg(c.parsed.y) } } }, scales: scales(pyg) }
    });
})();
</script>
@endpush
