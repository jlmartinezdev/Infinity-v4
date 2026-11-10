@php
    $enlaces = [
        'finanzas.dashboard' => 'Dashboard',
        'finanzas.libro' => 'Libro',
        'finanzas.cobros-mes' => 'Cobros del mes',
        'finanzas.transferencias' => 'Transferencias',
        'finanzas.control-gastos' => 'Gastos diarios',
        'finanzas.egresos' => 'Gastos',
        'finanzas.retiros' => 'Retiros',
        'finanzas.liquidaciones' => 'Bancard',
        'finanzas.conciliacion' => 'Conciliación',
        'finanzas.cierres' => 'Cierres',
        'finanzas.cuentas' => 'Cuentas',
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach($enlaces as $ruta => $etiqueta)
        <a href="{{ route($ruta) }}"
            class="px-3 py-1.5 rounded-lg text-sm font-medium border {{ request()->routeIs($ruta) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600' }}">
            {{ $etiqueta }}
        </a>
    @endforeach
</div>
