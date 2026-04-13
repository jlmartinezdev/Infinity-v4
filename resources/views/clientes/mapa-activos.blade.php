@extends('layouts.app')

@section('title', 'Mapa de clientes activos')

@section('content')
<div class="max-w-full mx-auto flex flex-col h-[calc(100vh-8rem)] min-h-[400px]">
    <div class="mb-4 flex-shrink-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Clientes activos en mapa</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Solo clientes en estado activo con enlace de ubicación del que se obtienen coordenadas GPS válidas. Cada punto usa el icono de casa; al hacer clic se muestra el nombre y el plan.</p>
        </div>
        <a href="{{ route('clientes.index') }}"
            class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium text-sm shrink-0">
            Volver a clientes
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex-1 min-h-[300px]">
        <div id="mapa-clientes-activos-app" class="w-full h-full min-h-[300px]"></div>
    </div>

    @if (empty($puntos))
        <div class="mt-4 px-4 py-6 text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
            No hay clientes activos con coordenadas válidas en la URL de ubicación.
        </div>
    @endif
</div>

@push('scripts')
@php
    $mapaConfig = [
        'apiKey' => $googleMapsApiKey,
        'puntos' => $puntos,
        'urlDetalleClienteBase' => url('clientes') . '/__id__/detalle',
    ];
@endphp
<script>
    window.__MAPA_CLIENTES_ACTIVOS_CONFIG__ = @json($mapaConfig);
</script>
<script src="{{ asset(mix('js/mapa-clientes-activos.js')) }}"></script>
@endpush
@endsection
