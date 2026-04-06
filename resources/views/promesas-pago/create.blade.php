@extends('layouts.app')

@section('title', 'Promesa de pago')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <a href="{{ route('factura-internas.pendientes') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">&larr; Pendiente de pago</a>
        <a href="{{ route('promesas-pago.index') }}" class="text-amber-700 dark:text-amber-300 hover:underline font-medium">Lista de promesas</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Registrar promesa de pago</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Factura interna #{{ $factura->id }} — {{ $factura->cliente->nombre }} {{ $factura->cliente->apellido }}. Saldo pendiente: <strong>{{ number_format($factura->saldo_pendiente, 0, ',', '.') }} {{ $factura->moneda }}</strong></p>

    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('promesas-pago.store', $factura) }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="vencimiento_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vencimiento de la promesa (fecha y hora)</label>
                <input type="datetime-local" name="vencimiento_at" id="vencimiento_at" required
                       value="{{ old('vencimiento_at', $defaultVencimiento) }}"
                       min="{{ now()->format('Y-m-d\TH:i') }}"
                       class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hasta esa fecha/hora el cliente queda cubierto por la promesa; al vencer, si sigue debiendo, se puede volver a suspender el servicio.</p>
            </div>
            <div>
                <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones (opcional)</label>
                <textarea name="observaciones" id="observaciones" rows="3" maxlength="1000"
                          class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('observaciones') }}</textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="inline-flex justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    Guardar promesa
                </button>
                <a href="{{ route('factura-internas.pendientes') }}" class="inline-flex justify-center px-5 py-2.5 rounded-lg font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
