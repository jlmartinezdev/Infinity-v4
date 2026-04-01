@extends('layouts.app')

@section('title', 'Editar tarea periódica')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('tareas-periodicas.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Tareas periódicas</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar tarea periódica</h1>

    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('tareas-periodicas.update', $tareaPeriodica) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $tareaPeriodica->nombre) }}" required maxlength="255"
                   class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>

        <div>
            <label for="accion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Acción</label>
            <input type="text" name="accion" id="accion" value="{{ old('accion', $tareaPeriodica->accion) }}" required maxlength="100"
                   class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>

        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
            <select name="estado" id="estado" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="activo" {{ old('estado', $tareaPeriodica->estado) == 'activo' ? 'selected' : '' }}>Activo</option>
                <option value="pausado" {{ old('estado', $tareaPeriodica->estado) == 'pausado' ? 'selected' : '' }}>Pausado</option>
                <option value="error" {{ old('estado', $tareaPeriodica->estado) == 'error' ? 'selected' : '' }}>Error</option>
            </select>
        </div>

        <div>
            <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nodo</label>
            <select name="nodo_id" id="nodo_id" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Sin nodo (global)</option>
                @foreach($nodos as $n)
                    <option value="{{ $n->nodo_id }}" {{ old('nodo_id', $tareaPeriodica->nodo_id) == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                @endforeach
            </select>
        </div>

        <div class="pt-4 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Guardar</button>
            <a href="{{ route('tareas-periodicas.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
