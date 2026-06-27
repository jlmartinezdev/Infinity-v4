@extends('layouts.app')

@section('title', 'Nueva factura')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        @if(!empty($modoManual))
            <a href="{{ route('facturas.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a facturas</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Nueva factura — datos manuales</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleccione el cliente e ingrese el detalle de la factura sin cargar servicios automáticamente.</p>
        @else
            <a href="{{ route('facturas.create') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Cambiar cliente</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Nueva factura electrónica</h1>
            @isset($clienteSeleccionado)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Cliente: <strong class="text-gray-800 dark:text-gray-200">{{ $clienteSeleccionado->nombre }} {{ $clienteSeleccionado->apellido }}</strong>
                    @if($clienteSeleccionado->cedula)
                        · {{ $clienteSeleccionado->cedula }}
                    @endif
                </p>
            @endisset
        @endif
    </div>

    @if(!empty($modoManual))
        <div class="mb-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300">
            Elija un cliente registrado o ingrese una cédula/RUC no registrada (con búsqueda en padrón o datos manuales).
        </div>
    @elseif(!empty($detallesIniciales))
        <div class="mb-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-800 dark:text-blue-200">
            Datos cargados desde los servicios activos del cliente y la configuración SIFEN. Puede ajustar las líneas antes de crear el borrador.
        </div>
    @elseif(isset($sifenConfig) && $sifenConfig)
        <div class="mb-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-800 dark:text-blue-200">
            Timbrado y vigencia tomados de la configuración SIFEN activa.
        </div>
    @elseif(isset($clienteSeleccionado) && empty($detallesIniciales))
        <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-200">
            El cliente no tiene servicios activos con plan. Complete el detalle manualmente.
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('facturas.store') }}" method="POST">
            @include('facturas._form', [
                'factura' => null,
                'clientes' => $clientes ?? collect(),
                'clienteSeleccionado' => $clienteSeleccionado ?? null,
                'prefill' => $prefill ?? [],
                'detallesIniciales' => $detallesIniciales ?? [],
                'modoManual' => !empty($modoManual),
            ])
        </form>
    </div>
</div>
@endsection
