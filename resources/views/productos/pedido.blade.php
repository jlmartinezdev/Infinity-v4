@extends('layouts.app')

@section('title', 'Armar pedido')

@section('content')
<div class="max-w-screen-2xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Armar pedido</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Agregá productos con cantidad y exportá código + cantidad para el proveedor.</p>
        </div>
        <a href="{{ route('productos.index') }}"
            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 rounded-lg font-medium border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
            Volver a productos
        </a>
    </div>

    <div id="pedido-productos-app"></div>
</div>

<script>
    window.__PEDIDO_PRODUCTOS_CONFIG__ = @json($pedidoConfig);
</script>

@push('scripts')
<script src="{{ asset(mix('js/pedido-productos.js')) }}" defer></script>
@endpush
@endsection
