@csrf
@isset($ticket)
    @method('PUT')
@endisset

@php
    $ticket = $ticket ?? null;
    $clientePresetId = $clientePresetId ?? null;
@endphp

<div class="space-y-6">
    <div>
        <label for="ticket_asunto_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asunto *</label>
        <select name="ticket_asunto_id" id="ticket_asunto_id" required
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <option value="">Seleccione un asunto</option>
            @foreach ($asuntos as $a)
                <option value="{{ $a->id }}" {{ old('ticket_asunto_id', $ticket?->ticket_asunto_id) == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
            @endforeach
        </select>
        @error('ticket_asunto_id')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="prioridad" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioridad</label>
            <select name="prioridad" id="prioridad"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach (App\Models\Ticket::prioridades() as $key => $label)
                    <option value="{{ $key }}" {{ old('prioridad', $ticket?->prioridad ?? 'media') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('prioridad')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="reportado_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reportado desde</label>
            <select name="reportado_desde" id="reportado_desde"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">—</option>
                @foreach (App\Models\Ticket::reportadoDesdeOpciones() as $key => $label)
                    <option value="{{ $key }}" {{ old('reportado_desde', $ticket?->reportado_desde) == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('reportado_desde')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="cliente_buscar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente</label>
            @php
                $clienteIdInicial = old('cliente_id', $ticket?->cliente_id ?? ($clientePresetId ?? null));
                $clienteLabelInicial = '';
                if ($ticket?->cliente) {
                    $clienteLabelInicial = trim($ticket->cliente->nombre . ' ' . $ticket->cliente->apellido) . ($ticket->cliente->cedula ? ' (' . $ticket->cliente->cedula . ')' : '');
                }
                if ($clienteIdInicial && !$clienteLabelInicial && isset($clientes)) {
                    $cInicial = $clientes->firstWhere('cliente_id', $clienteIdInicial);
                    $clienteLabelInicial = $cInicial ? trim($cInicial->nombre . ' ' . $cInicial->apellido) . ($cInicial->cedula ? ' (' . $cInicial->cedula . ')' : '') : '';
                }
            @endphp
            <div id="cliente-buscar-container" class="relative" data-initial-id="{{ $clienteIdInicial }}" data-initial-label="{{ $clienteLabelInicial }}">
                <input type="text" id="cliente_buscar" autocomplete="off"
                    value="{{ $clienteLabelInicial }}"
                    placeholder="Buscar por nombre, apellido o cédula..."
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <input type="hidden" name="cliente_id" id="cliente_id" value="{{ $clienteIdInicial }}">
                <button type="button" id="cliente_limpiar" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 {{ $clienteIdInicial ? '' : 'hidden' }}" title="Quitar cliente" aria-label="Quitar cliente">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div id="cliente_resultados" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden"></div>
            </div>
            @error('cliente_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="pedido_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pedido</label>
            <select name="pedido_id" id="pedido_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Sin pedido</option>
                @foreach ($pedidos as $p)
                    <option value="{{ $p->pedido_id }}" {{ old('pedido_id', $ticket?->pedido_id) == $p->pedido_id ? 'selected' : '' }}>#{{ $p->pedido_id }} - {{ $p->cliente?->nombre ?? '' }} {{ $p->cliente?->apellido ?? '' }}</option>
                @endforeach
            </select>
            @error('pedido_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="3"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            placeholder="Detalle del problema o solicitud...">{{ old('descripcion', $ticket?->descripcion) }}</textarea>
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
            <select name="estado" id="estado" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach (App\Models\Ticket::estados() as $key => $label)
                    <option value="{{ $key }}" {{ old('estado', $ticket?->estado ?? 'pendiente') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('estado')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="asignado_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asignado a</label>
            <select name="asignado_id" id="asignado_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">Sin asignar</option>
                @foreach ($tecnicos as $t)
                    <option value="{{ $t->usuario_id }}" {{ old('asignado_id', $ticket?->asignado_id) == $t->usuario_id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
            @error('asignado_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="imagen" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Imagen</label>
        @if($ticket?->imagen)
            <div class="mb-2">
                <img src="{{ asset('storage/' . $ticket->imagen) }}" alt="Imagen del ticket" class="max-h-40 rounded-lg border border-gray-200 dark:border-gray-600 object-cover">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Imagen actual. Suba otra para reemplazar.</p>
            </div>
        @endif
        <input type="file" name="imagen" id="imagen" accept="image/*"
            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        @error('imagen')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="2"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            placeholder="Notas internas...">{{ old('observaciones', $ticket?->observaciones) }}</textarea>
        @error('observaciones')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3 items-center">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $ticket ? 'Actualizar ticket' : 'Crear ticket' }}
        </button>
        @if($ticket)
            <a href="{{ route('tickets.crear-agenda', $ticket) }}"
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                Crear agenda
            </a>
        @endif
        <a href="{{ route('tickets.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>

<script>
(function() {
    var container = document.getElementById('cliente-buscar-container');
    var input = document.getElementById('cliente_buscar');
    var hidden = document.getElementById('cliente_id');
    var resultados = document.getElementById('cliente_resultados');
    var btnLimpiar = document.getElementById('cliente_limpiar');
    var debounceTimer = null;
    var urlBuscar = '{{ route("clientes.buscar") }}';

    function mostrarResultados(items) {
        resultados.innerHTML = '';
        if (!items || items.length === 0) {
            resultados.classList.add('hidden');
            return;
        }
        items.forEach(function(c) {
            var label = (c.nombre + ' ' + c.apellido).trim() + (c.cedula ? ' (' + c.cedula + ')' : '');
            var div = document.createElement('button');
            div.type = 'button';
            div.className = 'w-full text-left px-4 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/30 text-gray-900 dark:text-gray-100 text-sm border-b border-gray-100 dark:border-gray-600 last:border-0 first:rounded-t-lg last:rounded-b-lg';
            div.textContent = label;
            div.dataset.id = c.cliente_id;
            div.dataset.label = label;
            div.addEventListener('click', function() {
                hidden.value = c.cliente_id;
                input.value = label;
                input.readOnly = true;
                resultados.classList.add('hidden');
                resultados.innerHTML = '';
                btnLimpiar.classList.remove('hidden');
            });
            resultados.appendChild(div);
        });
        resultados.classList.remove('hidden');
    }

    function buscar() {
        var q = input.value.trim();
        if (q.length < 2) {
            resultados.classList.add('hidden');
            resultados.innerHTML = '';
            return;
        }
        fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function(data) {
            mostrarResultados(Array.isArray(data) ? data : []);
        }).catch(function() { mostrarResultados([]); });
    }

    input.addEventListener('input', function() {
        if (input.readOnly) return;
        clearTimeout(debounceTimer);
        hidden.value = '';
        btnLimpiar.classList.add('hidden');
        debounceTimer = setTimeout(buscar, 250);
    });

    input.addEventListener('focus', function() {
        if (input.readOnly) return;
        var q = input.value.trim();
        if (q.length >= 2) buscar();
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            resultados.classList.add('hidden');
            input.blur();
        }
    });

    btnLimpiar.addEventListener('click', function() {
        hidden.value = '';
        input.value = '';
        input.readOnly = false;
        input.focus();
        resultados.classList.add('hidden');
        btnLimpiar.classList.add('hidden');
    });

    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) resultados.classList.add('hidden');
    });

    if (container.dataset.initialId) {
        input.readOnly = true;
    }
})();
</script>
