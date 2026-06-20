@extends('layouts.app')

@section('title', 'Nueva factura electrónica')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('facturas.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a facturas</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Nueva factura electrónica</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleccione el cliente para emitir el documento SIFEN. Período: <strong class="capitalize">{{ $mesLabel }}</strong>.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Clientes</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($totalActivos, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-800/50 p-4 shadow-sm">
            <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wide">Emitidos este mes</p>
            <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">{{ number_format($emitidosMes, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800/50 p-4 shadow-sm">
            <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Sin emitir este mes</p>
            <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ number_format($pendientesMes, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('facturas.create') }}" class="p-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col lg:flex-row gap-3 lg:items-end">
                <div class="flex-1 min-w-0">
                    <label for="buscar" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Buscar cliente</label>
                    <input type="text" name="buscar" id="buscar" value="{{ request('buscar') }}"
                           placeholder="Nombre, apellido o cédula/RUC…"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pb-2.5">
                    <input type="checkbox" name="solo_pendientes" value="1" {{ request()->boolean('solo_pendientes') ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                    Solo sin emitir este mes
                </label>
                <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 text-sm">Buscar</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
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
                                    <a href="{{ route('facturas.create-cliente', $cliente) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg text-white {{ $emitidoMes ? 'bg-gray-600 hover:bg-gray-700' : 'bg-purple-600 hover:bg-purple-700' }}">
                                        {{ $emitidoMes ? 'Nueva DE' : 'Emitir factura' }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No hay clientes que coincidan con la búsqueda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clientes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                {{ $clientes->links() }}
            </div>
        @endif
    </div>

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        Se considera <strong>emitido</strong> cuando el cliente tiene al menos una factura electrónica en estado «Emitida» con fecha de emisión en el mes actual.
    </p>
</div>
@endsection
