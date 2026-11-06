@extends('layouts.app')

@section('title', 'WhatsApp - Memoria agente')

@section('content')
@php
    $campo = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    $activos = $memorias->where('activo', true)->values();
@endphp
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Memoria del agente</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Configuración a mano que Gemini consume en cada turno: estilo, textos fijos (cuentas/planes de voz) y documentos de instrucción.
        Al guardar, el próximo mensaje ya usa la versión nueva.
    </p>

    @include('whatsapp._tabs', ['waTab' => 'memoria'])

    @if(session('success'))
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</p>
    @endif

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('whatsapp.test-agente') }}" class="rounded-lg border border-gray-300 px-3 py-1.5 dark:border-gray-600">← Test agente</a>
        <a href="{{ route('whatsapp.casos.index') }}" class="rounded-lg border border-gray-300 px-3 py-1.5 dark:border-gray-600">Casos (ejemplos cortos)</a>
    </div>

    {{-- Estilo --}}
    <details open class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">1. Estilo de voz</summary>
        <form method="POST" action="{{ route('whatsapp.memoria.estilo') }}" class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-700">
            @csrf
            @method('PUT')
            <p class="text-xs text-gray-500">Cómo habla. No pongas números de cuenta acá.</p>
            <textarea name="estilo" required rows="8" class="{{ $campo }}">{{ old('estilo', $estilo ?? '') }}</textarea>
            <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white dark:bg-slate-200 dark:text-slate-900">Guardar estilo</button>
        </form>
    </details>

    {{-- Canned sensibles --}}
    <details open class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">2. Textos fijos (cuentas, beneficios, condiciones)</summary>
        <form method="POST" action="{{ route('whatsapp.memoria.canned') }}" class="space-y-4 border-t border-gray-200 p-4 dark:border-gray-700">
            @csrf
            @method('PUT')
            <p class="text-xs text-gray-500">Datos sensibles / canónicos. La IA los copia; no inventa. Planes de internet siguen saliendo del módulo Planes.</p>
            <div>
                <label class="mb-1 block text-xs font-medium">Transferencia / Tigo Money</label>
                <textarea name="transferencia" required rows="10" class="{{ $campo }} font-mono text-xs">{{ old('transferencia', $canned['transferencia'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium">Beneficios (upsell)</label>
                <textarea name="beneficios" required rows="6" class="{{ $campo }}">{{ old('beneficios', $canned['beneficios'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium">Condiciones del servicio</label>
                <textarea name="condiciones" required rows="10" class="{{ $campo }}">{{ old('condiciones', $canned['condiciones'] ?? '') }}</textarea>
            </div>
            <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Guardar textos fijos</button>
        </form>
    </details>

    {{-- Documentos --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">3. Documentos de instrucción ({{ $activos->count() }} activos)</h2>
    </div>

    <details class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">Importar .txt / .md</summary>
        <form method="POST" action="{{ route('whatsapp.memoria.importar') }}" enctype="multipart/form-data" class="grid gap-3 border-t border-gray-200 p-4 dark:border-gray-700 sm:grid-cols-2">
            @csrf
            <div class="sm:col-span-2">
                <input type="file" name="archivo" accept=".txt,.md,text/plain" required class="{{ $campo }}">
            </div>
            <input type="text" name="titulo" placeholder="Título (opcional)" class="{{ $campo }}">
            <select name="tipo" class="{{ $campo }}">
                @foreach($tipos as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" name="palabras" placeholder="Palabras clave: tv, netflix, canales" class="{{ $campo }} sm:col-span-2">
            <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white sm:col-span-2">Subir a memoria</button>
        </form>
    </details>

    <details class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">Nuevo documento a mano</summary>
        <form method="POST" action="{{ route('whatsapp.memoria.store') }}" class="grid gap-3 border-t border-gray-200 p-4 dark:border-gray-700">
            @csrf
            <input type="text" name="titulo" required maxlength="160" placeholder="Título" class="{{ $campo }}">
            <select name="tipo" class="{{ $campo }}">
                @foreach($tipos as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" name="palabras" placeholder="Palabras clave separadas por coma" class="{{ $campo }}">
            <textarea name="cuerpo" required rows="8" placeholder="Instrucciones que la IA debe seguir…" class="{{ $campo }}"></textarea>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" checked> Activo</label>
            <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Crear memoria</button>
        </form>
    </details>

    <div class="space-y-4">
        @forelse($memorias as $m)
            <details class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800" @if(session('borrador_ia_id') == $m->id) open @endif>
                <summary class="flex cursor-pointer flex-wrap items-center gap-2 px-4 py-3 text-sm">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $m->titulo }}</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-900">{{ $m->tipo }}</span>
                    <span class="text-xs text-gray-500">v{{ $m->version }} · {{ $m->activo ? 'activo' : 'off' }}</span>
                </summary>
                <div class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-700">
                    @if(session('borrador_ia_id') == $m->id && session('borrador_ia'))
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/30">
                            <p class="mb-2 font-medium">Borrador IA (no guardado aún)</p>
                            <pre class="whitespace-pre-wrap text-xs">{{ session('borrador_ia') }}</pre>
                            <form method="POST" action="{{ route('whatsapp.memoria.mejorar', $m) }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="aplicar" value="1">
                                <button class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white">Aplicar este borrador</button>
                            </form>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('whatsapp.memoria.update', $m) }}" class="grid gap-3">
                        @csrf
                        @method('PUT')
                        <input type="text" name="titulo" value="{{ $m->titulo }}" required class="{{ $campo }}">
                        <select name="tipo" class="{{ $campo }}">
                            @foreach($tipos as $k => $label)
                                <option value="{{ $k }}" @selected($m->tipo === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="palabras" value="{{ $m->palabras }}" class="{{ $campo }}" placeholder="palabras clave">
                        <textarea name="cuerpo" required rows="8" class="{{ $campo }}">{{ $m->cuerpo }}</textarea>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" @checked($m->activo)> Activo</label>
                        <div class="flex flex-wrap gap-2">
                            <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900">Guardar</button>
                        </div>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('whatsapp.memoria.mejorar', $m) }}">
                            @csrf
                            <button class="rounded-lg border border-violet-400 px-3 py-1.5 text-sm text-violet-700 dark:text-violet-300">Mejorar con IA (borrador)</button>
                        </form>
                        <form method="POST" action="{{ route('whatsapp.memoria.mejorar', $m) }}" onsubmit="return confirm('¿Mejorar con IA y guardar ya?');">
                            @csrf
                            <input type="hidden" name="aplicar" value="1">
                            <button class="rounded-lg border border-violet-600 bg-violet-50 px-3 py-1.5 text-sm text-violet-800 dark:bg-violet-950/40 dark:text-violet-200">Mejorar y guardar</button>
                        </form>
                        <form method="POST" action="{{ route('whatsapp.memoria.destroy', $m) }}" onsubmit="return confirm('¿Eliminar esta memoria?');">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-700">Eliminar</button>
                        </form>
                    </div>
                </div>
            </details>
        @empty
            <p class="text-sm text-gray-500">Todavía no hay documentos. Creá uno o importá un .txt con instrucciones de TV, políticas, etc.</p>
        @endforelse
    </div>
</div>
@endsection
