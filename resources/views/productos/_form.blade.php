@csrf
@isset($producto)
    @method('PUT')
@endisset

@php
    $producto = $producto ?? null;
@endphp

<div class="space-y-6">
    <div>
        <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $producto?->nombre) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="200" required autofocus placeholder="Nombre del producto">
        @error('nombre')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="categoria_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría</label>
            <select name="categoria_id" id="categoria_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                <option value="">Sin categoría</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id }}" {{ old('categoria_id', $producto?->categoria_id) == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                @endforeach
            </select>
            @error('categoria_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="proveedor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
            <select name="proveedor_id" id="proveedor_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                <option value="">Sin proveedor</option>
                @foreach($proveedores as $prov)
                    <option value="{{ $prov->id }}" {{ old('proveedor_id', $producto?->proveedor_id) == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                @endforeach
            </select>
            @error('proveedor_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <label for="codigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Código</label>
            <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $producto?->codigo) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="50" placeholder="Ej: PROD-001">
            @error('codigo')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="unidad" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unidad</label>
            <input type="text" name="unidad" id="unidad" value="{{ old('unidad', $producto?->unidad ?? 'unidad') }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="20" placeholder="Ej: unidad, kg, m">
            @error('unidad')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
            <select name="estado" id="estado" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                <option value="activo" {{ old('estado', $producto?->estado ?? 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
                <option value="inactivo" {{ old('estado', $producto?->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
            </select>
            @error('estado')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div>
            <label for="stock_minimo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock mínimo</label>
            <input type="number" name="stock_minimo" id="stock_minimo" value="{{ \App\Models\Producto::valorInput(old('stock_minimo', $producto?->stock_minimo ?? 0)) }}" step="0.01" min="0"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
            @error('stock_minimo')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="moneda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Moneda *</label>
            <select name="moneda" id="moneda" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                <option value="PYG" {{ old('moneda', $producto?->moneda ?? 'PYG') === 'PYG' ? 'selected' : '' }}>Guaraníes (Gs)</option>
                <option value="USD" {{ old('moneda', $producto?->moneda) === 'USD' ? 'selected' : '' }}>Dólar (USD)</option>
            </select>
            @error('moneda')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="precio_compra" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio compra</label>
            <input type="number" name="precio_compra" id="precio_compra" value="{{ \App\Models\Producto::valorInput(old('precio_compra', $producto?->precio_compra ?? 0)) }}" step="0.01" min="0"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
            @error('precio_compra')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="precio_venta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio venta</label>
            <input type="number" name="precio_venta" id="precio_venta" value="{{ \App\Models\Producto::valorInput(old('precio_venta', $producto?->precio_venta ?? 0)) }}" step="0.01" min="0"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
            @error('precio_venta')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if($producto)
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock actual</label>
            <p class="text-gray-900 dark:text-gray-100 font-medium">{{ \App\Models\Producto::formatoNumero($producto->stock_actual) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">El stock se actualiza mediante compras y ventas.</p>
        </div>
    @endif

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="3"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y">{{ old('descripcion', $producto?->descripcion) }}</textarea>
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Imagen</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">JPG, PNG, WebP o GIF, máx. 4 MB.</p>

        @if($producto?->imagenUrl())
            <div class="mb-3 flex items-start gap-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                <img src="{{ $producto->imagenUrl() }}" alt="{{ $producto->nombre }}" class="h-24 w-24 rounded-lg object-cover bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="flex-1 text-sm">
                    <p class="font-medium text-gray-900 dark:text-gray-100">Imagen actual</p>
                    <label class="mt-2 inline-flex items-center gap-2 text-red-600 dark:text-red-400 cursor-pointer">
                        <input type="checkbox" name="eliminar_imagen" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        Quitar imagen
                    </label>
                </div>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-4 items-start">
            <input type="file" name="imagen" id="imagen" accept="image/jpeg,image/png,image/webp,image/gif"
                class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-purple-900/30 dark:file:text-purple-200">
            <img id="producto-imagen-preview" src="" alt="" class="hidden h-24 w-24 rounded-lg object-cover bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
        </div>
        @error('imagen')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            {{ $producto ? 'Actualizar producto' : 'Crear producto' }}
        </button>
        <a href="{{ route('productos.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>

<script>
(function() {
    var fileInput = document.getElementById('imagen');
    var preview = document.getElementById('producto-imagen-preview');
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
