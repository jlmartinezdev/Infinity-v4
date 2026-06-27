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

    @if ($pedidosMapa->isEmpty())
        <div class="mt-4 px-4 py-6 text-center text-gray-500 bg-gray-50 rounded-lg border border-gray-200">
            No hay pedidos pendientes de instalación con coordenadas (lat/lon).
        </div>
    @endif
</div>

@push('scripts')
@php
    $mapasConfig = [
        'apiKey' => $googleMapsApiKey,
        'pedidos' => $pedidosMapa->values()->all(),
        'nodos' => ($nodos ?? collect())->map(fn ($n) => $n->toArraySelect())->values()->all(),
        'planes' => ($planes ?? collect())->map(fn ($p) => [
            'plan_id' => $p->plan_id,
            'nombre' => $p->nombre,
            'tecnologia_id' => $p->tecnologia_id,
        ])->values()->all(),
        'tiposTecnologia' => ($tiposTecnologia ?? collect())->map(fn ($t) => [
            'tecnologia_id' => $t->tecnologia_id,
            'descripcion' => $t->descripcion,
        ])->values()->all(),
        'aprobarEstadoUrl' => ($puedeAprobar ?? false) ? route('pedidos.aprobar-estado', ':pedido') : '',
        'urlOpcionesNodoAprobacion' => ($puedeAprobar ?? false) ? url('pedidos/nodos') . '/__id__/opciones-aprobacion' : '',
    ];
@endphp
<script>
    window.__MAPAS_PEDIDOS_CONFIG__ = @json($mapasConfig);
</script>
<script src="{{ asset(mix('js/mapas-pedidos.js')) }}"></script>
@endpush
@endsection
