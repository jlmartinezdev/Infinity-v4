@extends('layouts.app')

@section('title', 'Pendiente de pago')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Pendiente de pago</h1>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('promesas-pago.index') }}"
               class="inline-flex items-center justify-center gap-2 px-3 py-2 border border-amber-300 dark:border-amber-700 rounded-lg text-sm font-medium text-amber-900 dark:text-amber-200 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-colors"
               title="Lista de promesas de pago">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span class="hidden sm:inline">Promesas</span>
            </a>
            @if(auth()->user()?->tienePermiso('cobros.crear'))
                <a href="{{ route('cobros.create') }}"
                   class="inline-flex items-center justify-center p-2 rounded-lg bg-green-600 text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors"
                   title="Registrar cobro"
                   aria-label="Registrar cobro">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </a>
            @endif
        </div>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Facturas internas con saldo pendiente. Busque por nombre o cédula del cliente. Marque filas y use "Multicobro" para abonar varias facturas de una vez.</p>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('factura-internas.pendientes') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Buscar por nombre o cédula</label>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre, apellido o cédula del cliente..."
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-700 dark:bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-800 dark:hover:bg-gray-500 text-sm">Buscar</button>
                    @if(request('buscar'))
                        <a href="{{ route('factura-internas.pendientes') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">Limpiar</a>
                    @endif
                </div>
            </div>
        </form>

        @if(auth()->user()?->tienePermiso('cobros.crear'))
        <form id="form-multicobro" method="GET" action="{{ route('cobros.multicobro') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm text-gray-700 dark:text-gray-300">Marcar facturas para multicobro:</span>
                <button type="submit" id="btn-multicobro" disabled class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                    Registrar multicobro (<span id="count-selected">0</span>)
                </button>
            </div>
        </form>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        @if(auth()->user()?->tienePermiso('cobros.crear'))
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500 js-select-all-pendientes" title="Seleccionar todos">
                            </th>
                        @endif
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Período</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vencimiento</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cobrado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Promesa</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-36">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse ($facturas as $f)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            @if(auth()->user()?->tienePermiso('cobros.crear'))
                                <td class="px-4 py-3">
                                    <input type="checkbox" form="form-multicobro" name="factura_interna_ids[]" value="{{ $f->id }}" class="js-pendiente-cb rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500">
                                </td>
                            @endif
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $f->id }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $f->cliente->nombre }} {{ $f->cliente->apellido }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $f->periodo_desde->format('d/m/Y') }} - {{ $f->periodo_hasta->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $f->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($f->total, 0, ',', '.') }} {{ $f->moneda }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($f->monto_pagado, 0, ',', '.') }} {{ $f->moneda }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-amber-700 dark:text-amber-400">{{ number_format($f->saldo_pendiente, 0, ',', '.') }} {{ $f->moneda }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                @if($f->promesaPago)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200" title="Promesa de pago">
                                        Hasta {{ $f->promesaPago->vencimiento_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @include('factura-internas.partials.pendientes-acciones-iconos', ['f' => $f])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->tienePermiso('cobros.crear') ? 10 : 9 }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay facturas internas pendientes de pago.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($facturas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $facturas->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

@if(auth()->user()?->tienePermiso('cobros.crear'))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('form-multicobro');
    var checkboxes = document.querySelectorAll('.js-pendiente-cb');
    var selectAll = document.querySelector('.js-select-all-pendientes');
    var btn = document.getElementById('btn-multicobro');
    var countEl = document.getElementById('count-selected');

    function updateCount() {
        var n = document.querySelectorAll('.js-pendiente-cb:checked').length;
        countEl.textContent = n;
        btn.disabled = n === 0;
        if (selectAll) selectAll.checked = n > 0 && n === checkboxes.length;
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateCount);
    });
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateCount();
        });
    }
    updateCount();
});
</script>
@endpush
@endif
@endsection
