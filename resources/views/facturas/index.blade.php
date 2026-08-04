@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Facturas electrónicas</h1>
        <div class="flex flex-wrap gap-2">
            @can('facturas.crear')
            @if(($lotesPendientesCount ?? 0) > 0)
            <form method="POST" action="{{ route('facturas.consultar-lotes') }}" class="inline"
                  onsubmit="return confirm('¿Consultar los {{ $lotesPendientesCount }} lote(s) pendiente(s) en SIFEN?');">
                @csrf
                <input type="hidden" name="todos" value="1">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 text-sm">
                    Consultar lotes ({{ $lotesPendientesCount }})
                </button>
            </form>
            @endif
            <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Datos manuales</a>
            <a href="{{ route('facturas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Nueva factura</a>
            @else
            <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">Datos manuales</a>
            <a href="{{ route('facturas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Nueva factura</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800 text-sm">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Resumen del mes</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 capitalize">Facturas emitidas · {{ $mesDashboardLabel }}</p>
            </div>
            <form method="GET" action="{{ route('facturas.index') }}" class="flex flex-wrap items-end gap-2">
                @foreach (request()->except(['mes', 'page']) as $key => $value)
                    @if(is_array($value))
                        @foreach ($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div>
                    <label for="mes" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Mes</label>
                    <input type="month" name="mes" id="mes" value="{{ $mesDashboard->format('Y-m') }}"
                           class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <button type="submit" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500">Ver</button>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-purple-200 dark:border-purple-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-purple-700 dark:text-purple-400 uppercase tracking-wide">Monto emitido</p>
                <p class="text-2xl font-bold text-purple-700 dark:text-purple-300 mt-1">
                    {{ number_format((float) $statsEmitidasMes->monto_total, 0, ',', '.') }}
                    <span class="text-sm font-semibold">PYG</span>
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wide">Facturas emitidas</p>
                <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">{{ number_format((int) $statsEmitidasMes->cantidad, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-blue-200 dark:border-blue-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-blue-700 dark:text-blue-400 uppercase tracking-wide">IVA emitido</p>
                <p class="text-2xl font-bold text-blue-700 dark:text-blue-300 mt-1">
                    {{ number_format((float) $statsEmitidasMes->monto_iva, 0, ',', '.') }}
                    <span class="text-sm font-semibold">PYG</span>
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Borradores del mes</p>
                <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ number_format($borradoresMes, 0, ',', '.') }}</p>
                @if($borradoresMes > 0)
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ number_format($montoBorradoresMes, 0, ',', '.') }} PYG pendientes</p>
                @endif
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-200 dark:border-indigo-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-indigo-700 dark:text-indigo-400 uppercase tracking-wide">Lotes SIFEN</p>
                <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 mt-1">{{ number_format($lotesPendientesCount ?? 0, 0, ',', '.') }}</p>
                @if(($lotesPendientesCount ?? 0) > 0)
                    <a href="{{ route('facturas.index', ['lote_pendiente' => 1]) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline mt-1 inline-block">Ver pendientes</a>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sin pendientes</p>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('facturas.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            @if(request('mes'))
                <input type="hidden" name="mes" value="{{ request('mes') }}">
            @endif
            <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
                <div class="sm:w-40">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
                    <select name="estado" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach (App\Models\Factura::estados() as $key => $label)
                            <option value="{{ $key }}" {{ request('estado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-56">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</label>
                    <select name="cliente_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->cliente_id }}" {{ request('cliente_id') == $c->cliente_id ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-36">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Desde</label>
                    <input type="date" name="desde" value="{{ request('desde') }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:w-36">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Hasta</label>
                    <input type="date" name="hasta" value="{{ request('hasta') }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 self-end pb-2">
                    <input type="checkbox" name="lote_pendiente" value="1" {{ request()->boolean('lote_pendiente') ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                    Solo lotes pendientes
                </label>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 text-sm">Filtrar</button>
                </div>
            </div>
        </form>

        <div id="barra-lotes" class="hidden sticky top-0 z-10 px-4 py-3 border-b border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-indigo-900 dark:text-indigo-100">
                <span id="contador-lotes" class="font-semibold">0</span> factura(s) seleccionada(s)
            </p>
            <button type="submit" form="form-consultar-lotes" class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700">
                Consultar lotes seleccionados
            </button>
        </div>

        <form method="POST" action="{{ route('facturas.consultar-lotes') }}" id="form-consultar-lotes"
              onsubmit="return confirmarConsultaLotes(event);">
            @csrf
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left w-10">
                            <input type="checkbox" id="seleccionar-lotes"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                   title="Seleccionar pendientes de esta página">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Número</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse ($facturas as $f)
                        @php
                            $pendienteLote = $f->lotePendienteSifen();
                            $enCola = $f->enColaSifen();
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $pendienteLote || $enCola ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : '' }}">
                            <td class="px-4 py-3">
                                @if($pendienteLote && $f->set_estado_envio !== 'consultando')
                                    <input type="checkbox" name="factura_ids[]" value="{{ $f->id }}" form="form-consultar-lotes"
                                           class="chk-lote rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $f->id }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($f->esOcasional())
                                    <span class="text-gray-900 dark:text-gray-100">{{ $f->receptorNombreCompleto() }}</span>
                                    <span class="block text-xs text-purple-600 dark:text-purple-400">Ocasional</span>
                                @elseif($f->cliente)
                                    <a href="{{ route('clientes.edit', $f->cliente) }}" class="text-purple-600 dark:text-purple-400 hover:underline">{{ $f->cliente->nombre }} {{ $f->cliente->apellido }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">{{ $f->numero_completo ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $f->fecha_emision->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ App\Models\Factura::tiposDocumento()[$f->tipo_documento] ?? $f->tipo_documento }}</td>
                            <td class="px-4 py-3">
                                @php $estados = App\Models\Factura::estados(); @endphp
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    @if($f->estado === 'emitida') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                                    @elseif($f->estado === 'anulada') bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300
                                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                                    {{ $estados[$f->estado] ?? $f->estado }}
                                </span>
                                @if($f->set_estado_envio === 'en_cola')
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                        Emitiendo…
                                    </span>
                                @elseif($f->set_estado_envio === 'consultando')
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                        Consultando lote…
                                    </span>
                                @elseif($pendienteLote)
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300">
                                        Lote pendiente
                                    </span>
                                @elseif($f->set_estado_envio === 'rechazado')
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">
                                        SIFEN rechazó
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($f->total, 0, ',', '.') }} {{ $f->moneda }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('facturas.show', $f) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">Ver</a>
                                @if($pendienteLote && $f->set_estado_envio !== 'consultando')
                                    <form action="{{ route('facturas.consultar-lote', $f) }}" method="POST" class="inline ml-2">
                                        @csrf
                                        <button type="submit" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">Consultar lote</button>
                                    </form>
                                @elseif($f->estado === 'borrador' && ! $enCola && ! $pendienteLote)
                                    <a href="{{ route('facturas.edit', $f) }}" class="ml-2 text-gray-600 dark:text-gray-400 hover:underline text-sm">Editar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay facturas. <a href="{{ route('facturas.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear una</a>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($facturas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">{{ $facturas->links() }}</div>
        @endif
    </div>
</div>

<script>
(function () {
    const checks = () => Array.from(document.querySelectorAll('.chk-lote'));
    const barra = document.getElementById('barra-lotes');
    const contador = document.getElementById('contador-lotes');
    const todos = document.getElementById('seleccionar-lotes');

    function actualizar() {
        const seleccionados = checks().filter(c => c.checked);
        const n = seleccionados.length;
        contador.textContent = String(n);
        barra.classList.toggle('hidden', n === 0);
        if (todos) {
            const elegibles = checks();
            todos.checked = elegibles.length > 0 && elegibles.every(c => c.checked);
            todos.indeterminate = n > 0 && n < elegibles.length;
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('chk-lote') || e.target.id === 'seleccionar-lotes') {
            if (e.target.id === 'seleccionar-lotes') {
                checks().forEach(c => { c.checked = e.target.checked; });
            }
            actualizar();
        }
    });

    window.confirmarConsultaLotes = function () {
        const n = checks().filter(c => c.checked).length;
        if (n === 0) {
            alert('Seleccione al menos una factura con lote pendiente.');
            return false;
        }
        return confirm('¿Consultar ' + n + ' lote(s) en SIFEN?');
    };

    actualizar();
})();
</script>
@endsection
