@csrf
@isset($factura)
    @method('PUT')
@endisset

@php
    $factura = $factura ?? null;
    $detalles = old('detalles');
    if ($detalles === null) {
        $detalles = $factura && $factura->detalles->isNotEmpty()
            ? $factura->detalles->all()
            : [(object)['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 0, 'impuesto_id' => null]];
    }
    $detalles = is_array($detalles) ? $detalles : collect($detalles)->all();
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
            <select name="cliente_id" id="cliente_id" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Seleccione cliente</option>
                @foreach ($clientes as $c)
                    <option value="{{ $c->cliente_id }}" {{ old('cliente_id', $factura?->cliente_id) == $c->cliente_id ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }} ({{ $c->cedula }})</option>
                @endforeach
            </select>
            @error('cliente_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="tipo_documento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo documento *</label>
            <select name="tipo_documento" id="tipo_documento" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach (App\Models\Factura::tiposDocumento() as $key => $label)
                    <option value="{{ $key }}" {{ old('tipo_documento', $factura?->tipo_documento ?? 'factura_contado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="fecha_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha emisión *</label>
            <input type="date" name="fecha_emision" id="fecha_emision" value="{{ old('fecha_emision', $factura?->fecha_emision?->format('Y-m-d') ?? date('Y-m-d')) }}" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            @error('fecha_emision')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="fecha_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha vencimiento</label>
            <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="{{ old('fecha_vencimiento', $factura?->fecha_vencimiento?->format('Y-m-d')) }}" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="moneda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Moneda *</label>
            <select name="moneda" id="moneda" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="PYG" {{ old('moneda', $factura?->moneda ?? 'PYG') == 'PYG' ? 'selected' : '' }}>Guaraníes (PYG)</option>
                <option value="USD" {{ old('moneda', $factura?->moneda) == 'USD' ? 'selected' : '' }}>Dólares (USD)</option>
            </select>
        </div>
        <div>
            <label for="numero_timbrado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Timbrado (SET)</label>
            <input type="text" name="numero_timbrado" id="numero_timbrado" value="{{ old('numero_timbrado', $factura?->numero_timbrado) }}" maxlength="20" placeholder="Opcional" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label for="timbrado_vigencia_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vigencia timbrado desde</label>
            <input type="date" name="timbrado_vigencia_desde" id="timbrado_vigencia_desde" value="{{ old('timbrado_vigencia_desde', $factura?->timbrado_vigencia_desde?->format('Y-m-d')) }}" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>
        <div>
            <label for="timbrado_vigencia_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vigencia timbrado hasta</label>
            <input type="date" name="timbrado_vigencia_hasta" id="timbrado_vigencia_hasta" value="{{ old('timbrado_vigencia_hasta', $factura?->timbrado_vigencia_hasta?->format('Y-m-d')) }}" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>
        <div>
            <label for="establecimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Establecimiento</label>
            <input type="number" name="establecimiento" id="establecimiento" value="{{ old('establecimiento', $factura?->establecimiento ?? 1) }}" min="1" max="255" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>
        <div>
            <label for="punto_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Punto emisión</label>
            <input type="number" name="punto_emision" id="punto_emision" value="{{ old('punto_emision', $factura?->punto_emision ?? 1) }}" min="1" max="255" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>
    </div>

    <div>
        <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="2" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 resize-y">{{ old('observaciones', $factura?->observaciones) }}</textarea>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Detalle de la factura *</label>
            <button type="button" id="btn-agregar-linea" class="text-sm text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">+ Agregar línea</button>
        </div>
        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600" id="tabla-detalles">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-1/2">Descripción</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">Cantidad</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-28">P. unitario</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">Impuesto</th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="tbody-detalles" class="bg-white dark:bg-gray-800">
                    @foreach ($detalles as $idx => $item)
                    @php $item = is_array($item) ? (object)$item : $item; @endphp
                    <tr class="detalle-row border-t border-gray-100 dark:border-gray-600">
                        <td class="px-3 py-2"><input type="text" name="detalles[{{ $idx }}][descripcion]" value="{{ $item->descripcion ?? '' }}" required class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Descripción"></td>
                        <td class="px-3 py-2"><input type="number" name="detalles[{{ $idx }}][cantidad]" value="{{ $item->cantidad ?? 1 }}" min="0.0001" step="0.0001" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm text-right detalle-cantidad bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"></td>
                        <td class="px-3 py-2"><input type="number" name="detalles[{{ $idx }}][precio_unitario]" value="{{ $item->precio_unitario ?? 0 }}" min="0" step="0.01" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm text-right detalle-precio bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"></td>
                        <td class="px-3 py-2">
                            <select name="detalles[{{ $idx }}][impuesto_id]" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 detalle-impuesto">
                                <option value="">Exento</option>
                                @foreach ($impuestos as $imp)
                                    <option value="{{ $imp->id }}" {{ ($item->impuesto_id ?? null) == $imp->id ? 'selected' : '' }}>{{ $imp->nombre }} ({{ $imp->porcentaje }}%)</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-3 py-2"><button type="button" class="quitar-linea text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 p-1" title="Quitar">×</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('detalles')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            {{ $factura ? 'Actualizar factura' : 'Crear factura' }}
        </button>
        <a href="{{ route('facturas.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
    </div>
</div>

<script>
(function() {
    var tbody = document.getElementById('tbody-detalles');
    var btnAgregar = document.getElementById('btn-agregar-linea');
    var impuestosOptions = @json($impuestos->map(fn($i) => ['id' => $i->id, 'nombre' => $i->nombre . ' (' . $i->porcentaje . '%)'])->values());

    function nextIndex() {
        var rows = tbody.querySelectorAll('.detalle-row');
        return rows.length;
    }

    function addRow() {
        var idx = nextIndex();
        var tr = document.createElement('tr');
        tr.className = 'detalle-row border-t border-gray-100 dark:border-gray-600';
        var impOptions = impuestosOptions.map(function(i) {
            return '<option value="' + i.id + '">' + i.nombre + '</option>';
        }).join('');
        tr.innerHTML = '<td class="px-3 py-2"><input type="text" name="detalles[' + idx + '][descripcion]" required class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Descripción"></td>' +
            '<td class="px-3 py-2"><input type="number" name="detalles[' + idx + '][cantidad]" value="1" min="0.0001" step="0.0001" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm text-right detalle-cantidad bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"></td>' +
            '<td class="px-3 py-2"><input type="number" name="detalles[' + idx + '][precio_unitario]" value="0" min="0" step="0.01" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm text-right detalle-precio bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"></td>' +
            '<td class="px-3 py-2"><select name="detalles[' + idx + '][impuesto_id]" class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 detalle-impuesto"><option value="">Exento</option>' + impOptions + '</select></td>' +
            '<td class="px-3 py-2"><button type="button" class="quitar-linea text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 p-1" title="Quitar">×</button></td>';
        tbody.appendChild(tr);
        reindexRows();
    }

    function reindexRows() {
        tbody.querySelectorAll('.detalle-row').forEach(function(tr, i) {
            tr.querySelectorAll('input, select').forEach(function(inp) {
                inp.name = inp.name.replace(/detalles\[\d+\]/, 'detalles[' + i + ']');
            });
        });
    }

    btnAgregar.addEventListener('click', addRow);
    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('quitar-linea')) {
            var row = e.target.closest('tr');
            if (tbody.querySelectorAll('.detalle-row').length > 1) {
                row.remove();
                reindexRows();
            }
        }
    });
})();
</script>
