@extends('layouts.app')

@section('title', 'Técnicos en mapa')

@section('content')
<div class="max-w-full mx-auto flex flex-col h-[calc(100vh-8rem)] min-h-[420px]">
    <div class="mb-4 flex-shrink-0">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Técnicos en mapa</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Flota en vivo desde la app ISP Staff. Verde = reporte en los últimos 5 minutos; gris = offline.
            Podés activar capas de clientes, pedidos y tickets, y satélite. El mapa mantiene el estilo claro de Google.
        </p>
    </div>

    <div class="flex-1 min-h-0 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-100 dark:bg-gray-800">
        <div id="mapa-tecnicos-app" class="w-full h-full min-h-[420px]"></div>
    </div>
</div>

@push('scripts')
@php
    $mapaConfig = [
        'apiKey' => $googleMapsApiKey ?? '',
        'urlUbicaciones' => $urlUbicaciones ?? '',
        'urlClientes' => $urlClientes ?? '',
        'urlPedidos' => $urlPedidos ?? '',
        'urlTickets' => $urlTickets ?? '',
        'pollSegundos' => (int) ($pollSegundos ?? 15),
        'centerLat' => -25.2867,
        'centerLng' => -57.647,
    ];
@endphp
<script>
    window.__MAPA_TECNICOS_CONFIG__ = @json($mapaConfig);
</script>
<script src="{{ asset(mix('js/mapa-tecnicos.js')) }}"></script>
@endpush
@endsection
