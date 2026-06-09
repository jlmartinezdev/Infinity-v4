@extends('layouts.app')

@section('title', 'Factura interna — Servicio especial')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('servicios.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a servicios</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Factura interna — Servicio especial</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Cliente: {{ $servicio->cliente->nombre ?? '' }} {{ $servicio->cliente->apellido ?? '' }} — Plan: {{ $servicio->plan->nombre ?? '—' }}
        </p>
        <p class="text-xs text-amber-700 dark:text-amber-300 mt-2">
            Este tipo de factura no tiene período facturado ni fecha de vencimiento. Ideal para cargos puntuales o servicios especiales.
        </p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <form action="{{ route('facturas.store-crear-interna-servicio-especial', $servicio) }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos de la factura</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="fecha_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de emisión *</label>
                    <input type="date" name="fecha_emision" id="fecha_emision" value="{{ old('fecha_emision', $fechaEmision) }}" required
                           class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('fecha_emision')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="descuento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descuento (Gs.)</label>
                    <input type="number" name="descuento" id="descuento" value="{{ old('descuento', 0) }}" min="0" step="0.01"
                           class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('descuento')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="2" maxlength="500"
                              class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                              placeholder="Opcional">{{ old('observaciones') }}</textarea>
                    @error('observaciones')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Ítems de la factura</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4" id="items-container">
                    @php
                        $oldItems = old('items', [
                            [
                                'descripcion' => $descripcion,
                                'cantidad' => 1,
                                'precio_unitario' => $precioPlan,
                                'impuesto_id' => $impuestoExento?->id ?? '',
                            ],
                        ]);
                    @endphp
                    @foreach($oldItems as $idx => $item)
                    <div class="flex flex-col sm:flex-row gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 item-row">
                        <div class="flex-1 min-w-0">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                            <input type="text" name="items[{{ $idx }}][descripcion]" value="{{ $item['descripcion'] ?? '' }}" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cantidad</label>
                            <input type="number" name="items[{{ $idx }}][cantidad]" value="{{ $item['cantidad'] ?? 1 }}" min="0.0001" step="0.0001" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Precio (Gs.)</label>
                            <input type="number" name="items[{{ $idx }}][precio_unitario]" value="{{ $item['precio_unitario'] ?? 0 }}" min="0" step="0.01" required
                                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div class="w-48">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Impuesto</label>
                            <select name="items[{{ $idx }}][impuesto_id]" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="">Exento</option>
                                @foreach($impuestos as $imp)
                                    <option value="{{ $imp->id }}" {{ ($item['impuesto_id'] ?? '') == $imp->id ? 'selected' : '' }}>{{ $imp->nombre }} ({{ $imp->porcentaje }}%)</option>
                                @endforeach
                            </select>
                        </div>
                        @if($idx > 0)
                        <div class="flex items-end">
                            <button type="button" class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg remove-item" title="Quitar ítem">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-item" class="mt-4 px-3 py-2 text-sm text-purple-600 dark:text-purple-400 border border-purple-300 dark:border-purple-600 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/30">
                    + Agregar ítem
                </button>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Crear factura especial</button>
            <a href="{{ route('servicios.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = {{ count($oldItems) }};
    const container = document.getElementById('items-container');
    document.getElementById('add-item').addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'flex flex-col sm:flex-row gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 item-row';
        row.innerHTML = `
            <div class="flex-1 min-w-0">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                <input type="text" name="items[${itemIndex}][descripcion]" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="w-24">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cantidad</label>
                <input type="number" name="items[${itemIndex}][cantidad]" value="1" min="0.0001" step="0.0001" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="w-32">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Precio (Gs.)</label>
                <input type="number" name="items[${itemIndex}][precio_unitario]" value="0" min="0" step="0.01" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Impuesto</label>
                <select name="items[${itemIndex}][impuesto_id]" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
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
        row.querySelector('.remove-item').addEventListener('click', function() { row.remove(); });
        itemIndex++;
    });
    container.querySelectorAll('.remove-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = btn.closest('.item-row');
            if (container.querySelectorAll('.item-row').length > 1) row.remove();
        });
    });
});
</script>
@endpush
@endsection
