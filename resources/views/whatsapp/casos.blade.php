@extends('layouts.app')

@section('title', 'WhatsApp - Casos')

@section('content')
@php
    $etiquetas = ['saludo', 'cierre', 'cobertura', 'cobranza', 'soporte', 'tv', 'falla', 'zona', 'general'];
    $campo = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900';
    $activos = $casos->where('activo', true)->values();
    $inactivos = $casos->where('activo', false)->values();
@endphp
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Conocimiento del agente (LangGraph). Estilo + casos con palabras clave. La corrección del día a día es Enviar/Descartar en el chat.
    </p>

    @include('whatsapp._tabs', ['waTab' => 'casos'])

    @if(session('success'))
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</p>
    @endif

    <details class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Estilo Interplus</summary>
        <form method="POST" action="{{ route('whatsapp.estilo.update') }}" class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-700">
            @csrf
            @method('PUT')
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Cómo debe hablar el agente. Se inyecta en cada turno. Las cuentas de pago salen del texto fijo de transferencia, no de acá.
            </p>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="estilo">Texto de estilo</label>
            <textarea id="estilo" name="estilo" required rows="8" class="{{ $campo }}">{{ old('estilo', $estilo ?? '') }}</textarea>
            <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white dark:bg-slate-200 dark:text-slate-900">Guardar estilo</button>
        </form>
    </details>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Casos activos ({{ $activos->count() }})</h2>
        <button type="button" id="wa-caso-nuevo-btn"
            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white">
            Nuevo caso
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Título</th>
                    <th class="px-4 py-3 font-medium">Etiqueta</th>
                    <th class="px-4 py-3 font-medium">Usos</th>
                    <th class="px-4 py-3 font-medium text-right"> </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($activos as $caso)
                    @php
                        $prohibido = \App\Services\WhatsApp\Soporte\EstiloInterplus::textoProhibido(
                            trim($caso->titulo.' '.$caso->texto.' '.$caso->palabras)
                        );
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                        <td class="px-4 py-3">
                            <button type="button"
                                class="wa-caso-abrir text-left font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                data-caso-id="{{ $caso->id }}">
                                {{ $caso->titulo }}
                            </button>
                            @if($prohibido)
                                <span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-100">bloqueado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $caso->etiqueta ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ (int) ($caso->usos_exitosos ?? 0) }} / {{ (int) ($caso->usos_descartes ?? 0) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="wa-caso-abrir text-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200" data-caso-id="{{ $caso->id }}">Abrir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">No hay casos activos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($inactivos->isNotEmpty())
        <details class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">Inactivos ({{ $inactivos->count() }})</summary>
            <table class="min-w-full border-t border-gray-200 text-left text-sm dark:border-gray-700">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($inactivos as $caso)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <td class="px-4 py-3">
                                <button type="button" class="wa-caso-abrir text-left font-medium text-gray-600 hover:underline dark:text-gray-300" data-caso-id="{{ $caso->id }}">
                                    {{ $caso->titulo }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $caso->etiqueta ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="wa-caso-abrir text-xs text-gray-500" data-caso-id="{{ $caso->id }}">Abrir</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>

{{-- Modal nuevo --}}
<div id="wa-caso-modal-nuevo" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-800">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Nuevo caso</h3>
            <button type="button" class="wa-caso-cerrar text-gray-400 hover:text-gray-700" data-cerrar="wa-caso-modal-nuevo" aria-label="Cerrar">✕</button>
        </div>
        <form method="POST" action="{{ route('whatsapp.casos.store') }}" class="grid gap-3">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-titulo">Título</label>
                <input id="nuevo-titulo" name="titulo" required maxlength="120" placeholder="Nombre corto" class="{{ $campo }}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-texto">Frase al cliente</label>
                <textarea id="nuevo-texto" name="texto" required rows="5" placeholder="Qué decirle, en usted" class="{{ $campo }}"></textarea>
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
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="nuevo-palabras">Palabras clave</label>
                <input id="nuevo-palabras" name="palabras" placeholder="tv, cable, streaming" class="{{ $campo }}">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="activo" value="1" checked> Activo
            </label>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                <button type="button" class="wa-caso-cerrar rounded-lg px-3 py-1.5 text-sm text-gray-600" data-cerrar="wa-caso-modal-nuevo">Cancelar</button>
                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modales editar (uno por caso; solo títulos en tabla) --}}
