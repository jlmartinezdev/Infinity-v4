@extends('layouts.app')

@section('title', 'IPs del pool')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">IPs del pool</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $pool->ip_range }} — {{ $pool->router?->nombre ?? 'Router' }} ({{ $pool->router?->ip ?? '' }})</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.pool-ip-asignadas.create', ['pool_id' => $pool->pool_id]) }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Agregar IP
            </a>
            <a href="{{ route('sistema.router-ip-pools.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
                Volver a pools
            </a>
            <form action="{{ route('sistema.router-ip-pools.destroy', $pool) }}" method="POST" class="inline"
                onsubmit="return confirm('¿Eliminar el pool completo ({{ e($pool->ip_range) }}) y todas sus IPs asignadas? Esta acción no se puede deshacer.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Eliminar pool completo
                </button>
            </form>
        </div>
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ver otro pool</label>
        <select onchange="if(this.value) window.location='{{ route('sistema.pool-ip-asignadas.index') }}?pool_id='+this.value"
            class="w-full max-w-xs px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <option value="">-- Seleccionar pool --</option>
            @foreach($pools as $p)
                <option value="{{ $p->pool_id }}" {{ $p->pool_id == $pool->pool_id ? 'selected' : '' }}>
                    #{{ $p->pool_id }} {{ $p->ip_range }} ({{ $p->router?->nombre ?? '—' }})
                </option>
            @endforeach
        </select>
    </div>


    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Creado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ips as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->ip }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($item->estado === 'disponible')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Disponible</span>
                                @elseif($item->estado === 'asignada')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">Asignada</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300">Reservada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') : '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <a href="{{ route('sistema.pool-ip-asignadas.edit', ['pool_id' => $pool->pool_id, 'ip' => str_replace('.', '_', $item->ip)]) }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium mr-4">Editar</a>
                                <form action="{{ route('sistema.pool-ip-asignadas.destroy', ['pool_id' => $pool->pool_id, 'ip' => str_replace('.', '_', $item->ip)]) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta IP del pool?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay IPs en este pool. <a href="{{ route('sistema.pool-ip-asignadas.create', ['pool_id' => $pool->pool_id]) }}" class="text-purple-600 dark:text-purple-400 hover:underline">Agregar IP</a>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($ips->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $ips->appends(['pool_id' => $pool->pool_id])->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
