@extends('layouts.app')

@section('title', ($asunto ? 'Editar' : 'Nuevo').' asunto WhatsApp')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            {{ $asunto ? 'Editar asunto' : 'Nuevo asunto' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Clasificación de conversaciones WhatsApp</p>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'asuntos'])

    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="POST"
              action="{{ $asunto ? route('whatsapp.asuntos.update', $asunto) : route('whatsapp.asuntos.store') }}">
            @csrf
            @if($asunto)
                @method('PUT')
            @endif

            <div class="space-y-5">
                <div>
                    <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre *</label>
                    <input type="text" name="nombre" id="nombre" required maxlength="120"
                           value="{{ old('nombre', $asunto?->nombre) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                           placeholder="Ej: Soporte técnico">
                    @error('nombre')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="color" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" id="color"
                               value="{{ old('color', $asunto?->color ?? '#10b981') }}"
                               class="h-10 w-14 cursor-pointer rounded border border-gray-300 dark:border-gray-600">
                        <span class="text-xs text-gray-500">Se usa como etiqueta en el chat</span>
                    </div>
                    @error('color')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="orden" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Orden</label>
                    <input type="number" name="orden" id="orden" min="0" max="9999"
                           value="{{ old('orden', $asunto?->orden ?? 100) }}"
                           class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-emerald-600"
                           {{ old('activo', $asunto?->activo ?? true) ? 'checked' : '' }}>
                    Activo (visible al asignar en conversaciones)
                </label>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                        {{ $asunto ? 'Guardar' : 'Crear' }}
                    </button>
                    <a href="{{ route('whatsapp.asuntos.index') }}"
                       class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                        Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
