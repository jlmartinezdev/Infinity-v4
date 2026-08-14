@extends('layouts.app')

@section('title', 'Recibo ' . $cobro->numero_recibo)

@section('content')
<div class="max-w-2xl mx-auto" id="recibo-print">
    <div class="mb-4 flex flex-wrap gap-2 print:hidden">
        <a href="{{ route('cobros.index') }}" class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 text-sm font-medium">&larr; Volver a cobros</a>
        <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
            Imprimir recibo
        </button>
        <button type="button" data-copy-recibo-image data-target="#recibo-contenido" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 dark:bg-green-900/25 border border-green-200 dark:border-green-800 rounded-lg text-sm font-medium text-green-800 dark:text-green-200 hover:bg-green-100 dark:hover:bg-green-900/40">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Copiar imagen
        </button>
        @if(config('whatsapp.enabled'))
            <button type="button" id="btn-wa-recibo"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                Enviar por WhatsApp
            </button>
        @endif
        @if(auth()->user()?->tienePermiso('cobros.eliminar'))
            <form action="{{ route('cobros.destroy', $cobro) }}" method="POST" class="ml-auto inline" onsubmit="return confirm('¿Eliminar este cobro? Se revertirá el estado de la factura asociada.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1.5 text-red-600 dark:text-red-400 font-medium text-sm hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg">Eliminar</button>
            </form>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 print:hidden rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 print:hidden rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div id="recibo-contenido">
        @include('cobros._recibo-contenido', ['esMulticobro' => false, 'pdfUrl' => route('cobros.recibo-pdf', $cobro)])
    </div>
</div>

@php
    $telRegistrado = trim((string) ($cobro->cliente?->telefono ?? ''));
@endphp
@if(config('whatsapp.enabled'))
<div id="modal-wa-recibo" class="fixed inset-0 z-50 hidden print:hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" data-wa-cerrar></div>
    <div class="relative mx-auto mt-20 w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Enviar recibo por WhatsApp</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Elegí a qué número enviar el aviso de pago.</p>

        <form action="{{ route('cobros.enviar-whatsapp', $cobro) }}" method="POST" id="form-wa-recibo" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="guardar_telefono" id="wa-guardar-telefono" value="0">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-600 {{ $telRegistrado === '' ? 'opacity-50' : '' }}">
                <input type="radio" name="destino" value="registrado" class="mt-1 text-emerald-600 focus:ring-emerald-500"
                       {{ $telRegistrado !== '' ? 'checked' : 'disabled' }}>
                <span>
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Número registrado</span>
                    <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">
                        {{ $telRegistrado !== '' ? $telRegistrado : 'Sin teléfono en el cliente' }}
                    </span>
                </span>
            </label>

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-600">
                <input type="radio" name="destino" value="otro" class="mt-1 text-emerald-600 focus:ring-emerald-500"
                       {{ $telRegistrado === '' ? 'checked' : '' }}>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Otro número</span>
                    <input type="text" name="telefono" id="wa-telefono-otro" maxlength="40"
                           placeholder="Ej: 0981 123 456"
                           class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                           {{ $telRegistrado === '' ? 'required' : 'disabled' }}>
                </span>
            </label>

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-wa-cerrar
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                    Cancelar
                </button>
                <button type="submit" id="btn-wa-recibo-enviar"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-wait disabled:opacity-70">
                    Enviar
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script src="{{ asset(mix('js/recibo-modo-local.js')) }}" defer></script>
<script src="{{ asset(mix('js/recibo-copy-image.js')) }}" defer></script>
<script>
(function() {
    var STORAGE_KEY = 'reciboPapelMm';
    function leerMm() {
        try {
            var v = localStorage.getItem(STORAGE_KEY);
            return v === '56' ? '56' : '80';
        } catch (e) {
            return '80';
        }
    }
    function aplicar() {
        var mm = leerMm();
        var el = document.getElementById('recibo-contenido');
        if (!el) return;
        el.style.maxWidth = mm + 'mm';
        el.style.marginLeft = 'auto';
        el.style.marginRight = 'auto';
        var sid = 'recibo-papel-print-css';
        var st = document.getElementById(sid);
        if (!st) {
            st = document.createElement('style');
            st.id = sid;
            document.head.appendChild(st);
        }
        st.textContent = '@media print { @page { margin: 4mm; size: ' + mm + 'mm auto; } #recibo-contenido { max-width: ' + mm + 'mm !important; } }';
    }
    aplicar();
    window.addEventListener('storage', function(e) {
        if (e.key === STORAGE_KEY) aplicar();
    });
})();

