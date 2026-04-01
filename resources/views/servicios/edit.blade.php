@extends('layouts.app')

@section('title', 'Editar servicio')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar servicio #{{ $servicio->servicio_id }} — {{ $servicio->cliente->nombre }} {{ $servicio->cliente->apellido }}</h1>

    @if($servicio->servicioHotspot && auth()->user()?->tienePermiso('servicios.ver'))
    <div class="mb-6 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-xl border border-purple-200 dark:border-purple-800">
        <p class="text-sm text-purple-800 dark:text-purple-200">
            <strong>Hotspot:</strong> Usuario {{ $servicio->servicioHotspot->username }} en {{ $servicio->servicioHotspot->router?->nombre }}.
            <a href="{{ route('hotspot.index') }}" class="underline">Ver usuarios hotspot</a>
        </p>
    </div>
    @elseif(auth()->user()?->tienePermiso('servicios.ver'))
    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            <a href="{{ route('hotspot.create', ['servicio_id' => $servicio->servicio_id]) }}" class="text-purple-600 dark:text-purple-400 hover:underline">Asociar usuario Hotspot a este servicio</a>
        </p>
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('servicios.update', $servicio->servicio_id) }}" method="POST">
            @include('servicios._form', [
                'servicio' => $servicio,
                'clientes' => $clientes,
                'planes' => $planes,
                'pools' => $pools,
            ])
        </form>
    </div>
</div>
@endsection
