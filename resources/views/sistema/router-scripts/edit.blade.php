@extends('layouts.app')

@section('title', 'Editar script '.$script->nombre)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.router-scripts.index') }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">← Scripts MikroTik</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100 font-mono">{{ $script->nombre }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Origen: {{ $script->routerOrigen?->nombre ?? '—' }}
            @if($script->routerOrigen)
                ({{ $script->routerOrigen->ip }})
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('sistema.router-scripts.update', $script) }}"
        class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $script->nombre) }}" required maxlength="128"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono">
            @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Owner</label>
                <input type="text" name="owner" value="{{ old('owner', $script->owner) }}" maxlength="64"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Policy</label>
                <input type="text" name="policy" value="{{ old('policy', $script->policy) }}" maxlength="255"
                    placeholder="read,write,policy,test,..."
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
        </div>

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="dont_require_permissions" value="1" class="rounded border-gray-300"
                    {{ old('dont_require_permissions', $script->dont_require_permissions) ? 'checked' : '' }}>
                dont-require-permissions
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
            <input type="text" name="notas" value="{{ old('notas', $script->notas) }}" maxlength="255"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Source</label>
            <textarea name="source" rows="18" required
                class="w-full font-mono text-xs sm:text-sm px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">{{ old('source', $script->source) }}</textarea>
            @error('source') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-2 pt-2">
            <a href="{{ route('sistema.router-scripts.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Cancelar</a>
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
@endsection
