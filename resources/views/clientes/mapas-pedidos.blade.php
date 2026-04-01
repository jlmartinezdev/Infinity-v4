@extends('layouts.app')

@section('title', 'Mapas de pedidos')

@section('content')
<div class="max-w-full mx-auto flex flex-col h-[calc(100vh-8rem)] min-h-[400px]">
    <div class="mb-4 flex-shrink-0">
        <h1 class="text-2xl font-bold text-gray-900">Mapas de pedidos</h1>
        <p class="mt-1 text-sm text-gray-500">Pedidos con instalación pendiente (no instalados ni descartados) y con coordenadas GPS. Haz clic en un marcador para ver detalles.</p>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden flex-1 min-h-[300px]">
        <div id="mapas-pedidos-app" class="w-full h-full min-h-[300px]"></div>
    </div>

    @if ($pedidos->isEmpty())
        <div class="mt-4 px-4 py-6 text-center text-gray-500 bg-gray-50 rounded-lg border border-gray-200">
            No hay pedidos pendientes de instalación con coordenadas (lat/lon).
        </div>
    @endif
</div>

@push('scripts')
@php
    $mapasConfig = [
        'apiKey' => $googleMapsApiKey,
        'pedidos' => $pedidos->map(function ($p) {
            $ultimoConTecnologia = $p->estadoPedidoDetalles
                ->whereNotNull('tecnologia_id')
                ->sortByDesc('created_at')
                ->first();
            $tecnologiaDesc = $ultimoConTecnologia?->tipoTecnologia?->descripcion ?? null;
            return [
                'pedido_id' => $p->pedido_id,
                'lat' => (float) $p->lat,
                'lon' => (float) $p->lon,
                'ubicacion' => $p->ubicacion,
                'maps_gps' => $p->maps_gps,
                'fecha_pedido' => $p->fecha_pedido ? $p->fecha_pedido->toDateString() : null,
                'cliente' => $p->cliente ? $p->cliente->nombre . ' ' . $p->cliente->apellido : null,
                'plan' => $p->plan ? $p->plan->nombre : null,
                'tecnologia_descripcion' => $tecnologiaDesc,
            ];
        })->values()->all(),
    ];
@endphp
<script>
    window.__MAPAS_PEDIDOS_CONFIG__ = @json($mapasConfig);
</script>
<script src="{{ asset(mix('js/mapas-pedidos.js')) }}"></script>
@endpush
@endsection
