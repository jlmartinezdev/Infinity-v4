@csrf
@isset($factura)
    @method('PUT')
@endisset

@php
    $factura = $factura ?? null;
    $prefill = $prefill ?? [];
    $detallesIniciales = $detallesIniciales ?? [];
    $detalles = old('detalles');
    if ($detalles === null) {
        if ($factura && $factura->detalles->isNotEmpty()) {
            $detalles = $factura->detalles->all();
        } elseif ($detallesIniciales !== []) {
            $detalles = array_map(fn ($d) => (object) $d, $detallesIniciales);
        } else {
            $detalles = [(object)['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 0, 'impuesto_id' => null]];
        }
    }
    $detalles = is_array($detalles) ? $detalles : collect($detalles)->all();
    $modoManual = !empty($modoManual);
    $tipoReceptor = old('tipo_receptor', ($factura && $factura->esOcasional()) ? 'ocasional' : 'registrado');
@endphp

<div class="space-y-6">
    @if($modoManual)
        <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-700/30">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Receptor de la factura</p>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2 cursor-pointer text-gray-800 dark:text-gray-200">
                    <input type="radio" name="tipo_receptor" value="registrado" class="text-purple-600 focus:ring-purple-500" {{ $tipoReceptor === 'registrado' ? 'checked' : '' }}>
                    Cliente registrado
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer text-gray-800 dark:text-gray-200">
                    <input type="radio" name="tipo_receptor" value="ocasional" class="text-purple-600 focus:ring-purple-500" {{ $tipoReceptor === 'ocasional' ? 'checked' : '' }}>
                    Factura ocasional (sin cliente)
                </label>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div @if($modoManual) id="bloque-cliente-registrado" @endif class="{{ $modoManual && $tipoReceptor === 'ocasional' ? 'hidden' : '' }}">
            @if(isset($clienteSeleccionado) && $clienteSeleccionado)
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente</label>
                <p class="px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-gray-100">
                    {{ $clienteSeleccionado->nombre }} {{ $clienteSeleccionado->apellido }}
                    @if($clienteSeleccionado->cedula)
                        <span class="text-gray-500 dark:text-gray-400">({{ $clienteSeleccionado->cedula }})</span>
                    @endif
                </p>
                <input type="hidden" name="cliente_id" value="{{ $clienteSeleccionado->cliente_id }}">
            @else
                <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
                <select name="cliente_id" id="cliente_id" @unless($modoManual) required @endunless class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Seleccione cliente</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->cliente_id }}" {{ old('cliente_id', $factura?->cliente_id) == $c->cliente_id ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }} ({{ $c->cedula }})</option>
                    @endforeach
                </select>
            @endif
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

    @if($modoManual)
        <div id="bloque-receptor-nuevo" class="{{ $tipoReceptor === 'ocasional' ? '' : 'hidden' }} rounded-lg border border-purple-200 dark:border-purple-800/50 p-4 space-y-4 bg-purple-50/40 dark:bg-purple-900/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">Datos del receptor para factura ocasional. No se crea ni modifica ningún cliente. Puede consultar el padrón para autocompletar.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2 flex flex-col sm:flex-row gap-2 sm:items-end">
                    <div class="flex-1">
                        <label for="receptor_cedula" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cédula / RUC *</label>
                        <input type="text" name="receptor_cedula" id="receptor_cedula" value="{{ old('receptor_cedula', $factura?->receptor_documento) }}" maxlength="30"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('receptor_cedula')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" id="btn-buscar-padron-factura" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm whitespace-nowrap disabled:opacity-50">
                        Buscar en padrón
                    </button>
                </div>
                <div>
                    <label for="receptor_nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                    <input type="text" name="receptor_nombre" id="receptor_nombre" value="{{ old('receptor_nombre', $factura?->receptor_nombre) }}" maxlength="100"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('receptor_nombre')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="receptor_apellido" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Apellido</label>
                    <input type="text" name="receptor_apellido" id="receptor_apellido" value="{{ old('receptor_apellido', $factura?->receptor_apellido) }}" maxlength="100"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('receptor_apellido')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="receptor_direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
                    <input type="text" name="receptor_direccion" id="receptor_direccion" value="{{ old('receptor_direccion', $factura?->receptor_direccion) }}" maxlength="255"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('receptor_direccion')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="receptor_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo</label>
                    <input type="email" name="receptor_email" id="receptor_email" value="{{ old('receptor_email', $factura?->receptor_email) }}" maxlength="100"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('receptor_email')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="receptor_telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                    <input type="text" name="receptor_telefono" id="receptor_telefono" value="{{ old('receptor_telefono', $factura?->receptor_telefono) }}" maxlength="30"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('receptor_telefono')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <p id="receptor-padron-msg" class="text-sm hidden"></p>
        </div>
    @endif

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
        @php
            $timbradoBloqueado = ! empty($prefill['numero_timbrado']);
            $claseTimbrado = 'w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 read-only:bg-gray-100 read-only:dark:bg-gray-800 read-only:text-gray-600 read-only:dark:text-gray-300 read-only:cursor-default';
            $claseTimbradoFecha = $claseTimbrado.' dark:[color-scheme:dark]';
        @endphp
        <div>
            <label for="numero_timbrado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Timbrado (SET)</label>
            <input type="text" name="numero_timbrado" id="numero_timbrado" value="{{ old('numero_timbrado', $factura?->numero_timbrado ?? ($prefill['numero_timbrado'] ?? null)) }}" maxlength="20" class="{{ $claseTimbrado }}" {{ $timbradoBloqueado ? 'readonly' : '' }}>
            @if($timbradoBloqueado)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Desde configuración SIFEN</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label for="timbrado_vigencia_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vigencia timbrado desde</label>
            <input type="date" name="timbrado_vigencia_desde" id="timbrado_vigencia_desde" value="{{ old('timbrado_vigencia_desde', $factura?->timbrado_vigencia_desde?->format('Y-m-d') ?? ($prefill['timbrado_vigencia_desde'] ?? null)) }}" class="{{ $claseTimbradoFecha }}" {{ $timbradoBloqueado ? 'readonly' : '' }}>
        </div>
        <div>
            <label for="timbrado_vigencia_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vigencia timbrado hasta</label>
            <input type="date" name="timbrado_vigencia_hasta" id="timbrado_vigencia_hasta" value="{{ old('timbrado_vigencia_hasta', $factura?->timbrado_vigencia_hasta?->format('Y-m-d') ?? ($prefill['timbrado_vigencia_hasta'] ?? null)) }}" class="{{ $claseTimbradoFecha }}" {{ $timbradoBloqueado ? 'readonly' : '' }}>
        </div>
        <div>
            <label for="establecimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Establecimiento</label>
            <input type="number" name="establecimiento" id="establecimiento" value="{{ old('establecimiento', $factura?->establecimiento ?? ($prefill['establecimiento'] ?? 1)) }}" min="1" max="255" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>
        <div>
            <label for="punto_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Punto emisión</label>
            <input type="number" name="punto_emision" id="punto_emision" value="{{ old('punto_emision', $factura?->punto_emision ?? ($prefill['punto_emision'] ?? 1)) }}" min="1" max="255" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
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
                        <td class="px-3 py-2">
                            <input type="text" name="detalles[{{ $idx }}][descripcion]" value="{{ $item->descripcion ?? '' }}" required class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Descripción">
                            @if(!empty($item->servicio_id))
                                <input type="hidden" name="detalles[{{ $idx }}][servicio_id]" value="{{ $item->servicio_id }}">
                            @endif
                        </td>
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

