@extends('layouts.app')

@section('title', 'Editar scheduler '.$scheduler->nombre)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.router-schedulers.index') }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">← Schedulers MikroTik</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100 font-mono">{{ $scheduler->nombre }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Origen: {{ $scheduler->routerOrigen?->nombre ?? '—' }}
            @if($scheduler->routerOrigen)
                ({{ $scheduler->routerOrigen->ip }})
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('sistema.router-schedulers.update', $scheduler) }}"
        class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $scheduler->nombre) }}" required maxlength="128"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono">
            @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Interval</label>
                <input type="text" name="interval" value="{{ old('interval', $scheduler->interval) }}" maxlength="64"
                    placeholder="1d, 1h, 00:05:00..."
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start date</label>
                <input type="text" name="start_date" value="{{ old('start_date', $scheduler->start_date) }}" maxlength="32"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start time</label>
                <input type="text" name="start_time" value="{{ old('start_time', $scheduler->start_time) }}" maxlength="32"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Owner</label>
                <input type="text" name="owner" value="{{ old('owner', $scheduler->owner) }}" maxlength="64"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Policy</label>
                <input type="text" name="policy" value="{{ old('policy', $scheduler->policy) }}" maxlength="255"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
        </div>

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="disabled" value="1" class="rounded border-gray-300"
                    {{ old('disabled', $scheduler->disabled) ? 'checked' : '' }}>
                Disabled
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comment</label>
            <input type="text" name="comment" value="{{ old('comment', $scheduler->comment) }}" maxlength="255"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (Infinity)</label>
            <input type="text" name="notas" value="{{ old('notas', $scheduler->notas) }}" maxlength="255"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">On-event</label>
            <textarea name="on_event" rows="12"
                class="w-full font-mono text-xs sm:text-sm px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">{{ old('on_event', $scheduler->on_event) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Nombre de script o código RouterOS a ejecutar.</p>
            @error('on_event') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-2 pt-2">
            <a href="{{ route('sistema.router-schedulers.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Cancelar</a>
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
@endsection
