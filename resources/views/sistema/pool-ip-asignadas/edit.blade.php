@extends('layouts.app')

@section('title', 'Editar IP del pool')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar IP del pool</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Pool: {{ $pool->ip_range }} — IP: {{ $registro->ip }}</p>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('sistema.pool-ip-asignadas.update', ['pool_id' => $pool->pool_id, 'ip' => str_replace('.', '_', $registro->ip)]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP</label>
                    <p class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-gray-100">{{ $registro->ip }}</p>
                </div>

                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
                    <select name="estado" id="estado" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="disponible" {{ old('estado', $registro->estado) === 'disponible' ? 'selected' : '' }}>Disponible</option>
                        <option value="asignada" {{ old('estado', $registro->estado) === 'asignada' ? 'selected' : '' }}>Asignada</option>
                        <option value="reservada" {{ old('estado', $registro->estado) === 'reservada' ? 'selected' : '' }}>Reservada</option>
                    </select>
                    @error('estado')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        Actualizar estado
                    </button>
                    <a href="{{ route('sistema.pool-ip-asignadas.index', ['pool_id' => $pool->pool_id]) }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
                        Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
