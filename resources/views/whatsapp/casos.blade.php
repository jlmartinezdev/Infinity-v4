@extends('layouts.app')

@section('title', 'WhatsApp - Casos')

@section('content')
@php
    $etiquetas = ['saludo', 'cierre', 'cobertura', 'cobranza', 'soporte', 'general'];
    $campo = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900';
    $activos = $casos->where('activo', true);
    $inactivos = $casos->where('activo', false);
@endphp
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Frases que el agente puede usar. El trato es de usted, con Sr. o Sra.</p>

    @include('whatsapp._tabs', ['waTab' => 'casos'])

    @if(session('success'))
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</p>
    @endif

    <form method="POST" action="{{ route('whatsapp.estilo.update') }}" class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        @csrf
        @method('PUT')
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Estilo Interplus</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Así habla el agente. Las cuentas de pago no se editan acá: salen del texto fijo de transferencia. n8n recibe este estilo en cada mensaje. Un caso entra solo si alguna de sus palabras está en el mensaje del cliente, y nunca más de tres.</p>
        <label class="mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300" for="estilo">Texto de estilo</label>
        <textarea id="estilo" name="estilo" required rows="10" class="{{ $campo }} mt-1">{{ old('estilo', $estilo ?? '') }}</textarea>
        <button class="mt-3 w-fit rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white dark:bg-slate-200 dark:text-slate-900">Guardar estilo</button>
    </form>

    <form method="POST" action="{{ route('whatsapp.casos.store') }}" class="mb-8 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Nuevo caso</h2>
        <div class="mt-3 grid gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-texto">Frase al cliente</label>
                <textarea id="nuevo-texto" name="texto" required rows="4" placeholder="Qué decirle al cliente, en usted" class="{{ $campo }}"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-titulo">Título</label>
                <input id="nuevo-titulo" name="titulo" required maxlength="120" placeholder="Nombre corto del caso" class="{{ $campo }}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-etiqueta">Etiqueta</label>
                <select id="nuevo-etiqueta" name="etiqueta" class="{{ $campo }}">
                    @foreach($etiquetas as $opcion)
                        <option value="{{ $opcion }}">{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-palabras">Palabras</label>
                <input id="nuevo-palabras" name="palabras" placeholder="pago, saldo, comprobante" class="{{ $campo }}">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="activo" value="1" checked> Activo
            </label>
            <button class="w-fit rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Guardar</button>
        </div>
    </form>

    <div class="space-y-4">
        @forelse($activos as $caso)
            @include('whatsapp._caso', ['caso' => $caso, 'etiquetas' => $etiquetas, 'campo' => $campo])
        @empty
            <p class="text-sm text-gray-500">No hay casos activos.</p>
        @endforelse
    </div>

    @if($inactivos->isNotEmpty())
        <details class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">Inactivos ({{ $inactivos->count() }})</summary>
            <div class="space-y-4 border-t border-gray-200 p-4 dark:border-gray-700">
                @foreach($inactivos as $caso)
                    @include('whatsapp._caso', ['caso' => $caso, 'etiquetas' => $etiquetas, 'campo' => $campo])
                @endforeach
            </div>
        </details>
    @endif
</div>
@endsection