@foreach($casos as $caso)
    @php
        $opciones = $etiquetas;
        $actual = (string) ($caso->etiqueta ?? '');
        if ($actual !== '' && ! in_array($actual, $opciones, true)) {
            $opciones[] = $actual;
        }
        $prohibido = \App\Services\WhatsApp\Soporte\EstiloInterplus::textoProhibido(
            trim($caso->titulo.' '.$caso->texto.' '.$caso->palabras)
        );
    @endphp
    <div id="wa-caso-modal-{{ $caso->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-800">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $caso->titulo }}</h3>
                    <p class="text-xs text-gray-500">Usos OK {{ (int) ($caso->usos_exitosos ?? 0) }} · Descartes {{ (int) ($caso->usos_descartes ?? 0) }}</p>
                </div>
                <button type="button" class="wa-caso-cerrar text-gray-400 hover:text-gray-700" data-cerrar="wa-caso-modal-{{ $caso->id }}" aria-label="Cerrar">✕</button>
            </div>
            @if($prohibido)
                <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                    No se manda al agente: tiene Ueno, número de cuenta o voseo.
                </p>
            @endif
            <form id="borrar-caso-{{ $caso->id }}" method="POST" action="{{ route('whatsapp.casos.destroy', $caso) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
            <form method="POST" action="{{ route('whatsapp.casos.update', $caso) }}" class="grid gap-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="titulo-{{ $caso->id }}">Título</label>
                    <input id="titulo-{{ $caso->id }}" name="titulo" required maxlength="120" value="{{ $caso->titulo }}" class="{{ $campo }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="texto-{{ $caso->id }}">Frase al cliente</label>
                    <textarea id="texto-{{ $caso->id }}" name="texto" required rows="5" class="{{ $campo }}">{{ $caso->texto }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="etiqueta-{{ $caso->id }}">Etiqueta</label>
                    <select id="etiqueta-{{ $caso->id }}" name="etiqueta" class="{{ $campo }}">
                        @foreach($opciones as $opcion)
                            <option value="{{ $opcion }}" @selected($actual === $opcion)>{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="palabras-{{ $caso->id }}">Palabras clave</label>
                    <input id="palabras-{{ $caso->id }}" name="palabras" value="{{ $caso->palabras }}" class="{{ $campo }}">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" name="activo" value="1" @checked($caso->activo)> Activo
                </label>
                <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                    <button type="submit" form="borrar-caso-{{ $caso->id }}" class="text-sm text-rose-600" onclick="return confirm('¿Eliminar este caso?')">Eliminar</button>
                    <div class="flex gap-2">
                        <button type="button" class="wa-caso-cerrar rounded-lg px-3 py-1.5 text-sm text-gray-600" data-cerrar="wa-caso-modal-{{ $caso->id }}">Cerrar</button>
                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endforeach

@push('scripts')
<script>
(function () {
    function abrir(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
    function cerrar(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
        if (!document.querySelector('[id^="wa-caso-modal-"]:not(.hidden)')) {
            document.body.classList.remove('overflow-hidden');
        }
    }
    document.getElementById('wa-caso-nuevo-btn')?.addEventListener('click', function () {
        abrir('wa-caso-modal-nuevo');
    });
    document.querySelectorAll('.wa-caso-abrir').forEach(function (btn) {
        btn.addEventListener('click', function () {
            abrir('wa-caso-modal-' + btn.getAttribute('data-caso-id'));
        });
    });
    document.querySelectorAll('.wa-caso-cerrar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            cerrar(btn.getAttribute('data-cerrar'));
        });
    });
    document.querySelectorAll('[id^="wa-caso-modal-"]').forEach(function (modal) {
        modal.addEventListener('click', function (ev) {
            if (ev.target === modal) cerrar(modal.id);
        });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('[id^="wa-caso-modal-"]').forEach(function (modal) {
            if (!modal.classList.contains('hidden')) cerrar(modal.id);
        });
    });
})();
</script>
@endpush
@endsection
