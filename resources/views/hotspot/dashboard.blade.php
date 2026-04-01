@extends('layouts.app')

@section('title', 'Hotspot - Clientes activos')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Hotspot - Clientes activos</h1>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('hotspot.dashboard') }}" class="flex flex-wrap items-center gap-2">
                <select name="router_id" onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm min-w-[200px]">
                    <option value="">Seleccionar router...</option>
                    @foreach($routers as $r)
                        <option value="{{ $r->router_id }}" {{ (request('router_id') == $r->router_id) ? 'selected' : '' }}>
                            {{ $r->nombre }} ({{ $r->ip }})
                        </option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('hotspot.index') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                Usuarios hotspot
            </a>
            <a href="{{ route('hotspot.perfiles.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                Perfiles hotspot
            </a>
        </div>
    </div>

    @if($selectedRouter)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Clientes activos ({{ count($activeHosts) }})</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $selectedRouter->nombre }} - {{ $selectedRouter->ip }}</p>
            </div>
            <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">MAC</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Uptime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($activeHosts as $host)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $host['user'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ $host['address'] ?? $host['ip'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 font-mono">{{ $host['mac-address'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ $host['uptime'] ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay clientes activos</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Usuarios hotspot por servicio</h2>
            </div>
            <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Servicio</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($servicioHotspots as $sh)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">#{{ $sh->servicio_id }}</td>
                            <td class="px-4 py-2 text-sm font-mono">{{ $sh->username }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ $sh->servicio?->cliente?->nombre }} {{ $sh->servicio?->cliente?->apellido }}</td>
                            <td class="px-4 py-2 text-right">
                                <form action="{{ route('hotspot.sync', $sh) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-purple-600 dark:text-purple-400 hover:underline text-sm" title="Sincronizar">Sync</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay usuarios hotspot asociados</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($servicioHotspots->hasPages())
            <div class="p-3 border-t border-gray-200 dark:border-gray-700">
                {{ $servicioHotspots->links() }}
            </div>
            @endif
        </div>
    </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-8 text-center">
        <p class="text-gray-500 dark:text-gray-400">Selecciona un router para ver los clientes activos en hotspot.</p>
    </div>
    @endif
</div>
@endsection
