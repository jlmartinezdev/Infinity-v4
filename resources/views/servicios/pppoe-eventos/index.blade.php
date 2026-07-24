@extends('layouts.app')

@section('title', 'Eventos PPPoE MikroTik')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Eventos PPPoE MikroTik</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Agrupado por cliente · último evento y cantidad de conexiones/desconexiones.
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="pppoe-badge pppoe-badge--md pppoe-badge--up">
                Hoy conectados: {{ (int) ($resumenHoy[\App\Models\ServicioConexionEvento::TIPO_PPPOE_UP] ?? 0) }}
            </span>
            <span class="pppoe-badge pppoe-badge--md pppoe-badge--down">
                Hoy desconectados: {{ (int) ($resumenHoy[\App\Models\ServicioConexionEvento::TIPO_PPPOE_DOWN] ?? 0) }}
            </span>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('servicios.pppoe-eventos.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col lg:flex-row gap-3 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Buscar</label>
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                           placeholder="Usuario PPPoE, cliente, IP, MAC…"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                </div>
                <div class="sm:w-40">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Último evento</label>
                    <select name="tipo" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                        <option value="">Todos</option>
                        <option value="{{ \App\Models\ServicioConexionEvento::TIPO_PPPOE_UP }}" @selected(request('tipo') === \App\Models\ServicioConexionEvento::TIPO_PPPOE_UP)>Conectado</option>
                        <option value="{{ \App\Models\ServicioConexionEvento::TIPO_PPPOE_DOWN }}" @selected(request('tipo') === \App\Models\ServicioConexionEvento::TIPO_PPPOE_DOWN)>Desconectado</option>
                    </select>
                </div>
                <div class="sm:w-52">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Fuente</label>
                    <select name="fuente" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                        @foreach(\App\Models\ServicioConexionEvento::fuentesPppoeFiltro() as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(request('fuente', 'mikrotik') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-44">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Router</label>
                    <select name="router_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                        <option value="">Todos</option>
                        @foreach($routers as $router)
                            <option value="{{ $router->router_id }}" @selected((string) request('router_id') === (string) $router->router_id)>{{ $router->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-36">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                </div>
                <div class="sm:w-36">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        Filtrar
                    </button>
                    @if(request()->hasAny(['buscar', 'tipo', 'router_id', 'fecha_desde', 'fecha_hasta']) || request('fuente', 'mikrotik') !== 'mikrotik')
                        <a href="{{ route('servicios.pppoe-eventos.index') }}" class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Limpiar
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Último evento</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Usuario PPPoE</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">IP / MAC</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Router</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Fuente</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($grupos as $grupo)
                        @php $ev = $grupo->ultimo_evento; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($grupo->cliente)
                                        <a href="{{ route('clientes.edit', $grupo->cliente) }}" class="font-medium text-teal-600 dark:text-teal-400 hover:underline">
                                            {{ $grupo->cliente_nombre }}
                                        </a>
                                    @else
                                        <span class="font-medium">{{ $grupo->cliente_nombre }}</span>
                                    @endif
                                    <span class="pppoe-count-badge"
                                          title="{{ $grupo->total_eventos }} evento(s) en el período filtrado">
                                        {{ $grupo->total_eventos }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($ev)
                                    <div class="flex flex-col gap-1">
                                        <span class="text-gray-700 dark:text-gray-200">{{ $ev->ocurrio_at?->format('d/m/Y H:i:s') }}</span>
                                        @if($ev->esPppoeConexion())
                                            <span class="pppoe-badge pppoe-badge--sm pppoe-badge--up">Conectado</span>
                                        @else
                                            <span class="pppoe-badge pppoe-badge--sm pppoe-badge--down">Desconectado</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">
                                {{ $ev?->usuario_pppoe ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @if($ev?->ip)
                                    <div class="font-mono text-xs">{{ $ev->ip }}</div>
                                @endif
                                @if($ev?->mac_address)
                                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $ev->mac_address }}</div>
                                @endif
                                @if(! $ev?->ip && ! $ev?->mac_address)
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $ev?->router?->nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $ev?->etiquetaFuente() ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($ev?->servicio_id)
                                    <a href="{{ route('servicios.herramientas-red', $ev->servicio_id) }}"
                                       class="inline-flex items-center rounded-lg border border-teal-600 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-300 dark:hover:bg-teal-950/40">
                                        Herramientas
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                No hay eventos PPPoE con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($grupos->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $grupos->links() }}
            </div>
        @endif
    </div>

    <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
        Webhook MikroTik: <span class="font-mono">POST /api/v1/webhooks/mikrotik/pppoe</span>
        · El badge indica cuántos eventos tiene el cliente con los filtros actuales.
    </p>
</div>
@endsection

@push('scripts')
<style>
    .pppoe-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        border-radius: 9999px;
        font-weight: 600;
        line-height: 1.2;
    }
    .pppoe-badge--sm {
        padding: 0.15rem 0.55rem;
        font-size: 11px;
    }
    .pppoe-badge--md {
        padding: 0.25rem 0.75rem;
        font-size: 12px;
    }
    .pppoe-badge--up {
        background: #e0f2fe;
        color: #0c4a6e;
        border: 1px solid #7dd3fc;
    }
    .dark .pppoe-badge--up {
        background: #0c4a6e;
        color: #e0f2fe;
        border-color: #0284c7;
    }
    .pppoe-badge--down {
        background: #ffedd5;
        color: #7c2d12;
        border: 1px solid #fdba74;
    }
    .dark .pppoe-badge--down {
        background: #7c2d12;
        color: #ffedd5;
        border-color: #ea580c;
    }
    .pppoe-count-badge {
        display: inline-flex;
        min-width: 1.5rem;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        padding: 0.125rem 0.5rem;
        font-size: 11px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        background: #e2e8f0;
        color: #334155;
        border: 1px solid #cbd5e1;
    }
    .dark .pppoe-count-badge {
        background: #334155;
        color: #f1f5f9;
        border-color: #475569;
    }
</style>
@endpush
