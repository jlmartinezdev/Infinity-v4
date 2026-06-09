@extends('layouts.app')

@section('title', 'Rendición #'.$rendicion->id)

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('cobros-rendiciones.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver al listado</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Rendición #{{ $rendicion->id }}</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Registrada el {{ $rendicion->fecha_rendicion->format('d/m/Y H:i') }}
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cobrador</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $rendicion->cobrador?->name ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recibió (tesorero)</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $rendicion->tesorero?->name ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto rendido</p>
            <p class="mt-1 text-2xl font-bold text-green-700 dark:text-green-400">{{ number_format((float) $rendicion->monto, 0, ',', '.') }} Gs.</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $rendicion->cantidad_cobros }} recibo{{ $rendicion->cantidad_cobros === 1 ? '' : 's' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Observaciones</p>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $rendicion->observaciones ?: '—' }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recibos incluidos</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recibo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha pago</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rendicion->cobros as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $c->numero_recibo }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ optional($c->fecha_pago)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                {{ trim(($c->cliente?->nombre ?? '').' '.($c->cliente?->apellido ?? '')) }}
                                @if($c->cliente?->cedula)
                                    <span class="text-gray-500 dark:text-gray-400 text-xs">({{ $c->cliente->cedula }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format((float) $c->monto, 0, ',', '.') }} Gs.</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('cobros.show', $c) }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Sin recibos vinculados.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($rendicion->cobros->isNotEmpty())
                    <tfoot class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-green-700 dark:text-green-400 text-right">{{ number_format((float) $rendicion->monto, 0, ',', '.') }} Gs.</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
