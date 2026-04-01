@csrf
@isset($pedido)
    @method('PUT')
@endisset

@php
    $pedido = $pedido ?? null;
    $celularInicial = old('celular', $pedido?->cliente?->telefono ?? '');
@endphp

<div class="space-y-6">
    @if(isset($pedido) && $pedido)
    <div class="p-4 rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-900/20">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Crear cliente por cédula (padrón)</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Si el cliente no está en la lista, busque por cédula en el padrón. Si ya fue cargado antes, se seleccionará automáticamente.</p>
        <div class="flex gap-2 flex-wrap items-end">
            <div class="flex-1 min-w-[140px]">
                <label for="cedula-padron" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Cédula</label>
                <input type="text" id="cedula-padron" placeholder="Ej. 1234567"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <button type="button" id="btn-buscar-padron-pedido" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                <span id="btn-buscar-padron-pedido-text">Buscar padrón</span>
                <span id="btn-buscar-padron-pedido-loading" class="hidden">Consultando...</span>
            </button>
        </div>
        <p id="cedula-padron-msg" class="mt-2 text-sm hidden" role="status"></p>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
            <select name="cliente_id" id="cliente_id" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Seleccione un cliente</option>
                @foreach ($clientes as $cliente)
                    <option value="{{ $cliente->cliente_id }}"
                        data-telefono="{{ e($cliente->telefono ?? '') }}"
                        {{ old('cliente_id', $pedido?->cliente_id) == $cliente->cliente_id ? 'selected' : '' }}>
                        {{ $cliente->nombre }} {{ $cliente->apellido }} ({{ $cliente->cedula }})
                    </option>
                @endforeach
            </select>
            @error('cliente_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @if(isset($estados) && isset($estadoActual))
        <div>
            <label for="estado_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cambiar Estado</label>
            <select name="estado_id" id="estado_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Mantener estado actual</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado->estado_id }}"
                        {{ old('estado_id', $estadoActual?->estado_id) == $estado->estado_id ? 'selected' : '' }}>
                        {{ $estado->descripcion }}
                    </option>
                @endforeach
            </select>
            @if($estadoActual)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Estado actual: <strong>{{ $estadoActual->estadoPedido->descripcion ?? '—' }}</strong></p>
            @endif
            @error('estado_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        @else
        <div></div>
        @endif

        <div>
            <label for="fecha_pedido" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de pedido *</label>
            <input type="date" name="fecha_pedido" id="fecha_pedido"
                value="{{ old('fecha_pedido', $pedido?->fecha_pedido?->format('Y-m-d') ?? date('Y-m-d')) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                required>
            @error('fecha_pedido')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="prioridad_instalacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioridad de instalación</label>
            <select name="prioridad_instalacion" id="prioridad_instalacion"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="1" {{ old('prioridad_instalacion', $pedido?->prioridad_instalacion ?? 2) == 1 ? 'selected' : '' }}>Alta</option>
                <option value="2" {{ old('prioridad_instalacion', $pedido?->prioridad_instalacion ?? 2) == 2 ? 'selected' : '' }}>Media</option>
                <option value="3" {{ old('prioridad_instalacion', $pedido?->prioridad_instalacion ?? 2) == 3 ? 'selected' : '' }}>Baja</option>
            </select>
            @error('prioridad_instalacion')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ubicacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
            <input type="text" name="ubicacion" id="ubicacion"
                value="{{ old('ubicacion', $pedido?->ubicacion) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                placeholder="Dirección para la instalación...">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dirección o referencia del lugar de instalación</p>
            @error('ubicacion')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="maps_gps" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maps / GPS</label>
            <input type="text" name="maps_gps" id="maps_gps"
                value="{{ old('maps_gps', $pedido?->maps_gps) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                placeholder="https://www.google.com/maps/... o coordenadas">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enlace de Google Maps o coordenadas GPS</p>
            @error('maps_gps')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="celular" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Celular</label>
            <input type="text" name="celular" id="celular"
                value="{{ $celularInicial }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                placeholder="Teléfono del cliente...">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Teléfono del cliente seleccionado</p>
            @error('celular')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
            <textarea name="descripcion" id="descripcion" rows="3"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                placeholder="Descripción del pedido...">{{ old('descripcion', $pedido?->descripcion) }}</textarea>
            @error('descripcion')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones</label>
            <textarea name="observaciones" id="observaciones" rows="3"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                placeholder="Observaciones adicionales...">{{ old('observaciones', $pedido?->observaciones) }}</textarea>
            @error('observaciones')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2 flex flex-wrap gap-3">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                {{ $pedido ? 'Actualizar pedido' : 'Crear pedido' }}
            </button>
            <a href="{{ route('pedidos.index') }}"
                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                Cancelar
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var selectCliente = document.getElementById('cliente_id');
    var inputCelular = document.getElementById('celular');
    if (!selectCliente || !inputCelular) return;

    function syncCelularFromSelect() {
        var opt = selectCliente.options[selectCliente.selectedIndex];
        if (opt && opt.value && opt.dataset.telefono !== undefined) {
            inputCelular.value = opt.dataset.telefono || '';
        }
    }

    selectCliente.addEventListener('change', syncCelularFromSelect);
})();
</script>
@endpush
