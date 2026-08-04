@extends('layouts.app')

@section('title', 'Mapas de pedidos')

@section('content')
{{-- Rompe el padding de <main>; Vue ocupa todo el alto (toolbar + mapa) --}}
<div class="mapas-pedidos-page -mx-4 sm:-mx-6 lg:-mx-8 -my-8 h-[calc(100vh-4rem)] min-h-[420px]">
    <div id="mapas-pedidos-app" class="w-full h-full"></div>
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
        'urlClientes' => $urlClientes ?? route('clientes.mapas-pedidos.clientes'),
    ];
@endphp
<script>
    window.__MAPAS_PEDIDOS_CONFIG__ = @json($mapasConfig);
</script>
<script src="{{ asset(mix('js/mapas-pedidos.js')) }}"></script>
@endpush
@endsection
