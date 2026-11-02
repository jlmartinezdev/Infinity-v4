@extends('layouts.app')

@section('title', 'WhatsApp - Plantillas')

@section('content')
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Plantillas de texto que el soporte puede usar. Sin decibeles, IP ni velocidades: eso queda para el técnico.</p>

    @include('whatsapp._tabs', ['waTab' => 'casos'])

    @if(session('success'))
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</p>
    @endif

    <form method="POST" action="{{ route('whatsapp.casos.store') }}" class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Nuevo caso</h2>
        <div class="mt-3 grid gap-3">
            <input name="titulo" required placeholder="Título" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
            <input name="etiqueta" placeholder="Etiqueta: cobranza, cable, sitio, senal, zona" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
            <input name="palabras" placeholder="Palabras, separadas por coma" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
            <textarea name="texto" required rows="3" placeholder="Qué decirle al cliente, en simple" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="activo" value="1" checked> Activo
            </label>
            <button class="w-fit rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Guardar</button>
        </div>
    </form>

    <div class="space-y-3">
        @forelse($casos as $caso)
            <form method="POST" action="{{ route('whatsapp.casos.update', $caso) }}" class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                @csrf
                @method('PUT')
                <div class="grid gap-2">
                    <input name="titulo" value="{{ $caso->titulo }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <input name="etiqueta" value="{{ $caso->etiqueta }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <input name="palabras" value="{{ $caso->palabras }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <textarea name="texto" rows="3" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">{{ $caso->texto }}</textarea>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="activo" value="1" @checked($caso->activo)> Activo
                    </label>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm text-white">Actualizar</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="{{ route('whatsapp.casos.destroy', $caso) }}" class="-mt-2 mb-2">
                @csrf
                @method('DELETE')
                <button class="text-xs text-rose-600">Eliminar</button>
            </form>
        @empty
            <p class="text-sm text-gray-500">Todavía no hay casos.</p>
        @endforelse
    </div>
</div>
@endsection
