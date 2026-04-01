@extends('layouts.app')

@section('title', 'Cobros - Lista de servicios')

@section('content')
<div id="cobros-servicios-app">
    {{-- Vue CobrosServiciosList.vue monta aquí todo el contenido --}}
</div>

@php
    $config = [
        'servicios' => $servicios,
        'urlCobrosIndex' => $urlCobrosIndex ?? route('cobros.index'),
        'urlEditServicioBase' => $urlEditServicioBase ?? url('servicios') . '/__servicio_id__/edit',
        'urlCrearCobroBase' => $urlCrearCobroBase ?? route('cobros.create') . '?cliente_id=__cliente_id__',
        'canCrearCobro' => $canCrearCobro ?? false,
    ];
@endphp
<script>
window.__COBROS_SERVICIOS_CONFIG__ = @json($config);
</script>

@push('scripts')
<script src="{{ asset(mix('js/cobros-servicios.js')) }}" defer></script>
@endpush
@endsection
