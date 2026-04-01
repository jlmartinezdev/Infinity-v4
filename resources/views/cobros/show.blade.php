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
        @if(auth()->user()?->tienePermiso('cobros.eliminar'))
            <form action="{{ route('cobros.destroy', $cobro) }}" method="POST" class="ml-auto inline" onsubmit="return confirm('¿Eliminar este cobro? Se revertirá el estado de la factura asociada.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1.5 text-red-600 dark:text-red-400 font-medium text-sm hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg">Eliminar</button>
            </form>
        @endif
    </div>

    <div id="recibo-contenido">
        @include('cobros._recibo-contenido', ['esMulticobro' => false, 'pdfUrl' => route('cobros.recibo-pdf', $cobro)])
    </div>
</div>

@push('scripts')
<script src="{{ asset(mix('js/recibo-copy-image.js')) }}" defer></script>
<script>
(function() {
    var STORAGE_KEY = 'reciboPapelMm';
    var el = document.getElementById('recibo-contenido');
    if (!el) return;
    try {
        var mm = localStorage.getItem(STORAGE_KEY);
        if (mm !== '56' && mm !== '80') mm = '80';
        el.style.maxWidth = mm + 'mm';
        el.style.marginLeft = 'auto';
        el.style.marginRight = 'auto';
    } catch (e) {}
})();
</script>
@endpush

<style>
@media print {
    @page {
        margin: 4mm;
        size: 80mm auto;
    }
    #recibo-print {
        max-width: none !important;
    }
    #recibo-contenido {
        max-width: 80mm !important;
    }
    .recibo-termico {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0.5rem !important;
        background: #fff !important;
        -webkit-print-color-adjust: economy;
        print-color-adjust: economy;
    }
    /* Solo negro al imprimir (incluye modo oscuro y grises Tailwind) */
    #recibo-print .recibo-termico,
    #recibo-print .recibo-termico * {
        color: #000 !important;
    }
    #recibo-print .recibo-termico svg {
        stroke: #000 !important;
        color: #000 !important;
    }
}
</style>
@endsection
