@extends('layouts.app')

@section('title', 'Usuarios Hotspot')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Usuarios Hotspot</h1>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('hotspot.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                Asociar a servicio
            </a>
            <a href="{{ route('hotspot.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                Dashboard activos
            </a>
            <a href="{{ route('hotspot.perfiles.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                Perfiles hotspot
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('hotspot.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 flex flex-wrap gap-3">
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar por usuario, comentario o cliente..."
                        class="flex-1 min-w-[200px] px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <select name="router_id" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 py-2.5 px-3 min-w-[180px]">
                        <option value="">Todos los routers</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_id }}" {{ request('router_id') == $r->router_id ? 'selected' : '' }}>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                    Buscar
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Servicio</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Router</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Perfil</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Última sync</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($servicioHotspots as $sh)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('servicios.edit', $sh->servicio_id) }}" class="text-purple-600 dark:text-purple-400 hover:underline">#{{ $sh->servicio_id }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono">{{ $sh->username }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $sh->servicio?->cliente?->nombre }} {{ $sh->servicio?->cliente?->apellido }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $sh->router?->nombre }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $sh->hotspotPerfil?->nombre ?? 'default' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sh->last_synced?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('hotspot.sync', $sh) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">Sincronizar</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay usuarios hotspot. Asocia un servicio desde la edición del servicio.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($servicioHotspots->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $servicioHotspots->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
