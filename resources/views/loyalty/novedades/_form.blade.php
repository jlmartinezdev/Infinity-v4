@csrf
@isset($novedad)
    @method('PUT')
@endisset
@php $novedad = $novedad ?? null; @endphp

<div class="space-y-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Título *</label>
            <input type="text" name="titulo" value="{{ old('titulo', $novedad?->titulo) }}" required maxlength="200"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
            @error('titulo')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Tipo *</label>
            <select name="tipo" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
                @foreach(\App\Models\Novedad::TIPOS as $t)
                    <option value="{{ $t }}" @selected(old('tipo', $novedad?->tipo ?? 'promo') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Subtítulo</label>
        <input type="text" name="subtitulo" value="{{ old('subtitulo', $novedad?->subtitulo) }}" maxlength="300"
            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">URL de acción</label>
        <input type="url" name="accion_url" value="{{ old('accion_url', $novedad?->accion_url) }}"
            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Orden</label>
            <input type="number" name="orden" value="{{ old('orden', $novedad?->orden ?? 0) }}" min="0"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Vigente desde</label>
            <input type="date" name="vigente_desde" value="{{ old('vigente_desde', optional($novedad?->vigente_desde)?->format('Y-m-d')) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Vigente hasta</label>
            <input type="date" name="vigente_hasta" value="{{ old('vigente_hasta', optional($novedad?->vigente_hasta)?->format('Y-m-d')) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
    </div>
    <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="activa" value="1" @checked(old('activa', $novedad?->activa ?? true))>
        <span class="text-sm">Activa</span>
    </label>
    <div>
        <label class="block text-sm font-medium mb-1">Imagen</label>
        @if($novedad?->imagenUrl())
            <img src="{{ $novedad->imagenUrl() }}" class="h-24 mb-2 rounded object-cover">
            <label class="inline-flex items-center gap-2 text-sm text-red-600 mb-2">
                <input type="checkbox" name="eliminar_imagen" value="1"> Eliminar imagen
            </label>
        @endif
        <input type="file" name="imagen" accept="image/*" class="block w-full text-sm">
    </div>
    <div class="flex gap-3">
        <button type="submit" class="px-5 py-2 bg-purple-600 text-white rounded-lg">Guardar</button>
        <a href="{{ route('loyalty.novedades.index') }}" class="px-5 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg">Cancelar</a>
    </div>
</div>
