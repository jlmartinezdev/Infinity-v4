@php
    $tipoCambioValor = old('tipo_cambio', $valor ?? '');
    $tipoCambioClass = $inputClass ?? 'w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors';
@endphp
<div>
    <label for="tipo_cambio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cambio USD → Gs *</label>
    <input type="number" name="tipo_cambio" id="tipo_cambio" value="{{ $tipoCambioValor }}"
        step="0.01" min="1" max="999999" required placeholder="Ej: 7300"
        class="{{ $tipoCambioClass }}">
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        1 USD = esta cantidad de guaraníes. Se guarda como referencia de esta operación.
        <a href="{{ route('cotizacion-dolar.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Registrar cotización</a>
    </p>
    @error('tipo_cambio')
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