@if($modoManual)
(function() {
    var bloqueRegistrado = document.getElementById('bloque-cliente-registrado');
    var bloqueNuevo = document.getElementById('bloque-receptor-nuevo');
    var selectCliente = document.getElementById('cliente_id');
    var radios = document.querySelectorAll('input[name="tipo_receptor"]');
    var btnPadron = document.getElementById('btn-buscar-padron-factura');
    var msgEl = document.getElementById('receptor-padron-msg');
    var consultarPadronUrl = @json(route('facturas.consultar-padron-receptor'));
    var verificarCedulaUrl = @json(route('facturas.verificar-receptor-documento'));
    var csrf = document.querySelector('meta[name="csrf-token"]');

    function tipoActual() {
        var checked = document.querySelector('input[name="tipo_receptor"]:checked');
        return checked ? checked.value : 'registrado';
    }

    function actualizarBloques() {
        var esOcasional = tipoActual() === 'ocasional';
        if (bloqueRegistrado) bloqueRegistrado.classList.toggle('hidden', esOcasional);
        if (bloqueNuevo) bloqueNuevo.classList.toggle('hidden', !esOcasional);
        if (selectCliente) {
            if (esOcasional) {
                selectCliente.removeAttribute('required');
                selectCliente.value = '';
            } else {
                selectCliente.setAttribute('required', 'required');
            }
        }
        ['receptor_cedula', 'receptor_nombre'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (esOcasional) el.setAttribute('required', 'required');
            else el.removeAttribute('required');
        });
    }

    radios.forEach(function(r) { r.addEventListener('change', actualizarBloques); });
    actualizarBloques();

    function showMsg(text, isError) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.classList.remove('hidden', 'text-green-700', 'dark:text-green-400', 'text-amber-700', 'dark:text-amber-400', 'text-red-600', 'dark:text-red-400');
        msgEl.classList.add(isError ? 'text-amber-700' : 'text-green-700', isError ? 'dark:text-amber-400' : 'dark:text-green-400');
    }

    if (btnPadron) {
        btnPadron.addEventListener('click', function() {
            var cedula = (document.getElementById('receptor_cedula') || {}).value;
            cedula = cedula ? cedula.trim() : '';
            if (!cedula) {
                showMsg('Ingrese la cédula o RUC.', true);
                return;
            }
            btnPadron.disabled = true;
            fetch(verificarCedulaUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.content : ''
                },
                body: JSON.stringify({ cedula: cedula })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.existe && data.cliente) {
                    showMsg('Este documento ya está registrado como cliente. Seleccione «Cliente registrado» si desea facturarle como cliente.', true);
                    document.getElementById('receptor_nombre').value = data.cliente.nombre || '';
                    document.getElementById('receptor_apellido').value = data.cliente.apellido || '';
                    return null;
                }
                return fetch(consultarPadronUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf ? csrf.content : ''
                    },
                    body: JSON.stringify({ cedula: cedula })
                });
            })
            .then(function(res) {
                if (!res) return;
                return res.json().then(function(data) { return { ok: res.ok, data: data }; });
            })
            .then(function(result) {
                if (!result) return;
                if (!result.ok || !result.data.encontrado) {
                    showMsg(result.data.mensaje || result.data.error || 'No encontrado en padrón. Complete nombre y datos manualmente.', true);
                    return;
                }
                var d = result.data;
                document.getElementById('receptor_cedula').value = d.cedula || cedula;
                document.getElementById('receptor_nombre').value = d.nombre || '';
                document.getElementById('receptor_apellido').value = d.apellido || '';
                var dir = [d.direccion, d.domicilio].filter(Boolean).join(' ').trim();
                if (dir) document.getElementById('receptor_direccion').value = dir;
                showMsg('Datos cargados desde el padrón.', false);
            })
            .catch(function() {
                showMsg('Error al consultar. Complete los datos manualmente.', true);
            })
            .finally(function() { btnPadron.disabled = false; });
        });
    }
})();
@endif
</script>
