@extends('layouts.app')

@section('title', 'Registrar pago - Gasto #' . $gasto->id)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Registrar pago - Gasto #{{ $gasto->id }}</h1>
        <a href="{{ route('gastos.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Volver</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <div class="mb-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <p class="text-sm text-gray-500 dark:text-gray-400">Gasto</p>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $gasto->categoria?->nombre ?? '—' }} · {{ $gasto->proveedor?->nombre ?? '—' }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $gasto->descripcion ?? '—' }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100 mt-2">Monto: {{ number_format($gasto->monto ?? 0, 2, ',', '.') }}</p>
        </div>

        <form action="{{ route('pagos.store') }}" method="POST">
            @csrf
            <input type="hidden" name="tipo" value="gasto">
            <input type="hidden" name="referencia_id" value="{{ $gasto->id }}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="{{ date('Y-m-d') }}" required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto</label>
                    <input type="number" name="monto" step="0.01" min="0.01" value="{{ $gasto->monto }}" required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Método de pago</label>
                    <select name="metodo_pago" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ref. pago (opcional)</label>
                    <input type="text" name="referencia_pago" maxlength="100" placeholder="Nº operación"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (opcional)</label>
                    <textarea name="notas" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Registrar pago</button>
                <a href="{{ route('gastos.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
