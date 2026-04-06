@extends('layouts.app')

@section('title', 'Recibos - Multicobro')

@section('content')
<div class="max-w-2xl mx-auto" id="recibo-multicobro-print">
    <div class="mb-4 flex flex-wrap gap-2 print:hidden">
        <a href="{{ route('cobros.index') }}" class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 text-sm font-medium">&larr; Volver a cobros</a>
        <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
            Imprimir todos los recibos
        </button>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif

    @php
        $clientesUnicos = $cobros->pluck('cliente_id')->unique()->count();
        $nombreCliente = $clientesUnicos === 1 && $cobros->first()?->cliente
            ? trim($cobros->first()->cliente->nombre . ' ' . $cobros->first()->cliente->apellido)
            : null;
    @endphp
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 print:hidden">
        Se registraron {{ $cobros->count() }} cobro(s)@if($nombreCliente) para {{ $nombreCliente }}@endif. Imprima esta página para obtener todos los recibos.
    </p>

    <div class="space-y-8" id="recibos-contenedor">
        @foreach($cobros as $indice => $cobro)
            <div class="space-y-2">
            <div class="print:hidden flex flex-wrap gap-2">
                <button type="button" data-copy-recibo-image data-target="#recibo-captura-{{ $cobro->id }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 dark:bg-green-900/25 border border-green-200 dark:border-green-800 rounded-lg text-sm font-medium text-green-800 dark:text-green-200 hover:bg-green-100 dark:hover:bg-green-900/40">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Copiar imagen (recibo {{ $indice + 1 }}/{{ $cobros->count() }})
                </button>
            </div>
            <div id="recibo-captura-{{ $cobro->id }}" class="recibo-wrapper {{ !$loop->last ? 'break-after-page' : '' }}">
                @include('cobros._recibo-contenido', [
                    'cobro' => $cobro,
                    'ajustes' => $ajustes,
                    'esMulticobro' => true,
                    'indice' => $indice + 1,
                    'total' => $cobros->count(),
                    'pdfUrl' => route('cobros.recibo-pdf', $cobro),
                ])
            </div>
            </div>
        @endforeach
    </div>
</div>

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
        document.querySelectorAll('.recibo-wrapper').forEach(function(el) {
            el.style.maxWidth = mm + 'mm';
            el.style.marginLeft = 'auto';
            el.style.marginRight = 'auto';
        });
        var sid = 'recibo-papel-print-css';
        var st = document.getElementById(sid);
        if (!st) {
            st = document.createElement('style');
            st.id = sid;
            document.head.appendChild(st);
        }
        st.textContent = '@media print { @page { margin: 4mm; size: ' + mm + 'mm auto; } #recibo-multicobro-print .recibo-wrapper { max-width: ' + mm + 'mm !important; } }';
    }
    aplicar();
    window.addEventListener('storage', function(e) {
        if (e.key === STORAGE_KEY) aplicar();
    });
})();
</script>
@endpush

<style>
@media print {
    #recibo-multicobro-print {
        max-width: none !important;
    }
    .recibo-termico,
    #recibo-multicobro-print .recibo-bloque-linea-simple {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0.5rem !important;
        background: #fff !important;
        -webkit-print-color-adjust: economy;
        print-color-adjust: economy;
    }
    #recibo-multicobro-print .recibo-matricial,
    #recibo-multicobro-print .recibo-modo-wrapper[data-recibo-modo="sin_grafico"] > .recibo-bloque-estandar > .recibo-termico,
    #recibo-multicobro-print .recibo-bloque-linea-simple {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
    }
    #recibo-multicobro-print .recibo-modo-wrapper[data-recibo-modo="sin_grafico"] > .recibo-bloque-estandar > .recibo-termico {
        border: 1px dashed #000 !important;
    }
    #recibo-multicobro-print .recibo-termico,
    #recibo-multicobro-print .recibo-termico *,
    #recibo-multicobro-print .recibo-bloque-linea-simple,
    #recibo-multicobro-print .recibo-bloque-linea-simple * {
        color: #000 !important;
    }
    #recibo-multicobro-print .recibo-termico svg {
        stroke: #000 !important;
        color: #000 !important;
    }
    .break-after-page { break-after: page; }
}
</style>
@endsection
