@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Facturas electrónicas</h1>
        <div class="flex flex-wrap gap-2">
            @can('facturas.crear')
            <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Datos manuales</a>
            <a href="{{ route('facturas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Nueva factura</a>      
            @else
            <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">Datos manuales</a>
            <a href="{{ route('facturas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Nueva factura</a>
            @endcan
        </div>
    </div>

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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 text-sm">Filtrar</button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
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
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
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
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($f->total, 0, ',', '.') }} {{ $f->moneda }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('facturas.show', $f) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">Ver</a>
                                @if($f->estado === 'borrador')
                                    <a href="{{ route('facturas.edit', $f) }}" class="ml-2 text-gray-600 dark:text-gray-400 hover:underline text-sm">Editar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay facturas. <a href="{{ route('facturas.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear una</a>.</td>
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
@endsection
