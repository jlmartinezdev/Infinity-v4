@extends('layouts.app')

@section('title', 'Chat interno')

@section('content')
<div id="chat-interno" class="mx-auto flex h-[calc(100vh-8rem)] min-h-0 max-w-[1400px] flex-col">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Chat interno</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Mensajes del personal, archivos y pedidos de acción</p>
        </div>
        <div class="flex gap-2">
            <button type="button" id="chat-btn-directo" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700">Mensaje directo</button>
            <button type="button" id="chat-btn-grupo" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Nuevo grupo</button>
        </div>
    </div>

    <div class="flex min-h-0 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <aside id="chat-aside" class="flex w-full flex-col border-gray-200 dark:border-gray-700 md:w-80 md:border-r">
            <div id="chat-lista" class="flex-1 overflow-y-auto" role="list"></div>
        </aside>

        <section id="chat-panel" class="hidden min-w-0 flex-1 flex-col md:flex">
            <header id="chat-hilo-head" class="hidden items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <button type="button" id="chat-volver" class="rounded-md p-1 text-gray-500 hover:bg-gray-100 md:hidden dark:hover:bg-gray-700" aria-label="Volver a la lista">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="min-w-0">
                    <h2 id="chat-hilo-titulo" class="truncate text-base font-semibold text-gray-900 dark:text-gray-100"></h2>
                    <p id="chat-hilo-sub" class="truncate text-xs text-gray-500 dark:text-gray-400"></p>
                </div>
            </header>
            <div id="chat-vacio" class="flex flex-1 items-center justify-center p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                Elige una conversación o empieza una nueva.
            </div>
            <div id="chat-hilo" class="hidden flex-1 space-y-3 overflow-y-auto bg-gray-50 p-4 dark:bg-gray-900/40"></div>
            <form id="chat-composer" class="hidden border-t border-gray-200 p-3 dark:border-gray-700">
                <div id="chat-asignados" class="mb-2 hidden flex flex-wrap gap-2"></div>
                <div id="chat-archivo-nombre" class="mb-2 hidden text-xs text-gray-600 dark:text-gray-300"></div>
                <div class="flex items-end gap-2">
                    <label class="cursor-pointer rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Adjuntar archivo">
                        <input id="chat-archivo" type="file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/*,application/pdf">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </label>
                    <textarea id="chat-texto" rows="2" maxlength="4000" placeholder="Escribe un mensaje" class="max-h-32 flex-1 resize-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Enviar</button>
                </div>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input id="chat-accion" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Requiere acción
                </label>
                <p id="chat-error" class="mt-2 hidden text-sm text-red-600 dark:text-red-400"></p>
            </form>
        </section>
    </div>
</div>

<div id="chat-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
        <h3 id="chat-modal-titulo" class="text-lg font-semibold text-gray-900 dark:text-gray-100"></h3>
        <form id="chat-modal-form" class="mt-4 space-y-3">
            <input id="chat-modal-nombre" type="text" maxlength="120" placeholder="Nombre del grupo" class="hidden w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            <input id="chat-modal-buscar" type="search" placeholder="Buscar personal" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
            <div id="chat-modal-lista" class="max-h-64 space-y-1 overflow-y-auto"></div>
            <p id="chat-modal-error" class="hidden text-sm text-red-600 dark:text-red-400"></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="chat-modal-cancelar" class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Cancelar</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Crear</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.__CHAT_INTERNO__ = @json($config);
</script>
<script src="{{ asset(mix('js/chat-interno.js')) }}"></script>
@endpush
