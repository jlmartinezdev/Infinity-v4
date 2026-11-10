{{-- Popup global y conexión del chat interno --}}
<div id="chat-interno-popup" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4 print:hidden" role="dialog" aria-modal="true" aria-labelledby="chat-popup-heading">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">Acción requerida</p>
        <h2 id="chat-popup-heading" class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100" data-titulo></h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><span data-autor></span> te pidió una acción.</p>
        <p class="mt-3 whitespace-pre-wrap rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-100" data-cuerpo></p>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <button type="button" id="chat-popup-cerrar" class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Cerrar</button>
            <button type="button" id="chat-popup-atender" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-600">Marcar atendido</button>
            <button type="button" id="chat-popup-abrir" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Abrir chat</button>
        </div>
    </div>
</div>
@php
    $reverb = config('broadcasting.connections.reverb', []);
    $opciones = $reverb['options'] ?? [];
@endphp
<script>
window.InfinityChat = {
    usuarioId: {{ (int) auth()->user()->usuario_id }},
    csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
    pendientesUrl: @json(route('chat.pendientes')),
    atenderUrl: @json(url('/chat/mensajes')),
    chatUrl: @json(route('chat.index')),
    key: @json($reverb['key'] ?? null),
    host: @json(env('REVERB_CLIENT_HOST') ?: null),
    port: {{ (int) env('REVERB_CLIENT_PORT', 0) }},
    scheme: @json(env('REVERB_CLIENT_SCHEME') ?: null),
};
</script>
<script src="{{ asset(mix('js/chat-echo.js')) }}"></script>
