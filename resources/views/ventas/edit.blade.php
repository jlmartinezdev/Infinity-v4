@extends('layouts.app')

@section('title', 'Editar venta #' . $venta->id)

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar venta #{{ $venta->id }}</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('ventas.update', $venta) }}" method="POST" id="form-venta">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
                        <select name="cliente_id" id="cliente_id" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                            <option value="">Seleccionar cliente...</option>
                            @foreach($clientes as $cli)
                                <option value="{{ $cli->cliente_id }}" {{ old('cliente_id', $venta->cliente_id) == $cli->cliente_id ? 'selected' : '' }}>{{ $cli->nombre }} {{ $cli->apellido }}</option>
                            @endforeach
                        </select>
                        @error('cliente_id')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="servicio_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Servicio (opcional)</label>
                        <select name="servicio_id" id="servicio_id"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                            <option value="">Sin servicio</option>
                            @foreach($servicios ?? [] as $s)
                                <option value="{{ $s->servicio_id }}" {{ old('servicio_id', $venta->servicio_id) == $s->servicio_id ? 'selected' : '' }} data-cliente="{{ $s->cliente_id ?? '' }}">
                                    Servicio #{{ $s->servicio_id }} - {{ $s->cliente?->nombre ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('servicio_id')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
                        <input type="date" name="fecha" id="fecha" value="{{ old('fecha', $venta->fecha?->format('Y-m-d')) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                            required>
                        @error('fecha')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="numero_factura" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Factura</label>
                        <input type="text" name="numero_factura" id="numero_factura" value="{{ old('numero_factura', $venta->numero_factura) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                            maxlength="100">
                        @error('numero_factura')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
                        <select name="estado" id="estado" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                            <option value="pendiente" {{ old('estado', $venta->estado) === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                            <option value="cobrado" {{ old('estado', $venta->estado) === 'cobrado' ? 'selected' : '' }}>Cobrado</option>
                            <option value="parcial" {{ old('estado', $venta->estado) === 'parcial' ? 'selected' : '' }}>Parcial</option>
                            <option value="anulado" {{ old('estado', $venta->estado) === 'anulado' ? 'selected' : '' }}>Anulado</option>
                        </select>
                        @error('estado')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="descuento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descuento</label>
                        <input type="number" name="descuento" id="descuento" value="{{ old('descuento', $venta->descuento ?? 0) }}" step="0.01" min="0"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                        @error('descuento')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="impuesto" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Impuesto</label>
                        <input type="number" name="impuesto" id="impuesto" value="{{ old('impuesto', $venta->impuesto ?? 0) }}" step="0.01" min="0"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                        @error('impuesto')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                        <input type="text" name="notas" id="notas" value="{{ old('notas', $venta->notas) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                        @error('notas')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Detalles *</label>
                        <button type="button" id="btn-add-detalle" class="text-sm px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            + Agregar línea
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="tabla-detalles">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-24">Cantidad</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-28">P. unitario</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-24">Subtotal</th>
                                    <th class="px-3 py-2 w-12"></th>
                                </tr>
                            </thead>
                            <tbody id="detalles-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                {{-- Filas dinámicas --}}
                            </tbody>
                        </table>
                    </div>
                    @error('detalles')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        Actualizar venta
                    </button>
                    <a href="{{ route('ventas.show', $venta) }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
                        Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const productos = @json($productos->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'precio_venta' => (float)$p->precio_venta]));
    const detallesIniciales = @json(old('detalles', $venta->detalles->map(fn($d) => ['producto_id' => $d->producto_id, 'cantidad' => $d->cantidad, 'precio_unitario' => $d->precio_unitario])->values()));
    const tbody = document.getElementById('detalles-body');
    const btnAdd = document.getElementById('btn-add-detalle');
    let rowIndex = 0;

    function addRow(productoId = '', cantidad = '', precioUnitario = '') {
        const producto = productos.find(p => p.id == productoId);
        const precio = precioUnitario || (producto ? producto.precio_venta : '');
        const tr = document.createElement('tr');
        tr.className = 'detalle-row';
        tr.dataset.index = rowIndex;
        tr.innerHTML = `
            <td class="px-3 py-2">
                <select name="detalles[${rowIndex}][producto_id]" class="detalle-producto w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm" required>
                    <option value="">Seleccionar...</option>
                    ${productos.map(p => `<option value="${p.id}" ${p.id == productoId ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                </select>
            </td>
            <td class="px-3 py-2">
                <input type="number" name="detalles[${rowIndex}][cantidad]" class="detalle-cantidad w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm text-right" step="0.01" min="0.01" value="${cantidad}" required>
            </td>
            <td class="px-3 py-2">
                <input type="number" name="detalles[${rowIndex}][precio_unitario]" class="detalle-precio w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm text-right" step="0.01" min="0" value="${precio}" required>
            </td>
            <td class="px-3 py-2 text-right text-sm subtotal-cell text-gray-900 dark:text-gray-100">0.00</td>
            <td class="px-3 py-2">
                <button type="button" class="btn-remove text-red-600 dark:text-red-400 hover:text-red-800 text-sm">✕</button>
            </td>
        `;
        tbody.appendChild(tr);

        tr.querySelector('.detalle-producto').addEventListener('change', function() {
            const p = productos.find(x => x.id == this.value);
            if (p && !tr.querySelector('.detalle-precio').value) tr.querySelector('.detalle-precio').value = p.precio_venta;
            calcSubtotal(tr);
        });
        tr.querySelector('.detalle-cantidad').addEventListener('input', () => calcSubtotal(tr));
        tr.querySelector('.detalle-precio').addEventListener('input', () => calcSubtotal(tr));
        tr.querySelector('.btn-remove').addEventListener('click', () => tr.remove());
        calcSubtotal(tr);
        rowIndex++;
    }

    function calcSubtotal(tr) {
        const cant = parseFloat(tr.querySelector('.detalle-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.detalle-precio').value) || 0;
        tr.querySelector('.subtotal-cell').textContent = (cant * prec).toFixed(2);
    }

    btnAdd.addEventListener('click', () => addRow());

    if (detallesIniciales.length > 0) {
        detallesIniciales.forEach(d => addRow(d.producto_id ?? '', d.cantidad ?? '', d.precio_unitario ?? ''));
    } else {
        addRow();
    }

    document.getElementById('form-venta').addEventListener('submit', function() {
        document.querySelectorAll('.detalle-row').forEach((tr, i) => {
            tr.querySelector('[name*="[producto_id]"]').name = `detalles[${i}][producto_id]`;
            tr.querySelector('[name*="[cantidad]"]').name = `detalles[${i}][cantidad]`;
            tr.querySelector('[name*="[precio_unitario]"]').name = `detalles[${i}][precio_unitario]`;
        });
    });
})();
</script>
@endpush
@endsection
