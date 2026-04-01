@extends('layouts.app')

@section('title', 'Perfiles Hotspot')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Perfiles Hotspot</h1>
        <div class="flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('hotspot.perfiles.sync-mikrotik') }}" class="inline-flex items-center gap-2">
                @csrf
                <select name="router_id" required
                    class="rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm min-w-[180px]">
                    <option value="">Seleccionar router...</option>
                    @foreach($routers ?? [] as $r)
                        <option value="{{ $r->router_id }}">{{ $r->nombre }} ({{ $r->ip }})</option>
                    @endforeach
                </select>
                <button type="submit" onclick="return confirm('¿Sincronizar perfiles hotspot al router MikroTik?');"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sincronizar con MikroTik
                </button>
            </form>
            <a href="{{ route('hotspot.perfiles.create') }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                Nuevo perfil
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('hotspot.perfiles.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    placeholder="Buscar por nombre o rate limit..."
                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                    Buscar
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rate Limit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Shared Users</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($perfiles as $perfil)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 text-sm">{{ $perfil->hotspot_perfil_id }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $perfil->nombre }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $perfil->rate_limit ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $perfil->shared_users ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('hotspot.perfiles.edit', $perfil) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Editar</a>
                            <form action="{{ route('hotspot.perfiles.destroy', $perfil) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este perfil?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay perfiles. <a href="{{ route('hotspot.perfiles.create') }}" class="text-purple-600 hover:underline">Crear uno</a>.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($perfiles->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $perfiles->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
