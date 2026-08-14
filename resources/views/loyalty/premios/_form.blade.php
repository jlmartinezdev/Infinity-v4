@csrf
@isset($premio) @method('PUT') @endisset
@php
    $premio = $premio ?? null;
    $tipos = $tipos ?? \App\Models\Premio::tipos();
    $etiquetas = $etiquetas ?? \App\Models\Premio::etiquetas();
    $tipoActual = old('tipo', $premio?->tipo ?? \App\Models\Premio::TIPO_FISICO);
    $stockIlimitado = (bool) old('stock_ilimitado', $premio ? ($premio->stock === null) : true);
@endphp
<div class="space-y-5">
    <div>
        <label class="block text-sm font-medium mb-1">Nombre *</label>
        <input type="text" name="nombre" value="{{ old('nombre', $premio?->nombre) }}" required
            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Descripción</label>
        <textarea name="descripcion" rows="3" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">{{ old('descripcion', $premio?->descripcion) }}</textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Tipo de premio *</label>
            <select name="tipo" id="premio-tipo" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
                @foreach($tipos as $k => $label)
                    <option value="{{ $k }}" @selected($tipoActual === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">El tipo define el canje: el cliente no elige modalidad.</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Etiqueta promo</label>
            <select name="etiqueta" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
                <option value="">Sin badge</option>
                @foreach($etiquetas as $k => $label)
                    <option value="{{ $k }}" @selected(old('etiqueta', $premio?->etiqueta) === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div id="premio-descuento-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ $tipoActual === 'descuento_factura' ? '' : 'hidden' }}">
        <div>
            <label class="block text-sm font-medium mb-1">Descuento %</label>
            <input type="number" name="descuento_porcentaje" min="0" max="100" step="0.01"
                value="{{ old('descuento_porcentaje', $premio?->descuento_porcentaje) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Descuento monto (Gs.)</label>
            <input type="number" name="descuento_monto" min="0" step="1"
                value="{{ old('descuento_monto', $premio?->descuento_monto) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
        <p class="md:col-span-2 text-xs text-gray-500">Indicá al menos uno: % o monto fijo.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Puntos requeridos *</label>
            <input type="number" name="puntos_requeridos" min="1" value="{{ old('puntos_requeridos', $premio?->puntos_requeridos ?? 100) }}" required
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Stock</label>
            <input type="number" name="stock" id="premio-stock" min="0" {{ $stockIlimitado ? 'disabled' : '' }}
                value="{{ old('stock', $premio?->stock) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
            <label class="inline-flex items-center gap-2 text-xs text-gray-500 mt-1">
                <input type="checkbox" name="stock_ilimitado" id="premio-stock-ilimitado" value="1" @checked($stockIlimitado)>
                Sin límite (null)
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Orden</label>
            <input type="number" name="orden" min="0" value="{{ old('orden', $premio?->orden ?? 0) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Tier (1–5)</label>
            <input type="number" name="tier" min="1" max="5" value="{{ old('tier', $premio?->tier) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Tope anual por cliente</label>
            <input type="number" name="tope_anual_por_cliente" min="1"
                value="{{ old('tope_anual_por_cliente', $premio?->tope_anual_por_cliente) }}"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100"
                placeholder="Vacío = sin tope">
        </div>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 sm:gap-6 flex-wrap">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="activo" value="1" @checked(old('activo', $premio?->activo ?? true))> Activo</label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="destacado" value="1" @checked(old('destacado', $premio?->destacado ?? false))>
            Premio destacado
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="requiere_aprobacion" value="1" @checked(old('requiere_aprobacion', $premio?->requiere_aprobacion ?? false))>
            Requiere aprobación staff
        </label>
    </div>
    <p class="text-xs text-gray-500 -mt-2">Solo uno destacado a la vez. Stock 0 oculta el premio en la app. Desactivar no borra historial de canjes.</p>
    <div>
        <label class="block text-sm font-medium mb-1">Imagen</label>
        @if($premio?->imagenUrl())
            <img src="{{ $premio->imagenUrl() }}" class="h-24 mb-2 rounded object-cover">
            <label class="inline-flex items-center gap-2 text-sm text-red-600 mb-2"><input type="checkbox" name="eliminar_imagen" value="1"> Eliminar imagen</label>
        @endif
        <input type="file" name="imagen" accept="image/*" class="block w-full text-sm">
    </div>
    <div class="flex gap-3">
        <button class="px-5 py-2 bg-purple-600 text-white rounded-lg">Guardar</button>
        <a href="{{ route('loyalty.premios.index') }}" class="px-5 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg">Cancelar</a>
    </div>
</div>
<script>
(function () {
    const sel = document.getElementById('premio-tipo');
    const box = document.getElementById('premio-descuento-fields');
    const stock = document.getElementById('premio-stock');
    const ilim = document.getElementById('premio-stock-ilimitado');
    if (sel && box) {
        const sync = () => box.classList.toggle('hidden', sel.value !== 'descuento_factura');
        sel.addEventListener('change', sync);
        sync();
    }
    if (stock && ilim) {
        const syncStock = () => {
            stock.disabled = ilim.checked;
            if (ilim.checked) stock.value = '';
        };
        ilim.addEventListener('change', syncStock);
        syncStock();
    }
})();
</script>