(function() {
    var modal = document.getElementById('modal-wa-recibo');
    var btn = document.getElementById('btn-wa-recibo');
    if (!modal || !btn) return;

    var inputOtro = document.getElementById('wa-telefono-otro');
    var radios = modal.querySelectorAll('input[name="destino"]');

    function syncDestino() {
        var destino = (modal.querySelector('input[name="destino"]:checked') || {}).value;
        var esOtro = destino === 'otro';
        if (inputOtro) {
            inputOtro.disabled = !esOtro;
            inputOtro.required = esOtro;
            if (esOtro) inputOtro.focus();
        }
    }

    function abrir() {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        syncDestino();
    }
    function cerrar() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }

    var form = document.getElementById('form-wa-recibo');
    var btnEnviar = document.getElementById('btn-wa-recibo-enviar');
    var guardarInput = document.getElementById('wa-guardar-telefono');
    var telRegistrado = @json($telRegistrado);
    var enviando = false;

    function marcarEnviando() {
        enviando = true;
        if (btnEnviar) {
            btnEnviar.disabled = true;
            btnEnviar.textContent = 'Enviando…';
        }
        modal.querySelectorAll('[data-wa-cerrar]').forEach(function(el) {
            el.disabled = true;
            el.classList.add('pointer-events-none', 'opacity-50');
        });
    }

    function digitos(s) {
        return String(s || '').replace(/\D/g, '');
    }

    btn.addEventListener('click', abrir);
    modal.querySelectorAll('[data-wa-cerrar]').forEach(function(el) {
        el.addEventListener('click', function() {
            if (!enviando) cerrar();
        });
    });
    radios.forEach(function(r) {
        r.addEventListener('change', syncDestino);
    });
    if (form) {
        form.addEventListener('submit', function(e) {
            if (enviando) {
                e.preventDefault();
                return;
            }
            if (guardarInput) guardarInput.value = '0';

            var destino = (modal.querySelector('input[name="destino"]:checked') || {}).value;
            if (destino === 'otro' && inputOtro) {
                var tel = (inputOtro.value || '').trim();
                if (tel !== '') {
                    var distinto = digitos(tel) !== digitos(telRegistrado);
                    if (distinto) {
                        var msg = telRegistrado
                            ? '¿Guardar este número (' + tel + ') como teléfono del cliente?\n\nReemplaza el actual: ' + telRegistrado
                            : '¿Guardar este número (' + tel + ') como teléfono del cliente?';
                        if (confirm(msg)) {
                            if (guardarInput) guardarInput.value = '1';
                        }
                    }
                }
            }

            marcarEnviando();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden') && !enviando) cerrar();
    });
})();
</script>
@endpush

<style>
@media print {
    #recibo-print {
        max-width: none !important;
    }
    .recibo-termico,
    #recibo-print .recibo-bloque-linea-simple {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0.5rem !important;
        background: #fff !important;
        -webkit-print-color-adjust: economy;
        print-color-adjust: economy;
    }
    #recibo-print .recibo-matricial,
    #recibo-print .recibo-modo-wrapper[data-recibo-modo="sin_grafico"] > .recibo-bloque-estandar > .recibo-termico,
    #recibo-print .recibo-bloque-linea-simple {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
    }
    #recibo-print .recibo-modo-wrapper[data-recibo-modo="sin_grafico"] > .recibo-bloque-estandar > .recibo-termico {
        border: 1px dashed #000 !important;
    }
    /* Solo negro al imprimir (incluye modo oscuro y grises Tailwind) */
    #recibo-print .recibo-termico,
    #recibo-print .recibo-termico *,
    #recibo-print .recibo-bloque-linea-simple,
    #recibo-print .recibo-bloque-linea-simple * {
        color: #000 !important;
    }
    #recibo-print .recibo-termico svg {
        stroke: #000 !important;
        color: #000 !important;
    }
}
</style>
@endsection
