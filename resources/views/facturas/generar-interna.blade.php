@extends('layouts.app')

@section('title', 'Generar factura interna')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('facturas.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a facturación</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Generar factura interna</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Genera una factura a partir de los servicios activos del cliente (precio del plan por período). Si un servicio se instaló a mitad de mes, se aplicará prorrateo automático.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('facturas.store-generar-interna') }}" method="POST">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente *</label>
                    <select name="cliente_id" id="cliente_id" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Seleccione cliente</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->cliente_id }}" {{ (string) old('cliente_id', request('cliente_id')) === (string) $c->cliente_id ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="periodo_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período desde *</label>
                        <input type="date" name="periodo_desde" id="periodo_desde" value="{{ old('periodo_desde', $periodoDesde) }}" required
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('periodo_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="periodo_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período hasta *</label>
                        <input type="date" name="periodo_hasta" id="periodo_hasta" value="{{ old('periodo_hasta', $periodoHasta) }}" required
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('periodo_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Generar factura
                </button>
                <a href="{{ route('facturas.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
