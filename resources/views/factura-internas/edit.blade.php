@extends('layouts.app')

@section('title', 'Editar factura interna #' . $factura_interna->id)

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('factura-internas.show', $factura_interna) }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a factura</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar factura interna #{{ $factura_interna->id }}</h1>
    </div>

    <form action="{{ route('factura-internas.update', $factura_interna) }}" method="POST" id="form-edit-factura-interna">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos de la factura</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente *</label>
                    <select name="cliente_id" id="cliente_id" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach ($clientes as $c)
                            <option value="{{ $c->cliente_id }}" {{ old('cliente_id', $factura_interna->cliente_id) == $c->cliente_id ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="periodo_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período desde *</label>
                    <input type="date" name="periodo_desde" id="periodo_desde" value="{{ old('periodo_desde', $factura_interna->periodo_desde?->format('Y-m-d')) }}" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('periodo_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="periodo_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período hasta *</label>
                    <input type="date" name="periodo_hasta" id="periodo_hasta" value="{{ old('periodo_hasta', $factura_interna->periodo_hasta?->format('Y-m-d')) }}" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('periodo_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="fecha_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de emisión *</label>
                    <input type="date" name="fecha_emision" id="fecha_emision" value="{{ old('fecha_emision', $factura_interna->fecha_emision?->format('Y-m-d')) }}" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('fecha_emision')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="fecha_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de vencimiento *</label>
                    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="{{ old('fecha_vencimiento', $factura_interna->fecha_vencimiento?->format('Y-m-d')) }}" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('fecha_vencimiento')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado *</label>
                    <select name="estado" id="estado" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach ($estados as $key => $label)
                            <option value="{{ $key }}" {{ old('estado', $factura_interna->estado) == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('estado')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="moneda" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Moneda *</label>
                    <select name="moneda" id="moneda" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="PYG" {{ old('moneda', $factura_interna->moneda) == 'PYG' ? 'selected' : '' }}>PYG</option>
                        <option value="USD" {{ old('moneda', $factura_interna->moneda) == 'USD' ? 'selected' : '' }}>USD</option>
                    </select>
                    @error('moneda')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="2" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('observaciones', $factura_interna->observaciones) }}</textarea>
                    @error('observaciones')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Datos de pago</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Referencia para cobros y total a facturar tras ítems.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($factura_interna->estado === 'pagada' && isset($cobrosFactura) && $cobrosFactura->isNotEmpty())
                <div class="md:col-span-2">
                    <label for="forma_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pago</label>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Corresponde al cobro vinculado a esta factura. Si hubiera más de un cobro, se aplicará la misma forma a todos los asociados.</p>
                    <select name="forma_pago" id="forma_pago" class="mt-2 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach($formasPago as $key => $label)
                            <option value="{{ $key }}" {{ old('forma_pago', $formaPagoActual ?? 'efectivo') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('forma_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                @endif
                <div>
                    <label for="fecha_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de pago (opcional)</label>
                    @if(!empty($fechaPagoDesdeCobros))
                        <p class="mt-0.5 text-xs text-blue-700 dark:text-blue-300">Factura pagada: se muestra la fecha de pago del cobro más reciente vinculado a esta factura (podés ajustarla al guardar).</p>
                    @else
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Si el cobro es anterior a esta fecha, el recibo usará esta fecha; si es posterior, la fecha del cobro.</p>
                    @endif
                    <input type="date" name="fecha_pago" id="fecha_pago" value="{{ old('fecha_pago', $fechaPagoParaEdicion?->format('Y-m-d')) }}"
                           class="mt-2 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('fecha_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="descuento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descuento ({{ $factura_interna->moneda ?? 'PYG' }})</label>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Se resta del total bruto de las líneas (igual que al crear la factura).</p>
                    <input type="number" name="descuento" id="descuento" value="{{ old('descuento', $factura_interna->descuento ?? 0) }}" min="0" step="0.01"
                           class="mt-2 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('descuento')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-900/40">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Detalle</h2>
                <button type="button" id="btn-add-detalle" class="inline-flex items-center px-3 py-1.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 dark:focus:ring-2 dark:focus:ring-purple-500 dark:focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    + Añadir línea
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-8"></th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">Cant.</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">P. unit.</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-40">Impuesto</th>
                        </tr>
                    </thead>
                    <tbody id="detalles-tbody" class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @php
                            $detallesOld = old('detalles', $factura_interna->detalles->isEmpty() ? [['id' => '', 'descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 0, 'impuesto_id' => '']] : $factura_interna->detalles->all());
                        @endphp
                        @foreach ($detallesOld as $i => $d)
                        <tr class="js-detalle-row">
                            <td class="px-4 py-2">
                                <button type="button" class="js-remove-detalle p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded" title="Quitar línea">&times;</button>
                            </td>
                            <td class="px-4 py-2">
                                <input type="hidden" name="detalles[{{ $i }}][id]" value="{{ is_object($d) ? $d->id : ($d['id'] ?? '') }}">
                                <input type="text" name="detalles[{{ $i }}][descripcion]" value="{{ is_object($d) ? $d->descripcion : ($d['descripcion'] ?? '') }}" required maxlength="500" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-1 focus:ring-purple-500/20">
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" name="detalles[{{ $i }}][cantidad]" value="{{ is_object($d) ? $d->cantidad : ($d['cantidad'] ?? 1) }}" step="0.0001" min="0" required class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm text-right bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-1 focus:ring-purple-500/20">
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" name="detalles[{{ $i }}][precio_unitario]" value="{{ is_object($d) ? $d->precio_unitario : ($d['precio_unitario'] ?? '') }}" step="0.01" min="0" required class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm text-right bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-1 focus:ring-purple-500/20">
                            </td>
                            <td class="px-4 py-2">
                                <select name="detalles[{{ $i }}][impuesto_id]" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-1 focus:ring-purple-500/20">
                                    <option value="">Sin impuesto</option>
                                    @foreach ($impuestos as $imp)
                                        <option value="{{ $imp->id }}" {{ (is_object($d) ? $d->impuesto_id : ($d['impuesto_id'] ?? null)) == $imp->id ? 'selected' : '' }}>{{ $imp->nombre }} ({{ $imp->porcentaje }}%)</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('detalles')<p class="px-6 py-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                Guardar cambios
            </button>
            <a href="{{ route('factura-internas.show', $factura_interna) }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.getElementById('detalles-tbody');
    var btnAdd = document.getElementById('btn-add-detalle');
    var indice = tbody.querySelectorAll('.js-detalle-row').length;

    var inpCl = 'w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-1 focus:ring-purple-500/20';
    var inpNumCl = inpCl + ' text-right';
    var selCl = 'w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-1 focus:ring-purple-500/20';
    var btnRemCl = 'js-remove-detalle p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded';

    function impuestoOptionsHtml() {
        return @json(
            $impuestos->map(fn ($imp) => [
                'id' => $imp->id,
                'label' => $imp->nombre . ' (' . $imp->porcentaje . '%)',
            ])->values()
        ).map(function(imp) {
            return '<option value="' + imp.id + '">' + escapeHtml(imp.label) + '</option>';
        }).join('');
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function addRow() {
        var tr = document.createElement('tr');
        tr.className = 'js-detalle-row';
        tr.innerHTML =
            '<td class="px-4 py-2"><button type="button" class="' + btnRemCl + '" title="Quitar línea">&times;</button></td>' +
            '<td class="px-4 py-2"><input type="hidden" name="detalles[' + indice + '][id]" value="">' +
            '<input type="text" name="detalles[' + indice + '][descripcion]" value="" required maxlength="500" class="' + inpCl + '"></td>' +
            '<td class="px-4 py-2"><input type="number" name="detalles[' + indice + '][cantidad]" value="1" step="0.0001" min="0" required class="' + inpNumCl + '"></td>' +
            '<td class="px-4 py-2"><input type="number" name="detalles[' + indice + '][precio_unitario]" value="0" step="0.01" min="0" required class="' + inpNumCl + '"></td>' +
            '<td class="px-4 py-2"><select name="detalles[' + indice + '][impuesto_id]" class="' + selCl + '"><option value="">Sin impuesto</option>' + impuestoOptionsHtml() + '</select></td>';
        tbody.appendChild(tr);
        indice++;
        tr.querySelector('.js-remove-detalle').addEventListener('click', removeRow);
    }

    function removeRow(e) {
        var row = e.target.closest('tr');
        if (tbody.querySelectorAll('.js-detalle-row').length <= 1) return;
        row.remove();
        reindexDetalles();
        indice = tbody.querySelectorAll('.js-detalle-row').length;
    }

    function reindexDetalles() {
        var rows = tbody.querySelectorAll('.js-detalle-row');
        indice = 0;
        rows.forEach(function(tr) {
            tr.querySelectorAll('input, select').forEach(function(input) {
                var name = input.getAttribute('name');
                if (name) input.setAttribute('name', name.replace(/detalles\[\d+\]/, 'detalles[' + indice + ']'));
            });
            indice++;
        });
    }

    btnAdd.addEventListener('click', addRow);
    tbody.querySelectorAll('.js-remove-detalle').forEach(function(btn) {
        btn.addEventListener('click', removeRow);
    });
});
</script>
@endpush
@endsection
