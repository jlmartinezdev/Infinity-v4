@extends('layouts.app')

@section('title', ($listaTab ?? 'clientes') === 'servicios' ? 'Lista de servicios' : 'Lista de clientes')

@section('content')
@php
    $listaTab = $listaTab ?? 'clientes';
    $puedeVerClientes = $puedeVerClientes ?? false;
    $puedeVerServicios = $puedeVerServicios ?? false;
    $clientesFiltro = $clientesFiltro ?? collect();
@endphp
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Clientes y servicios</h1>
        @include('listas._tabs')
    </div>

    @if ($puedeVerClientes || $puedeVerServicios)
        @include('partials.lista-buscar')
    @endif

    @if ($puedeVerClientes)
        <div data-lista-panel="clientes" @if($listaTab !== 'clientes') hidden @endif>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4 mb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('clientes.mapa-activos') }}"
                        class="inline-flex items-center px-4 py-2 border border-purple-600 text-purple-700 dark:text-purple-300 dark:border-purple-400 rounded-lg font-medium hover:bg-purple-50 dark:hover:bg-purple-900/20 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        <svg class="w-5 h-5 mr-2 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                        </svg>
                        Mapa activos
                    </a>
                    <a href="{{ route('clientes.create') }}"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        Nuevo cliente
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div id="clientes-list-app"></div>
            </div>
        </div>
    @endif

    @if ($puedeVerServicios)
        <div data-lista-panel="servicios" @if($listaTab !== 'servicios') hidden @endif>
            <div id="servicios-index-app"></div>
        </div>
    @endif
</div>

@php
    if ($puedeVerClientes) {
        $clientesListConfig = [
            'clientes' => ($clientes ?? collect())->values(),
            'firstItem' => 1,
            'csrfToken' => csrf_token(),
            'urlEditClienteBase' => url('clientes') . '/__id__/edit',
            'urlDestroyClienteBase' => url('clientes') . '/__id__',
            'urlCreateCliente' => route('clientes.create'),
            'urlEditServicioBase' => url('servicios') . '/__servicio_id__/edit',
            'urlCreateServicioBase' => url('servicios') . '/create?cliente_id=__cliente_id__',
            'urlBuscarTemp' => route('clientes.buscar-temp'),
            'urlConsultarRucBase' => url('clientes') . '/__id__/consultar-ruc',
            'urlAplicarConsultaRucBase' => url('clientes') . '/__id__/aplicar-consulta-ruc',
            'urlActualizarDesdeTempBase' => url('clientes') . '/__id__/actualizar-desde-temp',
            'urlDetalleClienteBase' => url('clientes') . '/__id__/detalle',
            'urlAccionesClienteBase' => url('clientes') . '/__id__/detalle',
            'puedeEditar' => auth()->user()?->tienePermiso('clientes.editar') ?? false,
            'initialBuscar' => request('buscar', ''),
            'initialEstado' => request('estado', 'todos'),
            'initialSinServicio' => request('sin_servicio', ''),
        ];
    }

    if ($puedeVerServicios) {
        $serviciosIndexConfig = [
            'servicios' => $serviciosParaVue ?? [],
            'nodos' => isset($nodos) ? $nodos->map(fn ($n) => $n->toArraySelect())->values()->all() : [],
            'planes' => $planes ?? [],
            'clientes' => $clientesFiltro->map(fn ($c) => [
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
    }

    $listasTabsConfig = [
        'initialTab' => $listaTab,
        'initialBuscar' => request('buscar', ''),
        'urls' => [
            'clientes' => $urlClientes ?? route('clientes.index'),
            'servicios' => $urlServicios ?? route('servicios.index'),
        ],
    ];
@endphp

@if ($puedeVerClientes)
<script>window.__CLIENTES_LIST_CONFIG__ = @json($clientesListConfig);</script>
@endif
@if ($puedeVerServicios)
<script>window.__SERVICIOS_INDEX_CONFIG__ = @json($serviciosIndexConfig);</script>
@endif
<script>window.__LISTAS_TABS_CONFIG__ = @json($listasTabsConfig);</script>

@push('scripts')
<script src="{{ asset(mix('js/listas-cliente-servicio.js')) }}" defer></script>
@endpush
@endsection
