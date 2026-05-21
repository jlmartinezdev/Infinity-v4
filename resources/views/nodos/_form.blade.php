@csrf
@isset($nodo)
    @method('PUT')
@endisset

@php
    $nodo = $nodo ?? null;
@endphp

<div class="space-y-6">
    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion', $nodo?->descripcion) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="120" placeholder="Ej: Nodo Centro">
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tecnologías que maneja el nodo</span>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Marcá GPON (fibra), Wireless (radio) o ambas si el nodo atiende los dos tipos de instalación.</p>
        <div class="flex flex-wrap gap-6">
            @php
                $gponChecked = old('tecnologia_gpon', $nodo ? $nodo->tecnologia_gpon : true);
                $wirelessChecked = old('tecnologia_wireless', $nodo ? $nodo->tecnologia_wireless : true);
            @endphp
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="tecnologia_gpon" value="0">
                <input type="checkbox" name="tecnologia_gpon" value="1"
                    class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500"
                    {{ filter_var($gponChecked, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                <span class="text-sm text-gray-800 dark:text-gray-200 font-medium">GPON / Fibra</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="tecnologia_wireless" value="0">
                <input type="checkbox" name="tecnologia_wireless" value="1"
                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                    {{ filter_var($wirelessChecked, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                <span class="text-sm text-gray-800 dark:text-gray-200 font-medium">Wireless</span>
            </label>
        </div>
        @error('tecnologia_gpon')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('tecnologia_wireless')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="coordenas_gps" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Coordenadas GPS</label>
            <input type="text" name="coordenas_gps" id="coordenas_gps" value="{{ old('coordenas_gps', $nodo?->coordenas_gps) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="50" placeholder="Ej: -25.2637, -57.5759">
            @error('coordenas_gps')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ciudad" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ciudad</label>
            <input type="text" name="ciudad" id="ciudad" value="{{ old('ciudad', $nodo?->ciudad) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="50" placeholder="Ej: Asunción">
            @error('ciudad')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $nodo ? 'Actualizar nodo' : 'Crear nodo' }}
        </button>
        <a href="{{ route('nodos.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>
