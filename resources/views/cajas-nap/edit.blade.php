@extends('layouts.app')

@section('title', 'Editar caja NAP')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.cajas-nap.index') }}" class="text-purple-600 hover:text-purple-700 text-sm font-medium">&larr; Volver a cajas NAP</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar caja NAP: {{ $cajaNap->codigo }}</h1>
    </div>

    <form action="{{ route('sistema.cajas-nap.update', $cajaNap) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nodo *</label>
                <select name="nodo_id" id="nodo_id" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ old('nodo_id', $cajaNap->nodo_id) == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                @error('nodo_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código *</label>
                    <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $cajaNap->codigo) }}" required maxlength="50"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    @error('codigo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo *</label>
                    <select name="tipo" id="tipo" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                        <option value="primaria" {{ old('tipo', $cajaNap->tipo) === 'primaria' ? 'selected' : '' }}>Primaria</option>
                        <option value="secundaria" {{ old('tipo', $cajaNap->tipo) === 'secundaria' ? 'selected' : '' }}>Secundaria</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion', $cajaNap->descripcion) }}" maxlength="255"
                    class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
            </div>

            <div>
                <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                <input type="text" name="direccion" id="direccion" value="{{ old('direccion', $cajaNap->direccion) }}" maxlength="255"
                    class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="lat" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitud</label>
                    <input type="number" name="lat" id="lat" value="{{ old('lat', $cajaNap->lat) }}" step="0.0000001" min="-90" max="90"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    @error('lat')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="lon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitud</label>
                    <input type="number" name="lon" id="lon" value="{{ old('lon', $cajaNap->lon) }}" step="0.0000001" min="-180" max="180"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    @error('lon')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar cambios</button>
            <a href="{{ route('sistema.cajas-nap.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
