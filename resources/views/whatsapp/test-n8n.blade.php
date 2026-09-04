@extends('layouts.app')

@section('title', 'WhatsApp - Test n8n')

@section('content')
@include('partials.whatsapp-chat-theme')
<div class="mx-auto max-w-[1400px]">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Playground del agente n8n: no sale a WhatsApp real</p>
        </div>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'test-n8n'])

    <div id="whatsapp-test-n8n-app" class="mt-3">
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 shadow-xl dark:border-[#2a3942] dark:bg-[#111b21] dark:text-[#8696a0]">
            Cargando test n8n...
        </div>
    </div>
</div>

<script>
window.__WHATSAPP_TEST_N8N_CONFIG__ = @json([
    'telInicial' => $telInicial,
    'urls' => $urls,
]);
</script>

@push('scripts')
<script src="{{ asset(mix('js/whatsapp-test-n8n.js')) }}" defer></script>
@endpush
@endsection
