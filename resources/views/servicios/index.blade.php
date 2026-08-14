@extends('layouts.app')

@section('title', 'Servicios')

@section('content')
<div id="servicios-index-app">
    {{-- Vue ServiciosIndex.vue monta aquí todo el contenido --}}
</div>

@php
    $config = [
        'servicios' => $serviciosParaVue ?? [],
        'nodos' => isset($nodos) ? $nodos->map(fn ($n) => $n->toArraySelect())->values()->all() : [],
        'planes' => $planes ?? [],
        'clientes' => $clientes->map(fn($c) => [
            'cliente_id' => $c->cliente_id,
            'cedula' => $c->cedula,
            'nombre' => trim(($c->nombre ?? '') . ' ' . ($c->apellido ?? '')),
        ])->values()->all(),
        'canCreateFactura' => auth()->user()?->tienePermiso('facturas.crear') ?? false,
        'canCancelarServicio' => auth()->check()
            && auth()->user()->tienePermiso('servicios.crear')
            && auth()->user()->tienePermiso('facturas.crear'),
        'canDarBajaServicio' => auth()->user()?->tienePermiso('servicios.crear') ?? false,
        'formAction' => route('facturas.preparar-interna-desde-servicios'),
        'csrfToken' => csrf_token(),
        'urlIndex' => route('servicios.index'),
        'urlCreate' => route('servicios.create'),
        'urlEdit' => url('servicios') . '/__id__/edit',
        'urlMigrar' => url('servicios') . '/__id__/migrar',
        'urlDestroy' => url('servicios') . '/__id__',
        'urlActivar' => url('servicios') . '/__id__/activar',
        'urlSuspender' => url('servicios') . '/__id__/suspender',
        'urlCancelar' => url('servicios') . '/__id__/cancelar',
        'urlDarBaja' => url('servicios') . '/__id__/dar-baja',
        'urlSyncPppoe' => url('servicios') . '/__id__/sync-pppoe',
        'urlHerramientasRed' => url('servicios') . '/__id__/herramientas-red',
        'urlAccionesClienteBase' => auth()->user()?->tienePermiso('clientes.ver') ? url('clientes') . '/__id__/detalle' : '',
        'canVerClientes' => auth()->user()?->tienePermiso('clientes.ver') ?? false,
        'urlCrearFacturaInterna' => auth()->user()?->tienePermiso('facturas.crear') ? route('facturas.crear-interna-servicio', ['servicio' => '__id__']) : '',
        'urlCrearFacturaServicioEspecial' => auth()->user()?->tienePermiso('facturas.crear') ? route('facturas.crear-interna-servicio-especial', ['servicio' => '__id__']) : '',
        'urlCrearFacturaFraccionDeuda' => auth()->user()?->tienePermiso('facturas.crear') ? route('facturas.crear-interna-servicio-fraccion-deuda', ['servicio' => '__id__']) : '',
        'filtros' => [
            'buscar' => request('buscar', ''),
            'cliente_id' => request('cliente_id', ''),
            'nodo_id' => request('nodo_id', ''),
            'plan_id' => request('plan_id', ''),
            'estado' => request('estado', 'todos'),
            'estado_pago' => request('estado_pago', 'todos'),
            'app_tv' => request('app_tv', 'todos'),
            'fecha_desde' => request('fecha_desde', ''),
            'fecha_hasta' => request('fecha_hasta', ''),
        ],
    ];
@endphp
<script>
window.__SERVICIOS_INDEX_CONFIG__ = @json($config);
</script>

@push('scripts')
<script src="{{ asset(mix('js/servicios-index.js')) }}" defer></script>
@endpush
@endsection
