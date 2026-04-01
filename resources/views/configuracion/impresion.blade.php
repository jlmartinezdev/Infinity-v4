@extends('layouts.app')

@section('title', 'Configuración - Impresión')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Impresión de recibos</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Pos printer (papel térmico)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Elija el ancho del papel de su impresora térmica. La opción se guarda en este navegador y se usará al imprimir recibos.</p>
        </div>

        <div class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Tamaño de hoja</label>
                <div class="flex flex-wrap gap-6">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="recibo_papel_mm" value="80" class="js-tamano-papel rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
                        <span class="ml-2 text-gray-800 dark:text-gray-200 font-medium">80 mm</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="recibo_papel_mm" value="56" class="js-tamano-papel rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
                        <span class="ml-2 text-gray-800 dark:text-gray-200 font-medium">56 mm</span>
                    </label>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vista previa del tamaño del recibo</p>
                <div class="flex justify-center p-4 bg-gray-100 dark:bg-gray-700 rounded-lg">
                    <div id="recibo-preview" class="bg-white dark:bg-gray-800 border-2 border-dashed border-gray-400 dark:border-gray-600 shadow-inner rounded overflow-hidden transition-all duration-200" style="width: 80mm; min-height: 120mm;">
                        <div class="p-3 text-center text-gray-500 dark:text-gray-400 text-xs">
                            <p class="font-semibold text-gray-700 dark:text-gray-300">RECIBO</p>
                            <p class="mt-2">Ancho: <span id="preview-ancho">80</span> mm</p>
                            <p>Este es el ancho que se usará al imprimir.</p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">La configuración se guarda automáticamente en este navegador (localStorage). Si usa otro equipo o navegador, deberá seleccionar de nuevo.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var STORAGE_KEY = 'reciboPapelMm';
    var DEFAULT_MM = 80;

    var radios = document.querySelectorAll('.js-tamano-papel');
    var preview = document.getElementById('recibo-preview');
    var previewAncho = document.getElementById('preview-ancho');

    function getGuardado() {
        try {
            var v = localStorage.getItem(STORAGE_KEY);
            return v === '56' ? 56 : 80;
        } catch (e) {
            return DEFAULT_MM;
        }
    }

    function guardar(mm) {
        try {
            localStorage.setItem(STORAGE_KEY, String(mm));
        } catch (e) {}
    }

    function actualizarPreview(mm) {
        if (preview) {
            preview.style.width = mm + 'mm';
        }
        if (previewAncho) {
            previewAncho.textContent = mm;
        }
    }

    function aplicar(mm) {
        guardar(mm);
        actualizarPreview(mm);
        radios.forEach(function(r) {
            r.checked = r.value === String(mm);
        });
    }

    radios.forEach(function(r) {
        r.addEventListener('change', function() {
            aplicar(parseInt(this.value, 10));
        });
    });

    var mm = getGuardado();
    aplicar(mm);
})();
</script>
@endpush
@endsection
