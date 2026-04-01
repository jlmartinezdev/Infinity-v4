@extends('layouts.app')

@section('title', 'Pendiente de pago')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Pendiente de pago</h1>
        <a href="{{ route('cobros.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
            Registrar cobro
        </a>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
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
                            <td class="px-4 py-3 flex items-center gap-2">
                                <a href="{{ route('factura-internas.show', $f) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm" title="Ver factura interna"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                </svg>
                                </a>
                                @if(auth()->user()?->tienePermiso('cobros.crear'))
                                    <a href="{{ route('cobros.create', ['cliente_id' => $f->cliente_id, 'factura_interna_id' => $f->id]) }}" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                                       <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Registrar cobro
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->tienePermiso('cobros.crear') ? 9 : 8 }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay facturas internas pendientes de pago.</td>
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
