@extends('layouts.app')

@section('title', 'Nueva factura electrónica')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('facturas.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a facturas</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Nueva factura electrónica</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleccione uno o varios clientes. Período a facturar: <strong class="capitalize">{{ $mesLabel }}</strong>. La fecha de emisión del DE se elige abajo.</p>
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

        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Listas de clientes</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Marque clientes, guarde con un nombre y facture esa lista en lotes de hasta {{ \App\Support\FacturaElectronicaListaLote::MAX }}.
                        Puede ir sumando de a 50 (páginas) a la misma lista.
                    </p>
                </div>
            </div>
            @if(($listasFe ?? []) === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay listas. Seleccione clientes y pulse «Guardar lista».</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    @foreach($listasFe as $lista)
                        <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-3 py-2.5 bg-gray-50/80 dark:bg-gray-900/30">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $lista['nombre'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $lista['total'] }} cliente(s)
                                    · {{ $lista['pendientes'] }} pendiente(s) en {{ $mesLabel }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5 shrink-0">
                                <button type="button"
                                        class="btn-cargar-lista px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        data-ids="{{ implode(',', $lista['cliente_ids']) }}">
                                    Cargar
                                </button>
                                <button type="button"
                                        class="btn-facturar-lista px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                        data-url="{{ route('facturas.listas.facturar', $lista['id']) }}"
                                        data-nombre="{{ $lista['nombre'] }}"
                                        data-pendientes="{{ $lista['pendientes'] }}"
                                        @if($lista['pendientes'] < 1) disabled @endif>
                                    Facturar lote
                                </button>
                                <form method="POST" action="{{ route('facturas.listas.destroy', $lista['id']) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar la lista «{{ $lista['nombre'] }}»?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <form method="POST" action="{{ route('facturas.store-masivo') }}" id="form-masivo">
            @csrf
            <input type="hidden" name="emitir" id="input-emitir" value="0">
            <input type="hidden" name="periodo" id="input-periodo" value="{{ $periodoYm }}">
            <div id="cliente-ids-persistidos"></div>

            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="sm:w-56">
                    <label for="fecha_emision" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha de emisión del DE</label>
                    <input type="date" name="fecha_emision" id="fecha_emision"
                           value="{{ old('fecha_emision', $fechaEmision) }}"
                           max="{{ now()->toDateString() }}"
                           required
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('fecha_emision')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 pb-2 sm:max-w-xl">
                    Sale en el XML de SIFEN. El período de arriba es el mes facturado (líneas); esta fecha es la del documento.
                </p>
            </div>

            <div id="barra-masivo" class="hidden sticky top-0 z-10 px-4 py-3 border-b border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/30 flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-purple-900 dark:text-purple-100">
                        <span id="contador-seleccion" class="font-semibold">0</span> cliente(s) seleccionado(s)
                        <span class="text-xs text-purple-700 dark:text-purple-300"> · período <span class="capitalize">{{ $mesLabel }}</span> · se mantiene al cambiar de página · máx. 50</span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="btn-guardar-lista"
                                class="px-3 py-2 text-sm font-medium rounded-lg border border-purple-300 dark:border-purple-600 text-purple-800 dark:text-purple-100 bg-white dark:bg-gray-800 hover:bg-purple-100 dark:hover:bg-purple-900/50">
                            Guardar lista
                        </button>
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
                <div class="flex flex-col sm:flex-row sm:items-end gap-3 pt-1 border-t border-purple-200/70 dark:border-purple-800/70">
                    <div class="sm:w-56">
                        <label for="monto_modo" class="block text-xs font-medium text-purple-800 dark:text-purple-200 mb-1">Monto por cliente</label>
                        <select name="monto_modo" id="monto_modo"
                                class="w-full px-3 py-2 rounded-lg border border-purple-300 dark:border-purple-600 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                            <option value="plan">Precio del plan (normal)</option>
                            <option value="500000">500.000 Gs.</option>
                            <option value="1000000">1.000.000 Gs.</option>
                            <option value="otro">Otro monto…</option>
                        </select>
                    </div>
                    <div id="wrap-monto-otro" class="hidden sm:w-44">
                        <label for="monto_fijo" class="block text-xs font-medium text-purple-800 dark:text-purple-200 mb-1">Monto (Gs.)</label>
                        <input type="number" name="monto_fijo" id="monto_fijo" min="1" step="1" placeholder="Ej. 750000"
                               class="w-full px-3 py-2 rounded-lg border border-purple-300 dark:border-purple-600 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    </div>
                    <p class="text-xs text-purple-700 dark:text-purple-300 pb-2 sm:max-w-md">
                        Con monto fijo se usa la misma descripción (plan + período), pero el importe por cliente es el elegido.
                    </p>
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
        El <strong>período a facturar</strong> define las fechas de las líneas (plan + prorrateo).
        La <strong>fecha de emisión</strong> es la del documento electrónico (SIFEN) y no puede ser posterior a hoy.
        Se considera <strong>emitido</strong> cuando el cliente ya tiene una factura electrónica emitida para ese período.
        Máximo 50 clientes por tanda inmediata. Las listas guardadas se facturan de a {{ \App\Support\FacturaElectronicaListaLote::MAX }}.
    </p>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
<script>
(function () {
    const STORAGE_KEY = 'facturas_masivo_cliente_ids';
    const STORAGE_PERIODO_KEY = 'facturas_masivo_periodo';
    const STORAGE_FECHA_KEY = 'facturas_masivo_fecha_emision';
    const MAX = 50;
    const periodoActual = @json($periodoYm);
    const periodoLabel = @json($mesLabel);
    const checks = () => Array.from(document.querySelectorAll('.chk-cliente'));
    const barra = document.getElementById('barra-masivo');
    const contador = document.getElementById('contador-seleccion');
    const todos = document.getElementById('seleccionar-todos');
    const inputEmitir = document.getElementById('input-emitir');
    const contenedorHidden = document.getElementById('cliente-ids-persistidos');
    const montoModo = document.getElementById('monto_modo');
    const wrapMontoOtro = document.getElementById('wrap-monto-otro');
    const montoFijoInput = document.getElementById('monto_fijo');
    const fechaEmisionInput = document.getElementById('fecha_emision');
    const formMasivo = document.getElementById('form-masivo');
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const urlStoreLista = @json(route('facturas.listas.store'));
    const listasFe = @json($listasFe ?? []);
    const loteMax = @json(\App\Support\FacturaElectronicaListaLote::MAX);

    function swalTheme() {
        if (!document.documentElement.classList.contains('dark')) {
            return {};
        }
        return {
            background: '#1f2937',
            color: '#f3f4f6',
            customClass: { popup: 'border border-gray-700' },
        };
    }

    function avisar(msg, icon) {
        if (typeof Swal === 'undefined') {
            window.alert(msg);
            return;
        }
        Swal.fire(Object.assign({
            icon: icon || 'warning',
            title: msg,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#7c3aed',
        }, swalTheme()));
    }

    function etiquetaMonto() {
        if (!montoModo) return 'precio del plan';
        if (montoModo.value === '500000') return '500.000 Gs.';
        if (montoModo.value === '1000000') return '1.000.000 Gs.';
        if (montoModo.value === 'otro') {
            const v = Number(montoFijoInput && montoFijoInput.value ? montoFijoInput.value : 0);
            return v > 0 ? (v.toLocaleString('es-PY') + ' Gs.') : 'otro monto';
        }
        return 'precio del plan';
    }

    function toggleMontoOtro() {
        if (!montoModo || !wrapMontoOtro) return;
        const show = montoModo.value === 'otro';
        wrapMontoOtro.classList.toggle('hidden', !show);
        if (!show && montoFijoInput) montoFijoInput.value = '';
    }

    if (montoModo) {
        montoModo.addEventListener('change', toggleMontoOtro);
        toggleMontoOtro();
    }

    function fechaEmisionValida() {
        if (!fechaEmisionInput || !fechaEmisionInput.value) {
            return false;
        }
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const partes = fechaEmisionInput.value.split('-');
        if (partes.length !== 3) {
            return false;
        }
        const elegida = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
        return elegida.getTime() <= hoy.getTime();
    }

    function etiquetaFechaEmision() {
        if (!fechaEmisionInput || !fechaEmisionInput.value) {
            return '';
        }
        const partes = fechaEmisionInput.value.split('-');
        if (partes.length !== 3) {
            return fechaEmisionInput.value;
        }
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function restaurarFechaEmision() {
        if (!fechaEmisionInput) {
            return;
        }
        try {
            const guardada = sessionStorage.getItem(STORAGE_FECHA_KEY);
            if (guardada && /^\d{4}-\d{2}-\d{2}$/.test(guardada)) {
                fechaEmisionInput.value = guardada;
            }
        } catch (e) {}
    }

    function guardarFechaEmision() {
        if (!fechaEmisionInput) {
            return;
        }
        try {
            sessionStorage.setItem(STORAGE_FECHA_KEY, fechaEmisionInput.value || '');
        } catch (e) {}
    }

    if (fechaEmisionInput) {
        restaurarFechaEmision();
        fechaEmisionInput.addEventListener('change', guardarFechaEmision);
    }

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
                    avisar('Máximo ' + MAX + ' clientes por tanda.');
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
                    avisar('Máximo ' + MAX + ' clientes por tanda.');
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
            avisar('Seleccione al menos un cliente.');
            return false;
        }
        if (n > MAX) {
            avisar('Máximo ' + MAX + ' clientes por tanda.');
            return false;
        }
        if (montoModo && montoModo.value === 'otro') {
            const v = Number(montoFijoInput && montoFijoInput.value ? montoFijoInput.value : 0);
            if (!v || v < 1) {
                avisar('Ingrese el monto fijo en guaraníes.');
                return false;
            }
        }
        if (!fechaEmisionValida()) {
            avisar('Elija una fecha de emisión válida (hoy o anterior).');
            if (fechaEmisionInput) {
                fechaEmisionInput.focus();
            }
            return false;
        }
        guardarFechaEmision();
        const submitter = event.submitter;
        const emitir = submitter && submitter.value === 'emitir';
        inputEmitir.value = emitir ? '1' : '0';
        const montoTxt = etiquetaMonto();
        const fechaTxt = etiquetaFechaEmision();
        const form = event.target;
        const title = emitir ? '¿Crear y enviar a SIFEN?' : '¿Crear borradores?';
        const text = emitir
            ? n + ' factura(s) del período ' + periodoLabel + ', emisión ' + fechaTxt + ', monto ' + montoTxt + '.'
            : n + ' borrador(es) del período ' + periodoLabel + ', emisión ' + fechaTxt + ', monto ' + montoTxt + '.';
        const confirmText = emitir ? 'Sí, enviar a SIFEN' : 'Sí, crear borradores';

        const enviar = function () {
            inyectarHiddenParaSubmit();
            sessionStorage.removeItem(STORAGE_KEY);
            form.dataset.swalOk = '1';
            HTMLFormElement.prototype.submit.call(form);
        };

        if (typeof Swal === 'undefined') {
            if (!window.confirm((emitir ? '¿Crear y enviar a SIFEN ' : '¿Crear ') + text)) {
                return false;
            }
            enviar();
            return false;
        }

        Swal.fire(Object.assign({
            icon: 'question',
            title: title,
            text: text,
            showCancelButton: true,
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#6b7280',
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
        }, swalTheme())).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }
            enviar();
        });
        return false;
    };

    if (formMasivo) {
        formMasivo.addEventListener('submit', function (e) {
            if (formMasivo.dataset.swalOk === '1') {
                return;
            }
            e.preventDefault();
            confirmarMasivo(e);
        });
    }

    function tokenCsrf() {
        return csrf;
    }

    const btnGuardarLista = document.getElementById('btn-guardar-lista');
    if (btnGuardarLista) {
        btnGuardarLista.addEventListener('click', function () {
            sincronizarPaginaHaciaStorage();
            const ids = leerSeleccion();
            if (ids.length === 0) {
                avisar('Seleccione al menos un cliente para guardar la lista.');
                return;
            }
            if (typeof Swal === 'undefined') {
                const nombre = window.prompt('Nombre de la lista');
                if (!nombre) return;
                enviarGuardarLista(ids, nombre, '');
                return;
            }
            let opciones = '<option value="">Nueva lista</option>';
            listasFe.forEach(function (l) {
                opciones += '<option value="' + String(l.id) + '">' + String(l.nombre) + ' (' + String(l.total) + ')</option>';
            });
            Swal.fire(Object.assign({
                title: 'Guardar lista',
                html: '<p class="text-sm mb-2">Se guardarán ' + ids.length + ' cliente(s) de la selección.</p>'
                    + '<input id="swal-lista-nombre" class="swal2-input" placeholder="Nombre (ej. Nodo 4)">'
                    + '<select id="swal-lista-id" class="swal2-select">' + opciones + '</select>'
                    + '<p class="text-xs text-left mt-1">Si elige una lista existente, se agregan los marcados (sin borrar los que ya tenía).</p>',
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#7c3aed',
                reverseButtons: true,
                focusCancel: true,
                preConfirm: function () {
                    const nombre = (document.getElementById('swal-lista-nombre') || {}).value || '';
                    const listaId = (document.getElementById('swal-lista-id') || {}).value || '';
                    if (!String(nombre).trim() && !listaId) {
                        Swal.showValidationMessage('Escriba un nombre o elija una lista existente.');
                        return false;
                    }
                    return { nombre: String(nombre).trim(), lista_id: listaId };
                },
            }, swalTheme())).then(function (result) {
                if (!result.isConfirmed || !result.value) {
                    return;
                }
                enviarGuardarLista(ids, result.value.nombre, result.value.lista_id);
            });
        });
    }

    function enviarGuardarLista(ids, nombre, listaId) {
        const body = {
            nombre: nombre || '',
            cliente_ids: ids.map(function (id) { return Number(id); }),
        };
        if (listaId) {
            body.lista_id = Number(listaId);
        }
        fetch(urlStoreLista, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': tokenCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().then(function (data) {
                return { okHttp: res.ok, data: data };
            });
        }).then(function (pack) {
            const msg = (pack.data && (pack.data.mensaje || (pack.data.errors && Object.values(pack.data.errors)[0]))) || 'No se pudo guardar la lista.';
            const texto = Array.isArray(msg) ? msg[0] : String(msg);
            if (!pack.okHttp || (pack.data && pack.data.ok === false)) {
                avisar(texto, 'error');
                return;
            }
            if (typeof Swal === 'undefined') {
                window.alert(texto);
                window.location.reload();
                return;
            }
            Swal.fire(Object.assign({
                icon: 'success',
                title: texto,
                confirmButtonText: 'Listo',
                confirmButtonColor: '#7c3aed',
            }, swalTheme())).then(function () {
                window.location.reload();
            });
        }).catch(function () {
            avisar('No se pudo guardar la lista.', 'error');
        });
    }

    document.addEventListener('click', function (e) {
        const cargar = e.target.closest('.btn-cargar-lista');
        if (cargar) {
            const raw = cargar.getAttribute('data-ids') || '';
            const ids = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            if (ids.length === 0) {
                avisar('La lista no tiene clientes.');
                return;
            }
            guardarSeleccion(ids);
            restaurarChecksDesdeStorage();
            actualizarBarra();
            if (ids.length > MAX) {
                avisar('Lista con ' + ids.length + ' clientes. La tanda inmediata admite ' + MAX + '; use «Facturar lote» (máx. ' + loteMax + ' por vez).');
            }
            return;
        }
        const facturar = e.target.closest('.btn-facturar-lista');
        if (!facturar || facturar.disabled) {
            return;
        }
        const url = facturar.getAttribute('data-url');
        const nombre = facturar.getAttribute('data-nombre') || 'lista';
        const pendientes = Number(facturar.getAttribute('data-pendientes') || 0);
        if (!url) {
            return;
        }
        if (montoModo && montoModo.value === 'otro') {
            const v = Number(montoFijoInput && montoFijoInput.value ? montoFijoInput.value : 0);
            if (!v || v < 1) {
                avisar('Ingrese el monto fijo en guaraníes.');
                return;
            }
        }
        if (!fechaEmisionValida()) {
            avisar('Elija una fecha de emisión válida (hoy o anterior).');
            if (fechaEmisionInput) fechaEmisionInput.focus();
            return;
        }
        guardarFechaEmision();
        const nLote = Math.min(pendientes, loteMax);
        const resto = Math.max(0, pendientes - nLote);
        const montoTxt = etiquetaMonto();
        const fechaTxt = etiquetaFechaEmision();
        const text = 'Lista «' + nombre + '»: ' + nLote + ' de ' + pendientes + ' pendiente(s) en ' + periodoLabel
            + ', emisión ' + fechaTxt + ', monto ' + montoTxt + '.'
            + (resto > 0 ? ' Quedarán ' + resto + ' para otro lote.' : '');

        const disparar = function (emitir) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            function hidden(name, value) {
                const i = document.createElement('input');
                i.type = 'hidden';
                i.name = name;
                i.value = value == null ? '' : String(value);
                form.appendChild(i);
            }
            hidden('_token', tokenCsrf());
            hidden('periodo', periodoActual);
            hidden('fecha_emision', fechaEmisionInput ? fechaEmisionInput.value : '');
            hidden('emitir', emitir ? '1' : '0');
            hidden('monto_modo', montoModo ? montoModo.value : 'plan');
            if (montoModo && montoModo.value === 'otro' && montoFijoInput) {
                hidden('monto_fijo', montoFijoInput.value);
            }
            document.body.appendChild(form);
            form.submit();
        };

        if (typeof Swal === 'undefined') {
            if (!window.confirm(text + ' ¿Enviar a SIFEN?')) {
                return;
            }
            disparar(true);
            return;
        }

        Swal.fire(Object.assign({
            icon: 'question',
            title: '¿Facturar lote de la lista?',
            text: text,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonColor: '#7c3aed',
            denyButtonColor: '#6d28d9',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Crear y enviar a SIFEN',
            denyButtonText: 'Solo borradores',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
        }, swalTheme())).then(function (result) {
            if (result.isConfirmed) {
                disparar(true);
            } else if (result.isDenied) {
                disparar(false);
            }
        });
    });

    restaurarChecksDesdeStorage();
    actualizarBarra();
})();
</script>
@endpush

