@extends('layouts.app')

@section('title', 'Notas de crédito')

@section('content')
<div id="notas-credito-app"></div>

@php
    $config = [
        'listUrl' => route('factura-internas.notas-credito.list'),
        'facturaBaseUrl' => url('factura-internas'),
        'clientes' => $clientes->map(fn ($c) => [
            'cliente_id' => $c->cliente_id,
            'nombre' => $c->nombre,
            'apellido' => $c->apellido,
        ])->values()->all(),
    ];
@endphp
<script>
    window.__NOTAS_CREDITO_CONFIG__ = @json($config);
</script>

@push('scripts')
<script src="{{ asset(mix('js/notas-credito-index.js')) }}" defer></script>
@endpush
@endsection
