@extends('layouts.app')

@section('title', 'Lista de clientes')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Clientes</h1>
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

        @php
            $clientesListConfig = [
                'clientes' => $clientes->values(),
                'firstItem' => 1,
                'csrfToken' => csrf_token(),
                'urlEditClienteBase' => url('clientes') . '/__id__/edit',
                'urlDestroyClienteBase' => url('clientes') . '/__id__',
                'urlCreateCliente' => route('clientes.create'),
                'urlEditServicioBase' => url('servicios') . '/__servicio_id__/edit',
                'urlCreateServicioBase' => url('servicios') . '/create?cliente_id=__cliente_id__',
                'urlBuscarTemp' => route('clientes.buscar-temp'),
                'urlActualizarDesdeTempBase' => url('clientes') . '/__id__/actualizar-desde-temp',
                'urlDetalleClienteBase' => url('clientes') . '/__id__/detalle',
                'urlAccionesClienteBase' => url('clientes') . '/__id__/acciones',
                'puedeEditar' => auth()->user()?->tienePermiso('clientes.editar') ?? false,
                'initialBuscar' => request('buscar', ''),
                'initialEstado' => request('estado', 'todos'),
                'initialSinServicio' => request('sin_servicio', ''),
            ];
        @endphp
        <script>
        window.__CLIENTES_LIST_CONFIG__ = @json($clientesListConfig);
        </script>
    </div>
</div>
@push('scripts')
<script src="{{ asset(mix('js/clientes-list.js')) }}" defer></script>
@endpush
@endsection
