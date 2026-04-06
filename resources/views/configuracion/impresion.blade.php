@extends('layouts.app')

@section('title', 'Configuración - Impresión')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Impresión de recibos</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Formato del recibo (este equipo)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Define cómo se ve el recibo en pantalla e impresión. Se guarda en <strong>este navegador</strong> (localStorage). El enlace «Descargar PDF» usará el modo elegido.</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="recibo_modo_ui" value="con_grafico" class="js-recibo-modo mt-1 rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
                    <span>
                        <span class="block font-medium text-gray-900 dark:text-gray-100">Con gráfico (térmica / PDF con logo)</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Muestra el logo si está configurado en Ajustes generales; estilo habitual para POS.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="recibo_modo_ui" value="sin_grafico" class="js-recibo-modo mt-1 rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
                    <span>
                        <span class="block font-medium text-gray-900 dark:text-gray-100">Sin gráfico — matricial (caja / líneas)</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Sin logo; encabezado en texto, bordes y secciones. Texto sin acentos para impresoras matriciales.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="recibo_modo_ui" value="sin_grafico_linea" class="js-recibo-modo mt-1 rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
                    <span>
                        <span class="block font-medium text-gray-900 dark:text-gray-100">Sin gráfico — línea simple</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Una línea por dato, sin cajas; solo texto monoespaciado. Texto sin acentos.</span>
                    </span>
                </label>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Se guarda al elegir la opción. En otro navegador u ordenador puede elegir otro modo.</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Ancho de papel (recibos)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Elija 56 mm o 80 mm según su impresora térmica. La opción se guarda en <strong>este navegador</strong> (localStorage). El PDF descargado usa ancho 80 mm en el servidor.</p>
        </div>

        <div class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Tamaño de hoja</label>
                <div class="flex flex-wrap gap-6">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="recibo_papel_mm_ui" value="80" class="js-tamano-papel rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
                        <span class="ml-2 text-gray-800 dark:text-gray-200 font-medium">80 mm</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="recibo_papel_mm_ui" value="56" class="js-tamano-papel rounded-full border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500">
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
                            <p>Se guarda al elegir la opción (este equipo).</p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">Si usa otro navegador u ordenador, deberá seleccionar el ancho de nuevo.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var STORAGE_KEY = 'reciboModo';
    var radios = document.querySelectorAll('.js-recibo-modo');
    function leerModo() {
        try {
            var v = localStorage.getItem(STORAGE_KEY);
            if (v === 'sin_grafico' || v === 'sin_grafico_linea') return v;
            return 'con_grafico';
        } catch (e) {
            return 'con_grafico';
        }
    }
    function guardarModo(m) {
        try {
            localStorage.setItem(STORAGE_KEY, m);
        } catch (e) {}
    }
    function aplicarModo(m) {
        guardarModo(m);
        radios.forEach(function(r) {
            r.checked = r.value === m;
        });
    }
    radios.forEach(function(r) {
        r.addEventListener('change', function() {
            aplicarModo(this.value);
        });
    });
    aplicarModo(leerModo());
})();
(function() {
    var STORAGE_KEY = 'reciboPapelMm';
    var DEFAULT_MM = 80;
    var radios = document.querySelectorAll('.js-tamano-papel');
    var preview = document.getElementById('recibo-preview');
    var previewAncho = document.getElementById('preview-ancho');

    function leerMm() {
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
        if (preview) preview.style.width = mm + 'mm';
        if (previewAncho) previewAncho.textContent = mm;
        radios.forEach(function(r) {
            r.checked = r.value === String(mm);
        });
    }

    function aplicar(mm) {
        guardar(mm);
        actualizarPreview(mm);
    }

    radios.forEach(function(r) {
        r.addEventListener('change', function() {
            aplicar(parseInt(this.value, 10));
        });
    });

    aplicar(leerMm());
})();
</script>
@endpush
@endsection
