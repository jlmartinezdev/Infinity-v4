@extends('layouts.app')

@section('title', 'Crear factura interna - Servicio')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('servicios.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a servicios</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Crear factura interna</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cliente: {{ $servicio->cliente->nombre ?? '' }} {{ $servicio->cliente->apellido ?? '' }} — Plan: {{ $servicio->plan->nombre ?? '—' }}</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <form action="{{ route('facturas.store-crear-interna-servicio', $servicio) }}" method="POST" class="space-y-6">
        @csrf

        {{-- Datos de la factura --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos de la factura</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="min-w-0">
                        <label for="fecha_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de emisión *</label>
                        <input type="date" name="fecha_emision" id="fecha_emision" value="{{ old('fecha_emision', $fechaEmision) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_emision')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0">
                        <label for="fecha_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de vencimiento *</label>
                        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="{{ old('fecha_vencimiento', $fechaVencimiento) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_vencimiento')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0 flex flex-col">
                        <label for="fecha_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de pago (opcional)</label>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5 h-4">Referencia para cobros: si el cobro es antes de esta fecha, se usará esta fecha en el recibo; si es después, se usará la fecha del cobro.</span>
                        <input type="date" name="fecha_pago" id="fecha_pago" value="{{ old('fecha_pago', $fechaPago) }}"
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0 flex flex-col">
                        <label for="descuento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descuento (Gs.)</label>
                        <span class="block h-4 mt-0.5" aria-hidden="true"></span>
                        <input type="number" name="descuento" id="descuento" value="{{ old('descuento', 0) }}" min="0" step="0.01"
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('descuento')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0">
                        <label for="periodo_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período desde *</label>
                        <input type="date" name="periodo_desde" id="periodo_desde" value="{{ old('periodo_desde', $periodoDesde) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('periodo_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0">
                        <label for="periodo_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período hasta *</label>
                        <input type="date" name="periodo_hasta" id="periodo_hasta" value="{{ old('periodo_hasta', $periodoHasta) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('periodo_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Ítems de la factura --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Ítems de la factura</h2>
            </div>
            <div class="p-6">
                @if(!empty($prorrateoInfo))
                    <div class="mb-4 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm">
                        <p class="font-medium text-amber-800 dark:text-amber-200">Prorrateo aplicado</p>
                        <p class="mt-1 text-amber-700 dark:text-amber-300">
                            Instalado el {{ $prorrateoInfo['fecha_instalacion'] }} — {{ $prorrateoInfo['dias_restantes'] }} días restantes de {{ $prorrateoInfo['dias_en_mes'] }} en el mes.
                            Plan: {{ number_format($prorrateoInfo['precio_plan'], 0, ',', '.') }} Gs. → Monto prorrateado: <strong>{{ number_format($prorrateoInfo['precio_prorrateado'], 0, ',', '.') }} Gs.</strong>
                        </p>
                    </div>
                @endif
                <div class="space-y-4" id="items-container">
                    @php
                        $oldItems = old('items', [
                            [
                                'descripcion' => $descripcion,
                                'cantidad' => 1,
                                'precio_unitario' => $precio,
                                'impuesto_id' => $impuestoExento?->id ?? '',
                            ],
                        ]);
                    @endphp
                    @foreach($oldItems as $idx => $item)
                    <div class="flex flex-col sm:flex-row gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 item-row">
                        <div class="flex-1 min-w-0">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                            <input type="text" name="items[{{ $idx }}][descripcion]" value="{{ $item['descripcion'] ?? '' }}" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cantidad</label>
                            <input type="number" name="items[{{ $idx }}][cantidad]" value="{{ $item['cantidad'] ?? 1 }}" min="0.0001" step="0.0001" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Precio unit. (Gs.)</label>
                            <input type="number" name="items[{{ $idx }}][precio_unitario]" value="{{ $item['precio_unitario'] ?? 0 }}" min="0" step="0.01" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div class="w-48">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Impuesto</label>
                            <select name="items[{{ $idx }}][impuesto_id]" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="">Exento</option>
                                @foreach($impuestos as $imp)
                                    <option value="{{ $imp->id }}" {{ ($item['impuesto_id'] ?? '') == $imp->id ? 'selected' : '' }}>{{ $imp->nombre }} ({{ $imp->porcentaje }}%)</option>
                                @endforeach
                            </select>
                        </div>
                        @if($idx > 0)
                        <div class="flex items-end">
                            <button type="button" class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg remove-item" data-index="{{ $idx }}" title="Quitar ítem">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-item" class="mt-4 px-3 py-2 text-sm text-purple-600 dark:text-purple-400 border border-purple-300 dark:border-purple-600 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors">
                    + Agregar ítem
                </button>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                Crear factura interna
            </button>
            <a href="{{ route('servicios.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = {{ count($oldItems) }};
    const container = document.getElementById('items-container');
    const addBtn = document.getElementById('add-item');

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'flex flex-col sm:flex-row gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 item-row';
        row.innerHTML = `
            <div class="flex-1 min-w-0">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                <input type="text" name="items[${itemIndex}][descripcion]" value="" required
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="w-24">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cantidad</label>
                <input type="number" name="items[${itemIndex}][cantidad]" value="1" min="0.0001" step="0.0001" required
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="w-32">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Precio unit. (Gs.)</label>
                <input type="number" name="items[${itemIndex}][precio_unitario]" value="0" min="0" step="0.01" required
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Impuesto</label>
                <select name="items[${itemIndex}][impuesto_id]" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Exento</option>
                    @foreach($impuestos as $imp)
                        <option value="{{ $imp->id }}">{{ $imp->nombre }} ({{ $imp->porcentaje }}%)</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg remove-item" title="Quitar ítem">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>
        `;
        container.appendChild(row);
        row.querySelector('.remove-item').addEventListener('click', function() {
            row.remove();
        });
        itemIndex++;
    });

    container.querySelectorAll('.remove-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = btn.closest('.item-row');
            if (container.querySelectorAll('.item-row').length > 1) {
                row.remove();
            }
        });
    });
});
</script>
@endpush
@endsection
