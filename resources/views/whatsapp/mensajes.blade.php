@extends('layouts.app')

@section('title', 'WhatsApp - Mensajes')

@section('content')
@include('partials.whatsapp-chat-theme')
<div class="mx-auto max-w-[1400px]">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Conversaciones agrupadas por numero</p>
        </div>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'mensajes'])

    <div id="whatsapp-mensajes-app" class="mt-3">
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 shadow-xl dark:border-[#2a3942] dark:bg-[#111b21] dark:text-[#8696a0]">
            Cargando chat...
        </div>
    </div>
</div>

@php
    $config = [
        'telInicial' => $telInicial,
        'buscarInicial' => $buscarInicial,
        'configured' => $configured,
        'puedeEditar' => $puedeEditar,
        'puedeCrearTicket' => $puedeCrearTicket ?? false,
        'puedeCrearPedido' => $puedeCrearPedido ?? false,
        'puedeCrearCobro' => $puedeCrearCobro ?? false,
        'pedidoFormConfig' => $pedidoFormConfig ?? null,
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