@extends('layouts.app')

@section('title', 'WhatsApp · Mensajes')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Conversaciones agrupadas por número</p>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'mensajes'])

    <div id="whatsapp-mensajes-app">
        <div class="rounded-2xl border border-gray-200/80 bg-white p-10 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            Cargando chat…
        </div>
    </div>
</div>

@php
    $config = [
        'telInicial' => $telInicial,
        'buscarInicial' => $buscarInicial,
        'configured' => $configured,
        'puedeEditar' => $puedeEditar,
        'urls' => $urls,
        'flash' => $flash ?? ['success' => null, 'error' => null],
    ];
@endphp
<script>
window.__WHATSAPP_MENSAJES_CONFIG__ = @json($config);
</script>

@push('scripts')
<script src="{{ asset(mix('js/whatsapp-mensajes.js')) }}" defer></script>
@endpush
@endsection
