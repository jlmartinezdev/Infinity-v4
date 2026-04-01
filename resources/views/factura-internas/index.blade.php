@extends('layouts.app')

@section('title', 'Facturas internas')

@section('content')
<div id="facturas-internas-app"></div>

@php
    $config = [
        'listUrl' => route('factura-internas.list'),
        'facturaBaseUrl' => url('factura-internas'),
        'urlGenerarInterna' => route('facturas.generar-interna'),
        'urlEjecutarCrear' => route('factura-internas.ejecutar-crear-factura-internas'),
        'csrfToken' => csrf_token(),
        'clientes' => $clientes->map(fn ($c) => [
            'cliente_id' => $c->cliente_id,
            'nombre' => $c->nombre,
            'apellido' => $c->apellido,
        ])->values()->all(),
        'estados' => \App\Models\FacturaInterna::estados(),
        'canEjecutarCrear' => auth()->user()?->tienePermiso('factura-interna.crear') ?? false,
        'canEditar' => auth()->user()?->tienePermiso('factura-interna.crear') ?? false,
        'canEliminar' => auth()->user()?->tienePermiso('factura-interna.eliminar') ?? false,
        'flashSuccess' => session('success'),
        'flashError' => session('error'),
    ];
@endphp
<script>
    window.__FACTURAS_INTERNAS_CONFIG__ = @json($config);
</script>

@push('scripts')
@if(auth()->user()?->tienePermiso('factura-interna.eliminar') || auth()->user()?->tienePermiso('factura-interna.crear'))
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
@endif
<script src="{{ asset(mix('js/facturas-internas-index.js')) }}" defer></script>
@endpush
@endsection
