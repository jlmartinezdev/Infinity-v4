@extends('layouts.app')

@section('title', 'Nueva compra')

@section('content')
<div class="max-w-screen-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nueva compra</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('compras.store') }}" method="POST" id="form-compra">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-4 lg:min-w-0">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Articulos</h2>
                        <input type="text" id="buscar-producto" placeholder="Buscar por codigo o nombre..."
                            class="w-full max-w-sm px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                    </div>
                    <div class="overflow-auto border border-gray-200 dark:border-gray-700 rounded-lg max-h-[70vh]">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Codigo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Articulo</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Precio</th>
                                    <th class="px-3 py-2 w-16"></th>
                                </tr>
                            </thead>
                            <tbody id="catalogo-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-4 lg:sticky lg:top-4 self-start lg:min-w-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label for="proveedor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor *</label>
                            <select name="proveedor_id" id="proveedor_id" required
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                                <option value="">Seleccionar proveedor...</option>
                                @foreach($proveedores as $prov)
                                    <option value="{{ $prov->id }}" {{ old('proveedor_id') == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                                @endforeach
                            </select>
                            @error('proveedor_id')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
                            <input type="date" name="fecha" id="fecha" value="{{ old('fecha', date('Y-m-d')) }}"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                                required>
                            @error('fecha')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="numero_factura" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Factura</label>
                            <input type="text" name="numero_factura" id="numero_factura" value="{{ old('numero_factura') }}"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                                maxlength="100" placeholder="Ej: FAC-001">
                            @error('numero_factura')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="descuento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descuento</label>
                            <input type="number" name="descuento" id="descuento" value="{{ old('descuento', 0) }}" step="0.01" min="0"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                            @error('descuento')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="impuesto" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Impuesto</label>
                            <input type="number" name="impuesto" id="impuesto" value="{{ old('impuesto', 0) }}" step="0.01" min="0"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                            @error('impuesto')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                            <input type="text" name="notas" id="notas" value="{{ old('notas') }}"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                            @error('notas')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div id="compra-detalle-app"></div>
                    @error('detalles')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $compraCreateConfig = [
        'productos' => $productosJs,
        'oldDetalles' => old('detalles', []),
        'cancelUrl' => route('compras.index'),
    ];
@endphp
<script>
    window.__COMPRA_CREATE_CONFIG__ = @json($compraCreateConfig);
</script>

@push('scripts')
<script src="{{ asset(mix('js/compras-create.js')) }}" defer></script>
@endpush
@endsection
