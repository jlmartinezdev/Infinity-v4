@extends('layouts.app')

@section('title', 'Editar línea de cable')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.lineas-cable.index') }}" class="text-purple-600 hover:underline text-sm">&larr; Volver</a>
        <h1 class="text-2xl font-bold mt-1">Editar línea #{{ $lineaCable->linea_cable_id }}</h1>
    </div>

    <form action="{{ route('sistema.lineas-cable.update', $lineaCable) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="fibra_color_id" class="block text-sm font-medium">Color de fibra *</label>
                <select name="fibra_color_id" id="fibra_color_id" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    @foreach($fibraColores as $fc)
                        <option value="{{ $fc->fibra_color_id }}" {{ old('fibra_color_id', $lineaCable->fibra_color_id) == $fc->fibra_color_id ? 'selected' : '' }}>
                            {{ $fc->nombre }} {{ $fc->codigo_hex ? '(' . $fc->codigo_hex . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="origen_tipo" class="block text-sm font-medium">Origen tipo</label>
                    <select name="origen_tipo" id="origen_tipo" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                        <option value="nodo" {{ old('origen_tipo', $lineaCable->origen_tipo) === 'nodo' ? 'selected' : '' }}>Nodo</option>
                        <option value="caja_nap" {{ old('origen_tipo', $lineaCable->origen_tipo) === 'caja_nap' ? 'selected' : '' }}>Caja NAP</option>
                        <option value="splitter_primario" {{ old('origen_tipo', $lineaCable->origen_tipo) === 'splitter_primario' ? 'selected' : '' }}>Splitter primario</option>
                        <option value="splitter_secundario" {{ old('origen_tipo', $lineaCable->origen_tipo) === 'splitter_secundario' ? 'selected' : '' }}>Splitter secundario</option>
                        <option value="salida_pon" {{ old('origen_tipo', $lineaCable->origen_tipo) === 'salida_pon' ? 'selected' : '' }}>Salida PON</option>
                    </select>
                </div>
                <div>
                    <label for="origen_id" class="block text-sm font-medium">Origen ID</label>
                    <input type="number" name="origen_id" id="origen_id" value="{{ old('origen_id', $lineaCable->origen_id) }}" required min="1" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="destino_tipo" class="block text-sm font-medium">Destino tipo</label>
                    <select name="destino_tipo" id="destino_tipo" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                        <option value="nodo" {{ old('destino_tipo', $lineaCable->destino_tipo) === 'nodo' ? 'selected' : '' }}>Nodo</option>
                        <option value="caja_nap" {{ old('destino_tipo', $lineaCable->destino_tipo) === 'caja_nap' ? 'selected' : '' }}>Caja NAP</option>
                        <option value="splitter_primario" {{ old('destino_tipo', $lineaCable->destino_tipo) === 'splitter_primario' ? 'selected' : '' }}>Splitter primario</option>
                        <option value="splitter_secundario" {{ old('destino_tipo', $lineaCable->destino_tipo) === 'splitter_secundario' ? 'selected' : '' }}>Splitter secundario</option>
                        <option value="salida_pon" {{ old('destino_tipo', $lineaCable->destino_tipo) === 'salida_pon' ? 'selected' : '' }}>Salida PON</option>
                    </select>
                </div>
                <div>
                    <label for="destino_id" class="block text-sm font-medium">Destino ID</label>
                    <input type="number" name="destino_id" id="destino_id" value="{{ old('destino_id', $lineaCable->destino_id) }}" required min="1" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                </div>
            </div>

            <div>
                <label for="longitud_metros" class="block text-sm font-medium">Longitud (metros)</label>
                <input type="number" name="longitud_metros" id="longitud_metros" value="{{ old('longitud_metros', $lineaCable->longitud_metros) }}" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
            </div>

            <div>
                <label for="notas" class="block text-sm font-medium">Notas</label>
                <textarea name="notas" id="notas" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">{{ old('notas', $lineaCable->notas) }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar</button>
            <a href="{{ route('sistema.lineas-cable.index') }}" class="px-4 py-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
