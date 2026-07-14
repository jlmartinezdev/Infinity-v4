@extends('layouts.app')

@section('title', 'Nueva factura electrónica')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('facturas.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a facturas</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Nueva factura electrónica</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleccione uno o varios clientes. Período a facturar: <strong class="capitalize">{{ $mesLabel }}</strong>.</p>
        </div>
        <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 text-sm shrink-0">
            Datos manuales
        </a>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800 text-sm">{{ session('warning') }}</div>
    @endif
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Clientes</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($totalActivos, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-800/50 p-4 shadow-sm">
            <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wide">Emitidos · {{ $mesLabel }}</p>
            <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">{{ number_format($emitidosMes, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800/50 p-4 shadow-sm">
            <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Sin emitir · {{ $mesLabel }}</p>
            <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ number_format($pendientesMes, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('facturas.create') }}" class="p-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col lg:flex-row gap-3 lg:items-end">
                <div class="sm:w-56">
                    <label for="periodo" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Período a facturar</label>
                    <select name="periodo" id="periodo"
                            class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            onchange="this.form.submit()">
                        @foreach($periodosOpciones as $op)
                            <option value="{{ $op['value'] }}" {{ ($periodoYm ?? '') === $op['value'] ? 'selected' : '' }}>
                                {{ $op['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-0">
                    <label for="buscar" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Buscar cliente</label>
                    <input type="text" name="buscar" id="buscar" value="{{ request('buscar') }}"
                           placeholder="Nombre, apellido o cédula/RUC…"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2.5">
                    <input type="checkbox" name="solo_pendientes" value="1" {{ request()->boolean('solo_pendientes') ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                    Solo sin emitir en el período
                </label>
                <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 text-sm">Buscar</button>
            </div>
        </form>

        <form method="POST" action="{{ route('facturas.store-masivo') }}" id="form-masivo"
              onsubmit="return confirmarMasivo(event);">
            @csrf
            <input type="hidden" name="emitir" id="input-emitir" value="0">
            <input type="hidden" name="periodo" id="input-periodo" value="{{ $periodoYm }}">
            <div id="cliente-ids-persistidos"></div>

            <div id="barra-masivo" class="hidden sticky top-0 z-10 px-4 py-3 border-b border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm text-purple-900 dark:text-purple-100">
                    <span id="contador-seleccion" class="font-semibold">0</span> cliente(s) seleccionado(s)
                    <span class="text-xs text-purple-700 dark:text-purple-300"> · período <span class="capitalize">{{ $mesLabel }}</span> · se mantiene al cambiar de página · máx. 50</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btn-limpiar-seleccion"
                            class="px-3 py-2 text-sm font-medium rounded-lg text-purple-800 dark:text-purple-200 hover:bg-purple-100 dark:hover:bg-purple-900/50">
                        Limpiar
                    </button>
                    <button type="submit" name="accion" value="borrador"
                            class="px-4 py-2 text-sm font-medium rounded-lg border border-purple-300 dark:border-purple-600 text-purple-800 dark:text-purple-100 bg-white dark:bg-gray-800 hover:bg-purple-100 dark:hover:bg-purple-900/50">
                        Crear borradores
                    </button>
                    <button type="submit" name="accion" value="emitir"
                            class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-purple-600 hover:bg-purple-700">
                        Crear y enviar a SIFEN
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">
                                <input type="checkbox" id="seleccionar-todos"
                                       class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500"
                                       title="Seleccionar todos los elegibles de esta página">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Documento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado mes</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Última emisión</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                        @forelse($clientes as $cliente)
                            @php
                                $emision = $emisionesMes->get($cliente->cliente_id);
                                $emitidoMes = $emision !== null;
                                $sinDocumento = blank($cliente->cedula);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $emitidoMes ? 'bg-green-50/60 dark:bg-green-900/10' : '' }}">
                                <td class="px-4 py-3">
                                    @if(! $sinDocumento)
                                        <input type="checkbox" value="{{ $cliente->cliente_id }}"
                                               class="chk-cliente rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $cliente->nombre }} {{ $cliente->apellido }}
                                    </p>
                                    @if($cliente->telefono)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cliente->telefono }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono">
                                    @if($sinDocumento)
                                        <span class="text-amber-600 dark:text-amber-400">Sin documento</span>
                                    @else
                                        {{ $cliente->cedula }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($emitidoMes)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Emitido ({{ $emision->cantidad }})
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                            Pendiente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    @if($emitidoMes && $emision->ultima_fecha)
                                        {{ \Carbon\Carbon::parse($emision->ultima_fecha)->format('d/m/Y') }}
                                        @if($emision->ultima_factura_id)
                                            · <a href="{{ route('facturas.show', $emision->ultima_factura_id) }}" class="text-purple-600 dark:text-purple-400 hover:underline">#{{ $emision->ultima_factura_id }}</a>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($sinDocumento)
                                        <span class="text-xs text-gray-400 dark:text-gray-500">Complete cédula/RUC</span>
                                    @else
                                        <a href="{{ route('facturas.create-cliente', ['cliente' => $cliente, 'periodo' => $periodoYm]) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg text-white {{ $emitidoMes ? 'bg-gray-600 hover:bg-gray-700' : 'bg-purple-600 hover:bg-purple-700' }}">
                                            {{ $emitidoMes ? 'Nueva DE' : 'Individual' }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay clientes que coincidan con la búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        @if($clientes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                {{ $clientes->links() }}
            </div>
        @endif
    </div>

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        El <strong>período a facturar</strong> define las fechas de las líneas (plan + prorrateo). La fecha de emisión del DE será la del día en que se emite.
        Se considera <strong>emitido</strong> cuando el cliente ya tiene una factura electrónica emitida para ese período.
        Máximo 50 clientes por tanda.
    </p>
</div>

<script>
(function () {
    const STORAGE_KEY = 'facturas_masivo_cliente_ids';
    const STORAGE_PERIODO_KEY = 'facturas_masivo_periodo';
    const MAX = 50;
    const periodoActual = @json($periodoYm);
    const periodoLabel = @json($mesLabel);
    const checks = () => Array.from(document.querySelectorAll('.chk-cliente'));
    const barra = document.getElementById('barra-masivo');
    const contador = document.getElementById('contador-seleccion');
    const todos = document.getElementById('seleccionar-todos');
    const inputEmitir = document.getElementById('input-emitir');
    const contenedorHidden = document.getElementById('cliente-ids-persistidos');

    // Si cambió el período, limpiar selección previa de otro mes.
    try {
        const periodoGuardado = sessionStorage.getItem(STORAGE_PERIODO_KEY);
        if (periodoGuardado && periodoGuardado !== periodoActual) {
            sessionStorage.removeItem(STORAGE_KEY);
        }
        sessionStorage.setItem(STORAGE_PERIODO_KEY, periodoActual);
    } catch (e) {}

    function leerSeleccion() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr.map(String) : [];
        } catch (e) {
            return [];
        }
    }

    function guardarSeleccion(ids) {
        const unicos = Array.from(new Set(ids.map(String)));
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(unicos));
        return unicos;
    }

    function sincronizarPaginaHaciaStorage() {
        let ids = leerSeleccion();
        checks().forEach(function (c) {
            const id = String(c.value);
            if (c.checked) {
                if (!ids.includes(id)) {
                    ids.push(id);
                }
            } else {
                ids = ids.filter(function (x) { return x !== id; });
            }
        });
        return guardarSeleccion(ids);
    }

    function restaurarChecksDesdeStorage() {
        const ids = new Set(leerSeleccion());
        checks().forEach(function (c) {
            c.checked = ids.has(String(c.value));
        });
    }

    function actualizarBarra() {
        const ids = leerSeleccion();
        const n = ids.length;
        contador.textContent = String(n);
        barra.classList.toggle('hidden', n === 0);
        if (todos) {
            const elegibles = checks();
            const marcadosPagina = elegibles.filter(function (c) { return c.checked; }).length;
            todos.checked = elegibles.length > 0 && marcadosPagina === elegibles.length;
            todos.indeterminate = marcadosPagina > 0 && marcadosPagina < elegibles.length;
        }
    }

    function actualizar() {
        sincronizarPaginaHaciaStorage();
        actualizarBarra();
    }

    function inyectarHiddenParaSubmit() {
        if (!contenedorHidden) {
            return;
        }
        contenedorHidden.innerHTML = '';
        leerSeleccion().forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cliente_ids[]';
            input.value = id;
            contenedorHidden.appendChild(input);
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('chk-cliente') || e.target.id === 'seleccionar-todos') {
            if (e.target.id === 'seleccionar-todos') {
                const marcar = e.target.checked;
                let ids = leerSeleccion();
                checks().forEach(function (c) {
                    c.checked = marcar;
                    const id = String(c.value);
                    if (marcar) {
                        if (!ids.includes(id)) {
                            ids.push(id);
                        }
                    } else {
                        ids = ids.filter(function (x) { return x !== id; });
                    }
                });
                if (marcar && ids.length > MAX) {
                    alert('Máximo ' + MAX + ' clientes por tanda.');
                    ids = ids.slice(0, MAX);
                    const permitidos = new Set(ids);
                    checks().forEach(function (c) {
                        c.checked = permitidos.has(String(c.value));
                    });
                    guardarSeleccion(ids);
                } else {
                    guardarSeleccion(ids);
                }
            } else {
                const ids = sincronizarPaginaHaciaStorage();
                if (ids.length > MAX) {
                    e.target.checked = false;
                    sincronizarPaginaHaciaStorage();
                    alert('Máximo ' + MAX + ' clientes por tanda.');
                }
            }
            actualizarBarra();
        }
    });

    const btnLimpiar = document.getElementById('btn-limpiar-seleccion');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            sessionStorage.removeItem(STORAGE_KEY);
            checks().forEach(function (c) { c.checked = false; });
            actualizarBarra();
        });
    }

    window.confirmarMasivo = function (event) {
        sincronizarPaginaHaciaStorage();
        const ids = leerSeleccion();
        const n = ids.length;
        if (n === 0) {
            alert('Seleccione al menos un cliente.');
            return false;
        }
        if (n > MAX) {
            alert('Máximo ' + MAX + ' clientes por tanda.');
            return false;
        }
        const submitter = event.submitter;
        const emitir = submitter && submitter.value === 'emitir';
        inputEmitir.value = emitir ? '1' : '0';
        const msg = emitir
            ? '¿Crear y enviar a SIFEN ' + n + ' factura(s) del período ' + periodoLabel + '?'
            : '¿Crear ' + n + ' borrador(es) del período ' + periodoLabel + '?';
        if (!confirm(msg)) {
            return false;
        }
        inyectarHiddenParaSubmit();
        sessionStorage.removeItem(STORAGE_KEY);
        return true;
    };

    restaurarChecksDesdeStorage();
    actualizarBarra();
})();
</script>
@endsection

