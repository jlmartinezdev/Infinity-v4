@extends('layouts.app')

@section('title', 'WhatsApp - Mensajes')

@push('styles')
<style>
html.wa-fullscreen,
html.wa-fullscreen body { overflow: hidden; height: 100%; }
html.wa-fullscreen #sidebar-app { display: none !important; }
html.wa-fullscreen #app-main-shell,
html.wa-fullscreen body.auth-layout #app-main-shell,
html.wa-fullscreen body.auth-layout #app-main-shell[data-sidebar="expanded"],
html.wa-fullscreen body.auth-layout #app-main-shell[data-sidebar="collapsed"] {
    margin-left: 0 !important;
    height: 100vh;
    min-height: 100vh;
}
html.wa-fullscreen #app-main-shell > header { display: none !important; }
html.wa-fullscreen #app-main-shell > main {
    padding: 0 !important;
    height: 100vh;
    overflow: hidden;
}
html.wa-fullscreen #app-main-shell > main > :not(.wa-page) { display: none !important; }
html.wa-fullscreen .wa-page-chrome { display: none !important; }
html.wa-fullscreen .wa-page { max-width: none; margin: 0; height: 100%; }
html.wa-fullscreen .wa-page-app { margin-top: 0; height: 100%; }
html.wa-fullscreen .wa-app { height: 100%; border-radius: 0; border: 0; box-shadow: none; }
</style>
<script>
    (function () {
        try {
            if (localStorage.getItem('infinity_whatsapp_fullscreen') === '1') {
                document.documentElement.classList.add('wa-fullscreen');
            }
        } catch (_) {}
    })();
</script>
@endpush

@section('content')
@include('partials.whatsapp-chat-theme')
<div class="wa-page mx-auto max-w-[1400px]">
    <div class="wa-page-chrome mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Conversaciones agrupadas por numero</p>
        </div>
    </div>

    <div class="wa-page-chrome">
        @include('whatsapp._tabs', ['waTab' => 'mensajes'])
    </div>

    <div id="whatsapp-mensajes-app" class="wa-page-app mt-3">
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