@csrf
@isset($routerModelo)
    @method('PUT')
@endisset

@php
    $routerModelo = $routerModelo ?? null;
    $series = $series ?? [];
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del modelo *</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $routerModelo?->nombre) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="120" required placeholder="Ej: RB4011iGS+RM">
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Identificador (slug)</label>
            <input type="text" name="slug" id="slug" value="{{ old('slug', $routerModelo?->slug) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors font-mono text-sm"
                maxlength="64" placeholder="rb4011 (opcional, se genera del nombre)">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Solo minúsculas, números y guiones. Se usa al asignar el modelo a un router.</p>
            @error('slug')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="serie" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Serie *</label>
            <input type="text" name="serie" id="serie" list="series-mikrotik" value="{{ old('serie', $routerModelo?->serie ?? 'RB') }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="32" required>
            <datalist id="series-mikrotik">
                @foreach($series as $s)
                    <option value="{{ $s }}">
                @endforeach
            </datalist>
            @error('serie')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="orden" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Orden</label>
            <input type="number" name="orden" id="orden" value="{{ old('orden', $routerModelo?->orden ?? 0) }}"
                min="0" max="9999"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
            @error('orden')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion', $routerModelo?->descripcion) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="255" placeholder="Ej: Router rack 10 puertos + SFP+">
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Imagen del modelo</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Subí una foto o ilustración del equipo (JPG, PNG o WebP, máx. 4 MB). Se mostrará al seleccionar este modelo en los routers.</p>

        @if($routerModelo?->imagen)
            <div class="mb-3 flex items-start gap-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                <img src="{{ $routerModelo->imagenUrl() }}" alt="{{ $routerModelo->nombre }}" id="modelo-imagen-actual" class="h-24 w-32 rounded-lg object-contain bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="flex-1 text-sm">
                    <p class="font-medium text-gray-900 dark:text-gray-100">Imagen actual</p>
                    @if($routerModelo->imagenEsSubida())
                        <label class="mt-2 inline-flex items-center gap-2 text-red-600 dark:text-red-400 cursor-pointer">
                            <input type="checkbox" name="eliminar_imagen" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            Quitar imagen subida
                        </label>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Imagen predeterminada del sistema. Subí una nueva para reemplazarla.</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-4 items-start">
            <input type="file" name="imagen" id="imagen" accept="image/jpeg,image/png,image/webp,image/gif"
                class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-purple-900/30 dark:file:text-purple-200">
            <img id="modelo-imagen-preview" src="" alt="" class="hidden h-24 w-32 rounded-lg object-contain bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
        </div>
        @error('imagen')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="activo" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                {{ old('activo', $routerModelo?->activo ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700 dark:text-gray-300">Activo (visible al crear/editar routers)</span>
        </label>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $routerModelo ? 'Actualizar modelo' : 'Agregar al catálogo' }}
        </button>
        <a href="{{ route('sistema.router-modelos.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>

<script>
(function() {
    var fileInput = document.getElementById('imagen');
    var preview = document.getElementById('modelo-imagen-preview');
    if (fileInput && preview) {
        fileInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) {
                preview.classList.add('hidden');
                preview.removeAttribute('src');
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        });
    }
})();
</script>
