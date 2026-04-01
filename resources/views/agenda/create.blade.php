@extends('layouts.app')

@section('title', 'Nueva cita en agenda')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nueva cita en agenda</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('agenda.store') }}" method="POST">
            @include('agenda._form', ['agenda' => null, 'pedido_id' => $pedidoId ?? null, 'fecha_preseleccionada' => $fechaPreseleccionada ?? null, 'cliente_id' => $clienteId ?? null])
        </form>
    </div>
</div>
@endsection
