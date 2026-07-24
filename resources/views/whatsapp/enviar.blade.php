@extends('layouts.app')

@section('title', 'WhatsApp · Enviar')

@section('content')
@php
    $aprobadas = $plantillasAprobadas ?? [];
    $pendientes = $plantillasPendientes ?? [];
@endphp
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Envío manual (texto libre o plantilla)</p>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'enviar'])

    @if(! $configured)
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-200">
            WhatsApp no está configurado. Revisá WHATSAPP_ENABLED / TOKEN / PHONE_NUMBER_ID.
        </div>
    @endif

    @if(count($aprobadas) === 0)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
            <p class="font-semibold">No hay plantillas APPROVED en Meta.</p>
            <p class="mt-1">Fuera de la ventana de 24 h (cuando el cliente no te escribió), el texto libre falla con error <code class="font-mono">131047</code>.
                Las plantillas actuales están en <strong>PENDING</strong> y Meta las rechaza al enviar (<code class="font-mono">132001</code>).</p>
            <p class="mt-1">Esperá la aprobación en
                <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener" class="underline">Meta Business → Plantillas</a>
                o pedí revisión de las pendientes.
            </p>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="POST" action="{{ route('whatsapp.enviar.store') }}" class="space-y-4 p-4 sm:p-5" id="form-wa-enviar">
            @csrf

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Teléfono destino</label>
                <input type="text" name="telefono" value="{{ old('telefono', $telefonoPrefill ?? '') }}" required maxlength="40"
                       placeholder="0981… o +595981…"
                       class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-emerald-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Modo</label>
                <div class="flex flex-wrap gap-3 pt-1">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="modo" value="texto" class="js-wa-modo text-emerald-600" @checked(old('modo', count($aprobadas) ? 'texto' : 'plantilla') === 'texto')>
                        Texto libre <span class="text-xs text-gray-400">(solo si hay ventana 24h)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="modo" value="plantilla" class="js-wa-modo text-emerald-600" @checked(old('modo', count($aprobadas) ? 'texto' : 'plantilla') === 'plantilla')>
                        Plantilla <span class="text-xs text-gray-400">(fuera de 24h)</span>
                    </label>
                </div>
            </div>

            <div id="wa-bloque-texto">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mensaje</label>
                <textarea name="texto" rows="4" maxlength="4000"
                          class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-emerald-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600"
                          placeholder="Hola…">{{ old('texto') }}</textarea>
                <p class="mt-1 text-xs text-gray-400">Si el cliente no escribió en las últimas 24 h, Meta rechaza el texto libre.</p>
            </div>

            <div id="wa-bloque-plantilla" class="hidden space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Plantilla</label>
                    @if(count($aprobadas))
                        <select name="plantilla" class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm ring-1 ring-gray-200 dark:bg-gray-900/40 dark:ring-gray-600">
                            <option value="">Elegir plantilla aprobada…</option>
                            @foreach($aprobadas as $t)
                                <option value="{{ $t['name'] }}"
                                        data-lang="{{ $t['language'] }}"
                                        @selected(old('plantilla') === $t['name'])>
                                    {{ $t['name'] }} ({{ $t['language'] }} · {{ $t['category'] }})
                                </option>
                            @endforeach
                        </select>
                    @else
                        <p class="rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900/40 dark:ring-gray-600">
                            Sin plantillas aprobadas para elegir.
                        </p>
                        <input type="hidden" name="plantilla" value="">
                    @endif
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Idioma</label>
                    <input type="text" name="lang" id="wa-lang" value="{{ old('lang', $defaultLang) }}" maxlength="10"
                           class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm ring-1 ring-gray-200 dark:bg-gray-900/40 dark:ring-gray-600">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Parámetros (uno por línea)</label>
                    <textarea name="params" rows="3"
                              class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 font-mono text-sm ring-1 ring-gray-200 dark:bg-gray-900/40 dark:ring-gray-600"
                              placeholder="parametro 1&#10;parametro 2">{{ old('params') }}</textarea>
                </div>

                @if(count($pendientes))
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                        <p class="font-medium text-gray-600 dark:text-gray-300">Pendientes de Meta (no se pueden usar aún):</p>
                        <ul class="mt-1 list-inside list-disc font-mono">
                            @foreach($pendientes as $t)
                                <li>{{ $t['name'] }} · {{ $t['status'] }} · {{ $t['language'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="flex justify-end border-t border-gray-100 pt-4 dark:border-gray-700/80">
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        @disabled(! $configured || (count($aprobadas) === 0 && old('modo') === 'plantilla'))>
                    Enviar WhatsApp
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('form-wa-enviar');
    if (!form) return;
    var bloqueTexto = document.getElementById('wa-bloque-texto');
    var bloquePlantilla = document.getElementById('wa-bloque-plantilla');
    var lang = document.getElementById('wa-lang');
    function sync() {
        var modo = (form.querySelector('input[name="modo"]:checked') || {}).value || 'texto';
        var esPlantilla = modo === 'plantilla';
        bloqueTexto.classList.toggle('hidden', esPlantilla);
        bloquePlantilla.classList.toggle('hidden', !esPlantilla);
    }
    form.querySelectorAll('.js-wa-modo').forEach(function (el) {
        el.addEventListener('change', sync);
    });
    var sel = form.querySelector('select[name="plantilla"]');
    if (sel && lang) {
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            if (opt && opt.dataset.lang) lang.value = opt.dataset.lang;
        });
    }
    sync();
})();
</script>
@endpush
@endsection
