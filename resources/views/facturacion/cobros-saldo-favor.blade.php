@extends('layouts.app')
@use('App\Models\Cobro')
@use('App\Support\CobrosMesVentana')

@section('title', 'Cobros adelantados — saldo a favor')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('facturacion.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Dashboard de facturación
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cobros adelantados (saldo a favor)</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Cobros sin factura asociada o con exceso sobre lo aplicado a facturas, registrados como crédito del cliente.
            </p>
        </div>
        <form method="get" action="{{ route('facturacion.cobros-saldo-favor') }}" class="flex items-end gap-2">
            <div>
                <label for="mes" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Mes del pago</label>
                <select name="mes" id="mes" onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-2 focus:ring-2 focus:ring-purple-500">
                    @foreach($opcionesMes as $opcion)
                        <option value="{{ $opcion['valor'] }}" @selected($mesSeleccionado === $opcion['valor'])>{{ $opcion['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Saldo a favor registrado</p>
            <p class="mt-2 text-2xl font-bold text-purple-700 dark:text-purple-300">{{ number_format($totalSaldoFavor, 0, ',', '.') }} PYG</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $mesEtiqueta }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Recibos con saldo a favor</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalCobros, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recibo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto cobro</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo a favor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forma pago</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cobrador</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($cobros as $cobro)
                        @php
                            $montoSaldoFavor = (float) ($cobro->monto_saldo_favor ?? CobrosMesVentana::montoSaldoFavorRegistrado($cobro));
                            $tipo = CobrosMesVentana::tipoSaldoFavorRegistrado($cobro);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $cobro->fecha_pago?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $cobro->numero_recibo }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                @if($cobro->cliente)
                                    <div class="font-medium">{{ $cobro->cliente->nombre }} {{ $cobro->cliente->apellido }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cobro->cliente->cedula }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($tipo === 'sin_factura')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200">Sin factura</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200">Exceso</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ number_format((float) $cobro->monto, 0, ',', '.') }} PYG
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-purple-700 dark:text-purple-300 whitespace-nowrap">
                                {{ number_format($montoSaldoFavor, 0, ',', '.') }} PYG
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $cobro->usuario?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                <a href="{{ route('cobros.show', $cobro) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-medium">Ver recibo</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                No hay cobros registrados como saldo a favor en {{ $mesEtiqueta }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cobros->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $cobros->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
