@extends('layouts.app')

@section('title', 'Editar splitter secundario')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.cajas-nap.show', $splitterSecundario->cajaNap) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver a {{ $splitterSecundario->cajaNap->codigo }}</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar splitter secundario: {{ $splitterSecundario->codigo }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Caja NAP: {{ $splitterSecundario->cajaNap->codigo }} — Primario: {{ $splitterSecundario->splitterPrimario?->codigo }}</p>
    </div>

    <form action="{{ route('sistema.splitters-secundarios.update', $splitterSecundario) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="splitter_primario_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Splitter primario *</label>
                <select name="splitter_primario_id" id="splitter_primario_id" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @foreach($splitterSecundario->cajaNap->splitterPrimarios as $sp)
                        <option value="{{ $sp->splitter_primario_id }}" {{ old('splitter_primario_id', $splitterSecundario->splitter_primario_id) == $sp->splitter_primario_id ? 'selected' : '' }}>
                            {{ $sp->codigo }} ({{ $sp->ratio }})
                        </option>
                    @endforeach
                </select>
                @error('splitter_primario_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código *</label>
                    <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $splitterSecundario->codigo) }}" required maxlength="50"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('codigo')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ratio" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ratio *</label>
                    <select name="ratio" id="ratio" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="1:2" {{ old('ratio', $splitterSecundario->ratio) === '1:2' ? 'selected' : '' }}>1:2</option>
                        <option value="1:4" {{ old('ratio', $splitterSecundario->ratio) === '1:4' ? 'selected' : '' }}>1:4</option>
                        <option value="1:8" {{ old('ratio', $splitterSecundario->ratio) === '1:8' ? 'selected' : '' }}>1:8</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="puerto_entrada" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Puerto de entrada</label>
                <input type="number" name="puerto_entrada" id="puerto_entrada" value="{{ old('puerto_entrada', $splitterSecundario->puerto_entrada) }}" min="1"
                    class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="potencia_entrada" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Potencia entrada (dBm)</label>
                    <input type="number" name="potencia_entrada" id="potencia_entrada" value="{{ old('potencia_entrada', $splitterSecundario->potencia_entrada) }}" step="0.01" min="-50" max="10"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        placeholder="ej: -12.5">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Potencia recibida del splitter primario</p>
                    @error('potencia_entrada')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="potencia_salida" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Potencia salida (dBm)</label>
                    <input type="number" name="potencia_salida" id="potencia_salida" value="{{ old('potencia_salida', $splitterSecundario->potencia_salida) }}" step="0.01" min="-50" max="10"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        placeholder="ej: -18.2">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Potencia en puerto de salida (por rama)</p>
                    @error('potencia_salida')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                <select name="estado" id="estado" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="activo" {{ old('estado', $splitterSecundario->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ old('estado', $splitterSecundario->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>

            <div>
                <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
                <textarea name="notas" id="notas" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('notas', $splitterSecundario->notas) }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar cambios</button>
            <a href="{{ route('sistema.cajas-nap.show', $splitterSecundario->cajaNap) }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
