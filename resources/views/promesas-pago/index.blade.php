@extends('layouts.app')

@section('title', 'Promesas de pago')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Promesas de pago</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Acuerdos de pago con fecha y hora de cumplimiento. Las vencidas pueden seguir en la lista hasta que el sistema las procese.</p>
        </div>
        <a href="{{ route('factura-internas.pendientes') }}"
           class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M9 12h10.5m0 0-3.75-3.75M19.5 12l-3.75 3.75" />
            </svg>
            Ir a pendiente de pago
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('promesas-pago.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3 flex-wrap items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Buscar cliente</label>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre, apellido o cédula..."
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:w-44">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
                    <select name="estado" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-amber-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Todos</option>
                        <option value="vigente" {{ request('estado') === 'vigente' ? 'selected' : '' }}>Vigentes</option>
                        <option value="vencida" {{ request('estado') === 'vencida' ? 'selected' : '' }}>Vencidas (pendiente de proceso)</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-700 dark:bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-800 text-sm">Filtrar</button>
                    @if(request('buscar') || request('estado'))
                        <a href="{{ route('promesas-pago.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">Limpiar</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Factura</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vencimiento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo factura</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Registró</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($promesas as $p)
                        @php
                            $vigente = $p->vencimiento_at->isFuture();
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $p->id }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">#{{ $p->factura_interna_id }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $p->cliente->nombre }} {{ $p->cliente->apellido }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $p->vencimiento_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">
                                @if($vigente)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">Vigente</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">Vencida</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-amber-700 dark:text-amber-400">
                                @if($p->facturaInterna)
                                    {{ number_format($p->facturaInterna->saldo_pendiente, 0, ',', '.') }} {{ $p->facturaInterna->moneda }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $p->usuario?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($p->facturaInterna)
                                    <a href="{{ route('factura-internas.show', $p->facturaInterna) }}" class="inline-flex items-center justify-center p-2 rounded-lg text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/30" title="Ver factura">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay promesas de pago registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($promesas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $promesas->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
