@extends('layouts.app')

@section('title', 'Configuración - Ajustes generales')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Ajustes generales</h1>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Datos de la empresa</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Nombre, logo, contactos y página web. Se usarán en recibos y en la aplicación.</p>
        </div>

        <form action="{{ route('configuracion.ajustes.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf

            <div>
                <label for="nombre_empresa" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de la empresa</label>
                <input type="text" name="nombre_empresa" id="nombre_empresa" value="{{ old('nombre_empresa', $ajustes->nombre_empresa ?? '') }}" maxlength="200"
                       class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
            </div>

            <div>
                <label for="logo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Logo</label>
                @if(!empty($ajustes->logo))
                    <div id="logo-guardado-wrap" class="mt-1 mb-2 flex items-center gap-3 transition-opacity">
                        <img src="{{ $ajustes->urlLogo() }}" alt="Logo guardado" class="h-16 object-contain border border-gray-200 dark:border-gray-600 rounded">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Reemplace subiendo una nueva imagen.</span>
                    </div>
                @endif
                <input type="file" name="logo" id="logo" accept="image/*"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 text-sm bg-white dark:bg-gray-700 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 dark:file:bg-gray-600 dark:file:text-gray-200">
                <div id="logo-nueva-preview-wrap" class="mt-3 hidden flex flex-wrap items-center gap-3" aria-live="polite">
                    <img id="logo-nueva-preview" src="" alt="Vista previa del logo" width="128" height="64" class="h-16 max-w-[200px] w-auto object-contain border border-green-200 dark:border-green-800 rounded bg-white dark:bg-gray-900/50 p-1">
                    <span class="text-xs text-gray-600 dark:text-gray-400">Vista previa (se guardará al pulsar «Guardar ajustes»)</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teléfono</label>
                    <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $ajustes->telefono ?? '') }}" maxlength="50"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo electrónico</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $ajustes->email ?? '') }}" maxlength="100"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
            </div>

            <div>
                <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                <input type="text" name="direccion" id="direccion" value="{{ old('direccion', $ajustes->direccion ?? '') }}" maxlength="255"
                       class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
            </div>

            <div>
                <label for="sitio_web" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sitio web / Página web</label>
                <input type="url" name="sitio_web" id="sitio_web" value="{{ old('sitio_web', $ajustes->sitio_web ?? '') }}" placeholder="https://..."
                       maxlength="255" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Guardar ajustes
                </button>
                <a href="{{ route('configuracion.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('logo');
    var previewWrap = document.getElementById('logo-nueva-preview-wrap');
    var previewImg = document.getElementById('logo-nueva-preview');
    var logoGuardadoWrap = document.getElementById('logo-guardado-wrap');
    var objectUrl = null;

    if (!input || !previewWrap || !previewImg) {
        return;
    }

    function limpiarObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    input.addEventListener('change', function () {
        limpiarObjectUrl();
        var file = input.files && input.files[0];
        if (!file) {
            previewWrap.classList.add('hidden');
            previewImg.removeAttribute('src');
            if (logoGuardadoWrap) {
                logoGuardadoWrap.classList.remove('hidden', 'opacity-40');
            }
            return;
        }
        if (!/^image\//.test(file.type)) {
            previewWrap.classList.add('hidden');
            previewImg.removeAttribute('src');
            return;
        }
        objectUrl = URL.createObjectURL(file);
        previewImg.src = objectUrl;
        previewWrap.classList.remove('hidden');
        if (logoGuardadoWrap) {
            logoGuardadoWrap.classList.add('opacity-40');
        }
    });
});
</script>
@endpush
