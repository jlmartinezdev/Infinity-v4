@extends('layouts.app')

@section('title', 'Editar OLT')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.show', $olt) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver al OLT</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar OLT</h1>
    </div>

    <form action="{{ route('sistema.olts.update', $olt) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nodo *</label>
                <select name="nodo_id" id="nodo_id" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ old('nodo_id', $olt->nodo_id) == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                @error('nodo_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="olt_marca_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Marca *</label>
                    <select name="olt_marca_id" id="olt_marca_id" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach($marcas as $m)
                            <option value="{{ $m->olt_marca_id }}" {{ old('olt_marca_id', $olt->olt_marca_id) == $m->olt_marca_id ? 'selected' : '' }}>{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                    @error('olt_marca_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="modelo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo</label>
                    <input type="text" name="modelo" id="modelo" value="{{ old('modelo', $olt->modelo) }}" maxlength="100"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300">IP de gestión</label>
                    <input type="text" name="ip" id="ip" value="{{ old('ip', $olt->ip) }}" maxlength="45"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('ip')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="tipo_pon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo PON *</label>
                    <select name="tipo_pon" id="tipo_pon" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="GPON" {{ old('tipo_pon', $olt->tipo_pon) === 'GPON' ? 'selected' : '' }}>GPON</option>
                        <option value="EPON" {{ old('tipo_pon', $olt->tipo_pon) === 'EPON' ? 'selected' : '' }}>EPON</option>
                        <option value="XG-PON" {{ old('tipo_pon', $olt->tipo_pon) === 'XG-PON' ? 'selected' : '' }}>XG-PON</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="cantidad_puertos" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cantidad de puertos</label>
                    <input type="number" name="cantidad_puertos" id="cantidad_puertos" value="{{ old('cantidad_puertos', $olt->cantidad_puertos) }}" min="1" max="128"
                        class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                    <select name="estado" id="estado" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="activo" {{ old('estado', $olt->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ old('estado', $olt->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
                <textarea name="notas" id="notas" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('notas', $olt->notas) }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar cambios</button>
            <a href="{{ route('sistema.olts.show', $olt) }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
